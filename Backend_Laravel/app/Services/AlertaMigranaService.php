<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Alerta de posible migraña enviada por el SERVIDOR (llega con la app cerrada).
 *
 * Antes la alerta solo existía en el navegador: si MigraSense no estaba abierta, nadie
 * calculaba el riesgo y el aviso nunca salía. Ahora el motor de avisos (GitHub Actions o
 * el despertador) revisa a cada persona que tiene el aviso activado:
 *
 *  1. Pide la predicción al ML (el mismo modelo que usa la pantalla de Inicio).
 *  2. Si la probabilidad es muy alta (>= 70 %) y hay una señal reciente (un síntoma
 *     prodrómico en las últimas 72 h o el ritmo habitual de sus episodios), envía la
 *     notificación con la PROBABILIDAD y en CUÁNTAS HORAS podría empezar.
 *
 * Misma regla que js/analisis.js (alertaEpisodio), para que el aviso del celular y el
 * de la pantalla digan lo mismo.
 *
 * Sin tablas nuevas: usa envio_recordatorio.
 *   tipo 'alerta'     clave p<id del síntoma> o r<fecha>  → la alerta enviada (nunca dos veces)
 *   tipo 'alerta_rev' clave aammddHHii                    → cuándo se revisó a la persona
 */
class AlertaMigranaService
{
    public const HORAS_ENTRE_ALERTAS = 12;   // como máximo una alerta cada 12 h por persona
    public const DIAS_ACTIVIDAD = 60;        // solo se revisa a quien registró algo en este tiempo

    public function __construct(
        private PrediccionService $prediccion,
        private PushService $push,
        private CorreoService $correo,
    ) {
    }

    /** @return array{alertas:int, revisados:int, errores:int, estado:string} */
    public function ejecutar(?Carbon $ahora = null): array
    {
        $ahora = ($ahora ?? Carbon::now())->copy()->setTimezone(config('app.timezone'));
        $res = ['alertas' => 0, 'revisados' => 0, 'errores' => 0, 'estado' => 'ok'];

        if (! $this->prediccion->configurado()) {
            return ['estado' => 'el servicio de predicción no está configurado (ML_URL / ML_API_KEY)'] + $res;
        }
        if ($this->enSilencio($ahora)) {
            return ['estado' => 'horario de descanso: las alertas se envían al terminar'] + $res;
        }

        $cada = max(1, (int) config('services.avisos.alerta_cada_horas', 3));
        $desde = $ahora->copy()->subDays(self::DIAS_ACTIVIDAD)->toDateString();
        $usuarios = DB::table('usuario as u')
            ->where('u.activo', 1)->where('u.alerta_riesgo', 1)
            ->where(fn ($q) => $q
                ->whereExists(fn ($s) => $s->from('registro_sintoma as r')->whereColumn('r.id_usuario', 'u.id_usuario')->where('r.fecha', '>=', $desde))
                ->orWhereExists(fn ($s) => $s->from('episodio_migrana as e')->whereColumn('e.id_usuario', 'u.id_usuario')->where('e.fecha_inicio', '>=', $desde)))
            ->orderBy('u.id_usuario')
            ->get(['u.id_usuario', 'u.nombres', 'u.correo']);

        foreach ($usuarios as $u) {
            if (! $this->tocaRevisar((int) $u->id_usuario, $ahora, $cada)) {
                continue;
            }
            try {
                $r = $this->prediccion->predecir((int) $u->id_usuario);
            } catch (RuntimeException $e) {
                // El ML no despertó: no tiene sentido esperar un minuto por cada persona.
                // No se marca como revisada, así la siguiente vuelta (5 min) lo intenta de nuevo.
                $res['errores']++;
                $res['estado'] = 'el servicio de predicción no respondió; se reintenta en la próxima vuelta';
                break;
            } catch (Throwable $e) {
                $res['errores']++;
                Log::warning('Alerta de migraña del usuario '.$u->id_usuario.': '.$e->getMessage());
                continue;
            }
            $res['revisados']++;
            $this->marcarRevision((int) $u->id_usuario, $ahora);

            try {
                if ($this->avisar($u, $r, $ahora)) {
                    $res['alertas']++;
                }
            } catch (Throwable $e) {
                $res['errores']++;
                Log::warning('Alerta de migraña del usuario '.$u->id_usuario.': '.$e->getMessage());
            }
        }
        $this->limpiar($ahora);
        return $res;
    }

