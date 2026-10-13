<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Predicción de migraña con el servicio de ML (FastAPI en Render).
 *
 * Laravel lee los datos de la persona desde la BD (Aiven) y se los envía al ML
 * SIN nombre ni correo: solo perfil, episodios y síntomas con sus códigos.
 * Así las claves de la base de datos viven únicamente en Laravel.
 */
class PrediccionService
{
    public const DIAS_HISTORIAL = 365;
    public const UMBRAL_ALERTA = 0.70;   // "muy alto" en el ML

    public function __construct(private PerfilService $perfiles, private NotificacionService $notificaciones)
    {
    }

    public function configurado(): bool
    {
        return config('services.ml.url') !== '' && (string) config('services.ml.key') !== '';
    }

    /** Datos de UNA persona en el formato que espera el ML (ver caracteristicas.py). */
    public function datosPaciente(int $idUsuario, int $dias = self::DIAS_HISTORIAL): array
    {
        $desde = Carbon::now()->subDays($dias);

        $episodios = DB::table('episodio_migrana')
            ->where('id_usuario', $idUsuario)
            ->where('fecha_inicio', '>=', $desde->toDateString())
            ->orderBy('fecha_inicio')->orderBy('hora_inicio')
            ->get(['id_episodio', 'fecha_inicio', 'hora_inicio', 'nivel_dolor', 'duracion']);
        $desenc = DB::table('episodio_desencadenante as x')
            ->join('desencadenante as d', 'd.id_desencadenante', '=', 'x.id_desencadenante')
            ->whereIn('x.id_episodio', $episodios->pluck('id_episodio'))
            ->get(['x.id_episodio', 'd.codigo'])->groupBy('id_episodio');

        $registros = DB::table('registro_sintoma')
            ->where('id_usuario', $idUsuario)
            ->where('fecha', '>=', $desde->toDateString())
            ->orderBy('fecha')->orderBy('hora')
            ->get(['id_registro', 'fecha', 'hora', 'intensidad']);
        $sintomas = DB::table('detalle_registro_sintoma as d')
            ->join('sintoma_prodromico as s', 's.id_sintoma', '=', 'd.id_sintoma')
            ->whereIn('d.id_registro', $registros->pluck('id_registro'))
            ->get(['d.id_registro', 's.codigo'])->groupBy('id_registro');

        $fecha = fn ($dia, $hora) => Carbon::parse($dia.' '.$hora)->format('Y-m-d\TH:i:s');

        return [
            'perfil'    => $this->perfiles->cargar($idUsuario),
            'episodios' => $episodios->map(fn ($e) => [
                'inicio'          => $fecha($e->fecha_inicio, $e->hora_inicio),
                'intensidad'      => (int) $e->nivel_dolor,
                'duracion'        => $e->duracion,
                'desencadenantes' => $desenc->get($e->id_episodio, collect())->pluck('codigo')->values()->all(),
            ])->values()->all(),
            'registros' => $registros->map(fn ($r) => [
                'fecha'      => $fecha($r->fecha, $r->hora),
                'intensidad' => (int) $r->intensidad,
                'sintomas'   => $sintomas->get($r->id_registro, collect())->pluck('codigo')->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Pide la predicción al ML. Se guarda 10 minutos en caché mientras la persona
     * no registre nada nuevo (la "huella" cambia con cada registro).
     *
     * @throws RuntimeException si el servicio no está configurado o no responde
     */
    public function predecir(int $idUsuario): array
    {
        if (! $this->configurado()) {
            throw new RuntimeException('El servicio de predicción no está configurado (ML_URL / ML_API_KEY).');
        }

        $huella = implode('|', [
            DB::table('episodio_migrana')->where('id_usuario', $idUsuario)->max('id_episodio'),
            DB::table('registro_sintoma')->where('id_usuario', $idUsuario)->max('id_registro'),
            DB::table('episodio_migrana')->where('id_usuario', $idUsuario)->count(),
            DB::table('registro_sintoma')->where('id_usuario', $idUsuario)->count(),
            optional(DB::table('perfil')->where('id_usuario', $idUsuario)->first(['fecha_actualizacion']))->fecha_actualizacion,
        ]);

        $resultado = Cache::remember("ml:prediccion:$idUsuario:".md5($huella), now()->addMinutes(10), function () use ($idUsuario) {
            $datos = $this->datosPaciente($idUsuario) + ['ahora' => Carbon::now()->format('Y-m-d\TH:i:s')];
            return $this->llamar('/predecir', $datos);
        });

        $this->avisarSiEsMuyAlto($idUsuario, $resultado);
        return $resultado;
    }

    /** Todos los usuarios, SIN identificar, para reentrenar el ML con datos reales. */
    public function datosParaEntrenar(): array
    {
        $ids = DB::table('usuario')->where('activo', 1)->pluck('id_usuario');
        $pacientes = [];
        foreach ($ids as $id) {
            $p = $this->datosPaciente((int) $id, 730);
            if ($p['episodios'] || $p['registros']) {
                $pacientes[] = $p;        // sin id, nombre ni correo
            }
        }
        shuffle($pacientes);              // el orden tampoco delata a nadie
        return $pacientes;
    }

    public function reentrenar(int $simulados = 800): array
    {
        return $this->llamar('/reentrenar', [
            'ahora'     => Carbon::now()->format('Y-m-d\TH:i:s'),
            'simulados' => $simulados,
            'pacientes' => $this->datosParaEntrenar(),
        ]);
    }

    public function estadoReentreno(): array
    {
        return $this->llamar('/reentrenar/estado', null, 'get');
    }

    protected function llamar(string $ruta, ?array $cuerpo, string $metodo = 'post'): array
    {
        try {
            $http = Http::withHeaders(['X-API-Key' => (string) config('services.ml.key')])
                ->acceptJson()
                ->timeout((int) config('services.ml.timeout'));
            $r = $metodo === 'get'
                ? $http->get(config('services.ml.url').$ruta)
                : $http->post(config('services.ml.url').$ruta, $cuerpo);
        } catch (ConnectionException $e) {
            Log::warning('ML sin respuesta', ['ruta' => $ruta, 'error' => $e->getMessage()]);
            throw new RuntimeException('El servicio de predicción no responde. Si estuvo inactivo tarda cerca de 1 minuto en despertar: intenta de nuevo.');
        }

        if (! $r->successful()) {
            Log::warning('ML respondió con error', ['ruta' => $ruta, 'status' => $r->status(), 'cuerpo' => mb_substr($r->body(), 0, 300)]);
            throw new RuntimeException('El servicio de predicción respondió con un error ('.$r->status().').');
        }

        return $r->json() ?? [];
    }

    /** Si el riesgo es muy alto y la persona lo permite, deja una notificación (máximo 1 cada 24 h). */
    private function avisarSiEsMuyAlto(int $idUsuario, array $r): void
    {
        if (($r['probabilidad'] ?? 0) < self::UMBRAL_ALERTA) {
            return;
        }
        $quiere = (bool) DB::table('usuario')->where('id_usuario', $idUsuario)->value('alerta_riesgo');
        $reciente = DB::table('notificacion')->where('id_usuario', $idUsuario)->where('tipo', 'alerta_riesgo')
            ->where('fecha_hora', '>=', Carbon::now()->subDay()->format('Y-m-d H:i:s'))->exists();
        if (! $quiere || $reciente) {
            return;
        }

        $msg = sprintf('Riesgo %s de migraña (%s%%) en las próximas 48 h. %s',
            str_replace('_', ' ', $r['nivel'] ?? 'alto'), $r['porcentaje'] ?? round($r['probabilidad'] * 100), $r['mensaje'] ?? '');
        try {
            $this->notificaciones->crear($idUsuario, 'alerta_riesgo', mb_substr($msg, 0, 255), null);
        } catch (\Throwable $e) {
            Log::warning('No se pudo crear la alerta de riesgo', ['error' => $e->getMessage()]);
        }
    }
}
