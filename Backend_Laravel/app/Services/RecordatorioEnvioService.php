<?php

namespace App\Services;

use App\Support\ReporteSemanalPdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envía los recordatorios a la hora que cada persona eligió en su perfil.
 * Lo ejecuta el despertador externo (cron-job.org → POST /api/tareas/recordatorios)
 * cada 15 minutos; también se puede correr a mano: php artisan migrasense:enviar-recordatorios
 *
 * - Diario: notificación "¿Cómo te fue hoy?" a sus dispositivos. Si no tiene ninguno con
 *   avisos activados, le llega por correo. Si ya registró algo hoy, no se le molesta.
 * - Semanal: correo con el PDF de los últimos 7 días + notificación.
 * Cada aviso sale una sola vez por día/semana (tabla envio_recordatorio), aunque el
 * despertador llame varias veces o dos llamadas coincidan.
 */
class RecordatorioEnvioService
{
    public function __construct(
        private PushService $push,
        private CorreoService $correo,
        private RegistroService $registros,
    ) {
    }

    public function ejecutar(?Carbon $ahora = null): array
    {
        $ahora = ($ahora ?? Carbon::now())->copy()->setTimezone(config('app.timezone'));
        $ventana = max(1, (int) config('services.cron.ventana_horas', 3)) * 60;
        $res = ['diarios' => 0, 'semanales' => 0, 'omitidos' => 0, 'errores' => 0];

        $filas = DB::table('recordatorio as r')
            ->join('usuario as u', 'u.id_usuario', '=', 'r.id_usuario')
            ->where('u.activo', 1)->where('r.activo', 1)
            ->orderBy('r.id_usuario')
            ->get(['r.id_usuario', 'r.frecuencia', 'r.dia_semana', 'r.hora_configurada', 'u.nombres', 'u.correo']);

        foreach ($filas as $f) {
            $hora = Carbon::parse($ahora->toDateString().' '.$f->hora_configurada, $ahora->getTimezone());
            if ($ahora->lt($hora) || $hora->diffInMinutes($ahora) > $ventana) {
                continue;   // todavía no es la hora, o ya pasó demasiado tiempo
            }
            try {
                if ($f->frecuencia === 'diaria') {
                    $hecho = $this->diario($f, $ahora);
                    $hecho === 'omitido' ? $res['omitidos']++ : ($hecho ? $res['diarios']++ : null);
                } elseif ($f->frecuencia === 'semanal' && (int) $f->dia_semana === $ahora->dayOfWeek) {
                    $this->semanal($f, $ahora) && $res['semanales']++;
                }
            } catch (Throwable $e) {
                $res['errores']++;
                Log::warning('Recordatorio de usuario '.$f->id_usuario.' ('.$f->frecuencia.'): '.$e->getMessage());
            }
        }
        return $res;
    }

    /** true = enviado, 'omitido' = ya había registrado hoy, false = ya se había enviado */
    private function diario(object $f, Carbon $ahora): bool|string
    {
        if (! $this->reservar($f->id_usuario, 'diario', $ahora->toDateString())) {
            return false;
        }
        if ($this->registroHoy($f->id_usuario, $ahora)) {
            $this->resultado($f->id_usuario, 'diario', $ahora->toDateString(), 'omitido: ya registró hoy');
            return 'omitido';
        }

        $nombre = strtok((string) $f->nombres, ' ') ?: '';
        $dispositivos = $this->push->enviarAUsuario($f->id_usuario, [
            'title' => '¿Cómo te fue hoy?',
            'body'  => 'Toma un minuto para anotar tus síntomas. Así MigraSense puede anticipar tu próxima migraña.',
            'url'   => 'registro.html',
            'tag'   => 'recordatorio-diario',
        ]);

        $canal = $dispositivos > 0 ? "push a {$dispositivos} dispositivo(s)" : null;
        if ($dispositivos === 0) {
            // Sin dispositivos con avisos: le llega por correo para que no se pierda
            $canal = $this->enviarCorreo($f, ...$this->correoDiario($nombre, substr($f->hora_configurada, 0, 5)));
        }
        $this->resultado($f->id_usuario, 'diario', $ahora->toDateString(), $canal);
        return true;
    }