    /**
     * Decide si corresponde alertar con esta predicción y arma el aviso.
     * Devuelve null si no corresponde. Público para la prueba manual y las pruebas automáticas.
     *
     * @return array{clave:string, porcentaje:int, desde:int, hasta:int, titulo:string, cuerpo:string}|null
     */
    public function evaluar(int $idUsuario, array $prediccion, ?Carbon $ahora = null): ?array
    {
        $ahora = ($ahora ?? Carbon::now())->copy()->setTimezone(config('app.timezone'));
        $prob = (float) ($prediccion['probabilidad'] ?? 0);
        if ($prob < PrediccionService::UMBRAL_ALERTA) {
            return null;
        }
        $datos = $this->prediccion->datosPaciente($idUsuario, 365);
        $horasDesde = fn (string $f) => Carbon::parse($f, $ahora->getTimezone())->floatDiffInHours($ahora, false);

        // Síntomas prodrómicos de las últimas 72 h (el más reciente primero)
        $recientes = DB::table('registro_sintoma')->where('id_usuario', $idUsuario)
            ->where('fecha', '>=', $ahora->copy()->subDays(4)->toDateString())
            ->orderByDesc('fecha')->orderByDesc('hora')->get(['id_registro', 'fecha', 'hora'])
            ->map(fn ($x) => ['id' => (int) $x->id_registro, 'horas' => $horasDesde($x->fecha.' '.$x->hora)])
            ->filter(fn ($x) => $x['horas'] >= 0 && $x['horas'] <= 72)->values();

        $hayRitmo = collect($prediccion['factores'] ?? [])
            ->contains(fn ($f) => in_array($f['factor'] ?? '', ['ciclo', 'ritmo'], true) && (float) ($f['efecto_puntos'] ?? 0) > 0);
        if ($recientes->isEmpty() && ! $hayRitmo) {
            return null;   // probabilidad alta pero sin ninguna señal cercana: no se molesta
        }

        // ¿En cuántas horas? Según el tiempo que suele pasar entre su aviso y su dolor
        if ($recientes->isNotEmpty()) {
            $centro = self::horasTipicas($datos) ?? 12.0;   // sin historial suficiente: 12 h de referencia
            $margen = max(2.0, $centro * 0.4);
            $transcurridas = $recientes[0]['horas'];
            $desde = $centro - $margen - $transcurridas;
            $hasta = $centro + $margen - $transcurridas;
            $clave = 'p'.$recientes[0]['id'];
        } else {                                              // solo por el ritmo de sus episodios
            $desde = 6;
            $hasta = 24;
            $clave = 'r'.$ahora->toDateString();
        }
        $desde = max(0, (int) round($desde));
        $hasta = max($desde + 1, (int) round($hasta));
        if ($desde === 0 && $hasta <= 1) {
            $hasta = 2;
        }

        $pct = (int) round($prediccion['porcentaje'] ?? $prob * 100);
        $cuando = $desde === 0 ? "En las próximas {$hasta} horas" : "En {$desde} a {$hasta} horas";
        return [
            'clave' => $clave, 'porcentaje' => $pct, 'desde' => $desde, 'hasta' => $hasta,
            'titulo' => "Posible migraña · {$pct} % de probabilidad",
            'cuerpo' => "{$cuando} puede que tengas un episodio de migraña (probabilidad {$pct} %). Descansa, hidrátate y evita tus desencadenantes.",
        ];
    }

    /**
     * Mediana de horas entre un síntoma prodrómico y el episodio que le siguió (máx. 72 h).
     * null si hay menos de 2 casos. $datos = PrediccionService::datosPaciente()
     */
    public static function horasTipicas(array $datos): ?float
    {
        $eps = array_map(fn ($e) => strtotime($e['inicio']), $datos['episodios'] ?? []);
        $lapsos = [];
        foreach ($datos['registros'] ?? [] as $r) {
            $t = strtotime($r['fecha']);
            $sig = null;
            foreach ($eps as $e) {
                $h = ($e - $t) / 3600;
                if ($h > 0 && $h <= 72 && ($sig === null || $h < $sig)) {
                    $sig = $h;
                }
            }
            if ($sig !== null) {
                $lapsos[] = $sig;
            }
        }
        $n = count($lapsos);
        if ($n < 2) {
            return null;
        }
        sort($lapsos);
        $m = intdiv($n, 2);
        return $n % 2 ? $lapsos[$m] : ($lapsos[$m - 1] + $lapsos[$m]) / 2;
    }

    /** Aviso tal como llega al dispositivo (lo usa también la prueba manual) */
    public static function avisoPush(array $a): array
    {
        return ['title' => '⚠️ '.$a['titulo'], 'body' => $a['cuerpo'], 'url' => 'registro.html?tab=prodromico', 'tag' => 'migrasense-alerta'];
    }

    /* ---------------- apoyo ---------------- */

