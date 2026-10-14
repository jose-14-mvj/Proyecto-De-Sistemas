<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Predicción de migraña con el servicio de ML (FastAPI en Render).
 *
 * Laravel lee los datos de la persona desde la BD (Aiven) y se los envía al ML
 * SIN nombre ni correo: solo perfil, episodios y síntomas con sus códigos.
 * Así las claves de la base de datos viven únicamente en Laravel.
 *
 * APRENDIZAJE CONTINUO (tabla modelo_ml):
 *  - Cada cierto tiempo (máx. 1 vez cada 24 h y con al menos 20 registros nuevos) se pide
 *    al ML que se reentrene con los datos de TODOS los usuarios (anónimos).
 *  - El ML solo activa el modelo nuevo si pasa su filtro de calidad (no acierta peor).
 *  - El modelo activo se guarda en Aiven; si Render reinicia el ML (que entonces vuelve al
 *    modelo original), Laravel se lo devuelve en la siguiente predicción. No se pierde nada.
 *  - Todo esto va dentro de try/catch: si falla, la predicción sigue funcionando igual.
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
            optional($this->modeloActivo(false))->version,          // modelo nuevo = predicción nueva
        ]);

        $llamoAlMl = false;
        $resultado = Cache::remember("ml:prediccion:$idUsuario:".md5($huella), now()->addMinutes(10), function () use ($idUsuario, &$llamoAlMl) {
            $llamoAlMl = true;
            $datos = $this->datosPaciente($idUsuario) + ['ahora' => Carbon::now()->format('Y-m-d\TH:i:s')];
            $r = $this->llamar('/predecir', $datos);
            // ¿El ML arrancó con el modelo original? Se le devuelve lo aprendido y se vuelve a preguntar
            if ($this->restaurarModeloSiHaceFalta($r['modelo_version'] ?? null)) {
                $r = $this->llamar('/predecir', $datos);
            }
            return $r;
        });

        $this->avisarSiEsMuyAlto($idUsuario, $resultado);
        if ($llamoAlMl) {                 // solo con el ML ya despierto: nunca hace esperar de más
            $this->mantenimientoAprendizaje();
        }
        return $resultado;
    }

    /* =====================================================================
     *  APRENDIZAJE CONTINUO
     * ===================================================================== */

    /** ¿Ya se creó la tabla modelo_ml? (si aún no se corrió la migración, todo esto se salta) */
    public function aprendizajeDisponible(): bool
    {
        try {
            return Cache::remember('ml:tabla_modelo', now()->addMinutes(10), fn () => Schema::hasTable('modelo_ml'));
        } catch (Throwable) {
            return false;
        }
    }

    /** Fila del modelo activo (sin el archivo, salvo que se pida) o null */
    public function modeloActivo(bool $conArchivo = true): ?object
    {
        if (! $this->aprendizajeDisponible()) {
            return null;
        }
        try {
            $q = DB::table('modelo_ml')->where('estado', 'activo')->orderByDesc('id_modelo');
            return $conArchivo ? $q->first() : $q->first(['id_modelo', 'version', 'auc', 'fecha_fin']);
        } catch (Throwable $e) {
            Log::warning('No se pudo leer modelo_ml', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /** Si el ML no tiene el modelo activo guardado en la BD, se lo envía. Devuelve true si lo cargó. */
    protected function restaurarModeloSiHaceFalta(?string $versionMl): bool
    {
        $activo = $this->modeloActivo(false);
        if (! $activo || ! $activo->version || $activo->version === $versionMl) {
            return false;
        }
        try {
            $fila = DB::table('modelo_ml')->where('id_modelo', $activo->id_modelo)->first(['archivo', 'sha256']);
            if (! $fila || ! $fila->archivo) {
                return false;
            }
            $r = $this->http(60)->withHeaders(['X-Sha256' => $fila->sha256])
                ->withBody($fila->archivo, 'application/octet-stream')
                ->post(config('services.ml.url').'/modelo/cargar');
            if (! $r->successful()) {
                Log::warning('El ML no aceptó el modelo guardado', ['status' => $r->status(), 'cuerpo' => mb_substr($r->body(), 0, 200)]);
                return false;
            }
            Log::info('Modelo aprendido restaurado en el ML', ['version' => $activo->version]);
            return true;
        } catch (Throwable $e) {
            Log::warning('No se pudo restaurar el modelo en el ML', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /** Revisa un reentrenamiento en curso y, si toca, inicia otro. Nunca lanza errores. */
    public function mantenimientoAprendizaje(): void
    {
        try {
            if (! $this->configurado() || ! $this->aprendizajeDisponible() || ! Cache::add('ml:mantenimiento', 1, 120)) {
                return;
            }
            $this->revisarReentreno();
            if (config('services.ml.auto_reentrenar')) {
                $this->iniciarReentreno(false);
            }
        } catch (Throwable $e) {
            Log::warning('Mantenimiento del aprendizaje del ML', ['error' => $e->getMessage()]);
        }
    }

    private function totalRegistros(): int
    {
        return DB::table('registro_sintoma')->count() + DB::table('episodio_migrana')->count();
    }

    /**
     * Pide al ML que se reentrene con los datos de todos los usuarios.
     * Sin $forzar respeta las reglas (1 vez cada N horas y con suficientes registros nuevos).
     * Devuelve la fila creada o null si no tocaba.
     */
    public function iniciarReentreno(bool $forzar = false): ?object
    {
        if (DB::table('modelo_ml')->where('estado', 'en_curso')->exists()) {
            return null;
        }
        $total = $this->totalRegistros();
        if (! $forzar) {
            $ultimo = DB::table('modelo_ml')->orderByDesc('id_modelo')->first(['fecha_inicio', 'registros_usados']);
            $horas = (int) config('services.ml.reentrenar_horas', 24);
            if ($ultimo && Carbon::parse($ultimo->fecha_inicio)->gt(Carbon::now()->subHours($horas))) {
                return null;
            }
            $nuevos = $total - (int) optional($ultimo)->registros_usados;
            if ($nuevos < (int) config('services.ml.reentrenar_minimo', 20)) {
                return null;
            }
        }

        $pacientes = $this->datosParaEntrenar();
        if (! $pacientes) {
            throw new RuntimeException('Todavía no hay usuarios con registros para aprender.');
        }
        $this->llamar('/reentrenar', [
            'ahora'     => Carbon::now()->format('Y-m-d\TH:i:s'),
            'simulados' => max(200, (int) config('services.ml.simulados', 200)),
            'pacientes' => $pacientes,
        ]);
        $id = DB::table('modelo_ml')->insertGetId([
            'estado' => 'en_curso', 'registros_usados' => $total, 'pacientes_reales' => count($pacientes),
            'fecha_inicio' => Carbon::now()->format('Y-m-d H:i:s'),
        ], 'id_modelo');
        Log::info('Reentrenamiento del ML iniciado', ['id_modelo' => $id, 'personas' => count($pacientes)]);
        return DB::table('modelo_ml')->where('id_modelo', $id)->first();
    }

    /**
     * Si hay un reentrenamiento en curso, pregunta al ML cómo va. Si terminó y se aceptó,
     * descarga el modelo nuevo y lo guarda en la BD como activo. Devuelve la fila (o null).
     */
    public function revisarReentreno(bool $sinEsperar = false): ?object
    {
        $fila = DB::table('modelo_ml')->where('estado', 'en_curso')->orderByDesc('id_modelo')->first();
        if (! $fila) {
            return null;
        }
        $inicio = Carbon::parse($fila->fecha_inicio);
        if (! $sinEsperar && $inicio->gt(Carbon::now()->subMinute())) {
            return $fila;                                     // recién empezó: aún no vale la pena preguntar
        }

        $est = $this->llamar('/reentrenar/estado', null, 'get');
        $fin = Carbon::now()->format('Y-m-d H:i:s');
        $cerrar = fn (string $estado, string $motivo, array $extra = []) => DB::table('modelo_ml')
            ->where('id_modelo', $fila->id_modelo)
            ->update(['estado' => $estado, 'motivo' => mb_substr($motivo, 0, 255), 'fecha_fin' => $fin] + $extra);

        switch ($est['estado'] ?? '') {
            case 'en_curso':
                if ($inicio->lt(Carbon::now()->subMinutes(45))) {
                    $cerrar('error', 'El ML no terminó en 45 minutos (Render pudo reiniciarlo). Se intentará otro día.');
                }
                break;

            case 'terminado':
                $datos = [
                    'version' => $est['version'] ?? null,
                    'auc' => $est['auc_personas_nuevas'] ?? null, 'auc_anterior' => $est['auc_anterior'] ?? null,
                    'pacientes_reales' => $est['pacientes_reales'] ?? $fila->pacientes_reales,
                    'filas_reales' => $est['filas_reales'] ?? null,
                ];
                if (empty($est['aceptado'])) {
                    $cerrar('rechazado', $est['motivo'] ?? 'No pasó el filtro de calidad', $datos);
                    break;
                }
                $r = $this->http(60)->get(config('services.ml.url').'/modelo/descargar');
                $bin = $r->successful() ? $r->body() : '';
                $sha = hash('sha256', $bin);
                if (! $r->successful() || $bin === '' || ! hash_equals((string) $r->header('X-Sha256'), $sha)
                    || $r->header('X-Modelo-Version') !== $datos['version']) {
                    // El ML pudo reiniciarse justo después de entrenar: se intentará de nuevo otro día
                    $cerrar('error', 'No se pudo descargar el modelo aprendido (status '.$r->status().').', $datos);
                    break;
                }
                DB::transaction(function () use ($fila, $datos, $bin, $sha, $est, $fin) {
                    DB::table('modelo_ml')->where('estado', 'activo')->update(['estado' => 'reemplazado']);
                    DB::table('modelo_ml')->where('id_modelo', $fila->id_modelo)->update($datos + [
                        'estado' => 'activo', 'motivo' => mb_substr($est['motivo'] ?? 'Aceptado', 0, 255),
                        'archivo' => base64_encode($bin), 'sha256' => $sha, 'tamano_bytes' => strlen($bin),
                        'fecha_fin' => $fin,
                    ]);
                });
                Log::info('Nuevo modelo del ML activado', ['version' => $datos['version'], 'auc' => $datos['auc']]);
                break;

            case 'error':
                $cerrar('error', 'El ML falló al entrenar: '.($est['detalle'] ?? 'sin detalle'));
                break;

            default:   // "sin_iniciar": el ML se reinició y perdió el trabajo
                $cerrar('error', 'El ML se reinició antes de terminar. Se intentará otro día.');
        }

        return DB::table('modelo_ml')->where('id_modelo', $fila->id_modelo)->first();
    }

    /** Historial del aprendizaje (sin el archivo) */
    public function historialAprendizaje(int $limite = 20): array
    {
        if (! $this->aprendizajeDisponible()) {
            return [];
        }
        return DB::table('modelo_ml')->orderByDesc('id_modelo')->limit($limite)
            ->get(['id_modelo', 'version', 'estado', 'auc', 'auc_anterior', 'pacientes_reales', 'registros_usados', 'motivo', 'fecha_inicio', 'fecha_fin'])
            ->map(fn ($f) => (array) $f)->all();
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

    protected function llamar(string $ruta, ?array $cuerpo, string $metodo = 'post'): array
    {
        try {
            // Render gratis "duerme" el ML: mientras despierta responde 502/503/504 o no conecta.
            // Se reintenta solo esos casos, con pausas, hasta ~1 minuto en total.
            $despertando = fn ($e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && in_array($e->response->status(), [502, 503, 504], true));
            $http = $this->http((int) config('services.ml.timeout'))
                ->acceptJson()
                ->retry((int) config('services.ml.reintentos', 6), (int) config('services.ml.pausa_ms', 10000), $despertando, false);
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

    /** Cliente HTTP con la clave del ML (sin reintentos: para llamadas al ML ya despierto) */
    protected function http(int $timeout)
    {
        return Http::withHeaders(['X-API-Key' => (string) config('services.ml.key')])->timeout($timeout);
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