    private function semanal(object $f, Carbon $ahora): bool
    {
        $clave = $ahora->format('o-\WW');
        if (! $this->reservar($f->id_usuario, 'semanal', $clave)) {
            return false;
        }

        $desde = $ahora->copy()->subDays(6)->startOfDay();
        $regs = array_values(array_filter(
            $this->registros->listar($f->id_usuario, 'todos', 8, 2000),
            fn ($r) => Carbon::parse($r['date'], $ahora->getTimezone())->gte($desde)
        ));
        $ep = count(array_filter($regs, fn ($r) => $r['type'] === 'episodio'));
        $se = count($regs) - $ep;
        $pdf = ReporteSemanalPdf::generar((string) $f->nombres, $desde, $ahora, $regs);
        $periodo = $this->fechaCorta($desde).' al '.$this->fechaCorta($ahora);

        [$asunto, $html, $texto] = $this->correoSemanal(strtok((string) $f->nombres, ' ') ?: '', $periodo, $ep, $se);
        $canales = [$this->enviarCorreo($f, $asunto, $html, $texto, [[
            'nombre' => 'MigraSense-resumen-'.$ahora->format('Y-m-d').'.pdf', 'tipo' => 'application/pdf', 'contenido' => $pdf,
        ]])];

        $resumen = $regs ? "Esta semana: {$ep} ".($ep === 1 ? 'migraña' : 'migrañas')." y {$se} ".($se === 1 ? 'señal' : 'señales').'.' : 'Esta semana no hay registros.';
        $n = $this->push->enviarAUsuario($f->id_usuario, [
            'title' => 'Tu resumen semanal está listo',
            'body'  => $resumen.' Te enviamos el PDF a tu correo.',
            'url'   => 'historial.html?reporte=1',
            'tag'   => 'resumen-semanal',
        ]);
        if ($n > 0) {
            $canales[] = "push a {$n} dispositivo(s)";
        }
        $this->resultado($f->id_usuario, 'semanal', $clave, implode(' + ', array_filter($canales)));
        return true;
    }

    /* ---------------- apoyo ---------------- */

    /** Marca el envío antes de hacerlo: si otra llamada ya lo marcó, devuelve false (no se repite). */
    private function reservar(int $idUsuario, string $tipo, string $clave): bool
    {
        return DB::table('envio_recordatorio')->insertOrIgnore([
            'id_usuario' => $idUsuario, 'tipo' => $tipo, 'clave' => $clave,
            'resultado' => 'en curso', 'fecha_envio' => Carbon::now()->format('Y-m-d H:i:s'),
        ]) === 1;
    }

    private function resultado(int $idUsuario, string $tipo, string $clave, ?string $texto): void
    {
        DB::table('envio_recordatorio')->where(['id_usuario' => $idUsuario, 'tipo' => $tipo, 'clave' => $clave])
            ->update(['resultado' => mb_substr($texto ?: 'sin canal disponible', 0, 255)]);
    }

    private function registroHoy(int $idUsuario, Carbon $ahora): bool
    {
        $hoy = $ahora->toDateString();
        $inicio = $ahora->copy()->startOfDay()->format('Y-m-d H:i:s');
        return DB::table('registro_sintoma')->where('id_usuario', $idUsuario)
                ->where(fn ($q) => $q->where('fecha', $hoy)->orWhere('fecha_creacion', '>=', $inicio))->exists()
            || DB::table('episodio_migrana')->where('id_usuario', $idUsuario)
                ->where(fn ($q) => $q->where('fecha_inicio', $hoy)->orWhere('fecha_creacion', '>=', $inicio))->exists();
    }

    /** Envía el correo si hay proveedor; devuelve el texto del resultado */
    private function enviarCorreo(object $f, string $asunto, string $html, string $texto, array $adjuntos = []): ?string
    {
        if (! $this->correo->configurado() && ! app()->environment('local')) {   // en local se simula (laravel.log)
            return null;
        }
        try {
            $this->correo->enviar((string) $f->correo, (string) $f->nombres, $asunto, $html, $texto, $adjuntos);
            return 'correo'.($adjuntos ? ' con PDF' : '');
        } catch (Throwable $e) {
            Log::warning('Recordatorio por correo al usuario '.$f->id_usuario.': '.$e->getMessage());
            return 'correo falló: '.mb_substr($e->getMessage(), 0, 120);
        }
    }