    private function avisar(object $u, array $prediccion, Carbon $ahora): bool
    {
        $a = $this->evaluar((int) $u->id_usuario, $prediccion, $ahora);
        if (! $a) {
            return false;
        }
        $hace = $ahora->copy()->subHours(self::HORAS_ENTRE_ALERTAS)->format('Y-m-d H:i:s');
        $reciente = DB::table('envio_recordatorio')->where('id_usuario', $u->id_usuario)->where('tipo', 'alerta')
            ->where('fecha_envio', '>=', $hace)->exists();
        if ($reciente) {
            return false;
        }
        // Se reserva antes de enviar: si otra vuelta ya lo hizo, no se repite
        $reservado = DB::table('envio_recordatorio')->insertOrIgnore([
            'id_usuario' => $u->id_usuario, 'tipo' => 'alerta', 'clave' => mb_substr($a['clave'], 0, 12),
            'resultado' => 'en curso', 'fecha_envio' => $ahora->format('Y-m-d H:i:s'),
        ]) === 1;
        if (! $reservado) {
            return false;
        }

        $n = $this->push->enviarAUsuario((int) $u->id_usuario, self::avisoPush($a), 'high', 6 * 3600);
        $canal = $n > 0 ? "push a {$n} dispositivo(s)" : $this->porCorreo($u, $a);
        DB::table('envio_recordatorio')->where(['id_usuario' => $u->id_usuario, 'tipo' => 'alerta', 'clave' => mb_substr($a['clave'], 0, 12)])
            ->update(['resultado' => mb_substr("{$a['porcentaje']} % · ".($canal ?: 'sin canal disponible'), 0, 255)]);
        return true;
    }

    /** Sin dispositivos con avisos: la alerta llega por correo */
    private function porCorreo(object $u, array $a): ?string
    {
        if (! $this->correo->configurado() && ! app()->environment('local')) {
            return null;
        }
        $url = (string) config('services.correo.frontend_url');
        $nombre = strtok((string) $u->nombres, ' ') ?: '';
        $hola = $nombre !== '' ? 'Hola, '.e($nombre).':' : 'Hola:';
        $html = '<div style="max-width:480px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;color:#18212f">'
            .'<p style="font-size:20px;font-weight:bold;color:#1f3f99">MigraSense</p><p>'.$hola.'</p>'
            .'<p><b>'.e($a['titulo']).'</b></p><p>'.e($a['cuerpo']).'</p>'
            .'<p><a href="'.e($url).'/registro.html?tab=prodromico" style="background:#2f5bd3;color:#fff;text-decoration:none;padding:12px 20px;border-radius:10px;font-weight:bold;display:inline-block">Registrar cómo me siento</a></p>'
            .'<p style="color:#4f5b6c;font-size:13px">Es una estimación, no un diagnóstico médico. Puedes apagar este aviso en Perfil → Recordatorios.</p></div>';
        $texto = ($nombre !== '' ? "Hola, {$nombre}:" : 'Hola:')."\n\n{$a['titulo']}\n{$a['cuerpo']}\n\n{$url}/registro.html?tab=prodromico";
        try {
            $this->correo->enviar((string) $u->correo, (string) $u->nombres, $a['titulo'].' · MigraSense', $html, $texto);
            return 'correo';
        } catch (Throwable $e) {
            Log::warning('Alerta por correo al usuario '.$u->id_usuario.': '.$e->getMessage());
            return 'correo falló';
        }
    }

    /** Se revisa cada N horas, o antes si la persona registró algo después de la última revisión */
    private function tocaRevisar(int $idUsuario, Carbon $ahora, int $cadaHoras): bool
    {
        $ultima = DB::table('envio_recordatorio')->where('id_usuario', $idUsuario)->where('tipo', 'alerta_rev')->max('fecha_envio');
        if (! $ultima) {
            return true;
        }
        if (Carbon::parse($ultima, $ahora->getTimezone())->lte($ahora->copy()->subHours($cadaHoras))) {
            return true;
        }
        return DB::table('registro_sintoma')->where('id_usuario', $idUsuario)->where('fecha_creacion', '>', $ultima)->exists()
            || DB::table('episodio_migrana')->where('id_usuario', $idUsuario)->where('fecha_creacion', '>', $ultima)->exists();
    }

    private function marcarRevision(int $idUsuario, Carbon $ahora): void
    {
        DB::table('envio_recordatorio')->insertOrIgnore([
            'id_usuario' => $idUsuario, 'tipo' => 'alerta_rev', 'clave' => $ahora->format('ymdHi'),
            'resultado' => null, 'fecha_envio' => $ahora->format('Y-m-d H:i:s'),
        ]);
    }

    /** Las marcas de revisión viejas no sirven: se borran para que la tabla no crezca */
    private function limpiar(Carbon $ahora): void
    {
        try {
            DB::table('envio_recordatorio')->where('tipo', 'alerta_rev')
                ->where('fecha_envio', '<', $ahora->copy()->subDays(3)->format('Y-m-d H:i:s'))->delete();
        } catch (Throwable $e) {
            Log::warning('No se pudieron limpiar las marcas de revisión: '.$e->getMessage());
        }
    }

    /** Horario de descanso "23-6" (de 23:00 a 05:59 no se envían alertas). Vacío = sin descanso. */
    private function enSilencio(Carbon $ahora): bool
    {
        if (! preg_match('/^(\d{1,2})-(\d{1,2})$/', (string) config('services.avisos.silencio', '23-6'), $m)) {
            return false;
        }
        [$ini, $fin, $h] = [(int) $m[1] % 24, (int) $m[2] % 24, $ahora->hour];
        return $ini === $fin ? false : ($ini < $fin ? ($h >= $ini && $h < $fin) : ($h >= $ini || $h < $fin));
    }
}