    private function correoDiario(string $nombre, string $hora): array
    {
        $url = (string) config('services.correo.frontend_url');
        $hola = $nombre !== '' ? 'Hola, '.e($nombre).':' : 'Hola:';
        $html = $this->plantilla($hola,
            '<p style="margin:0 0 16px">Toma un minuto para anotar cómo te sentiste hoy: las señales previas (bostezos, cansancio, sensibilidad a la luz…) y si tuviste migraña. Cada registro ayuda a anticipar la próxima.</p>',
            $url.'/registro.html', 'Registrar ahora',
            "Recibes este recordatorio porque está activado en tu perfil (todos los días a las {$hora}). Puedes cambiar la hora o apagarlo en Perfil → Recordatorios.");
        $texto = ($nombre !== '' ? "Hola, {$nombre}:" : 'Hola:')."\n\nToma un minuto para anotar cómo te sentiste hoy en MigraSense: {$url}/registro.html\n\n"
            ."Recibes este recordatorio porque está activado en tu perfil (todos los días a las {$hora}).";
        return ['¿Cómo te fue hoy? · MigraSense', $html, $texto];
    }

    private function correoSemanal(string $nombre, string $periodo, int $ep, int $se): array
    {
        $url = (string) config('services.correo.frontend_url');
        $hola = $nombre !== '' ? 'Hola, '.e($nombre).':' : 'Hola:';
        $cuerpo = $ep + $se > 0
            ? "<p style=\"margin:0 0 16px\">Esta semana registraste <b>{$ep} ".($ep === 1 ? 'migraña' : 'migrañas')."</b> y <b>{$se} ".($se === 1 ? 'señal previa' : 'señales previas').'</b>. Te adjuntamos el resumen en PDF: puedes guardarlo o mostrárselo a tu médico.</p>'
            : '<p style="margin:0 0 16px">Esta semana no hay registros. Te adjuntamos el resumen igualmente. Recuerda que anotar las señales previas ayuda a que la predicción sea más precisa.</p>';
        $html = $this->plantilla($hola, $cuerpo, $url.'/historial.html', 'Ver mi historial',
            'Recibes este resumen porque está activado en tu perfil. Puedes cambiar el día y la hora, o apagarlo, en Perfil → Recordatorios.');
        $texto = ($nombre !== '' ? "Hola, {$nombre}:" : 'Hola:')."\n\nTu resumen semanal de MigraSense ({$periodo}): {$ep} migraña(s) y {$se} señal(es) previa(s). "
            ."El PDF va adjunto.\n\nVer historial: {$url}/historial.html";
        return ["Tu resumen semanal de MigraSense ({$periodo})", $html, $texto];
    }

    private function plantilla(string $hola, string $cuerpo, string $enlace, string $boton, string $pie): string
    {
        $enlace = e($enlace);
        return <<<HTML
<!doctype html>
<html lang="es"><body style="margin:0;padding:24px;background:#f2f4f7;font-family:Arial,Helvetica,sans-serif;color:#18212f">
  <div style="max-width:480px;margin:0 auto;background:#ffffff;border:1px solid #e2e6ec;border-radius:14px;padding:28px">
    <p style="margin:0 0 4px;font-size:20px;font-weight:bold;color:#1f3f99">MigraSense</p>
    <p style="margin:16px 0">{$hola}</p>
    {$cuerpo}
    <p style="margin:24px 0"><a href="{$enlace}" style="background:#2f5bd3;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:10px;font-weight:bold;display:inline-block">{$boton}</a></p>
    <p style="margin:0;color:#4f5b6c;font-size:13px">{$pie}</p>
  </div>
</body></html>
HTML;
    }

    private function fechaCorta(Carbon $f): string
    {
        $m = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        return $f->day.' '.$m[$f->month - 1];
    }
}
