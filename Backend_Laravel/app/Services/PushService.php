<?php

namespace App\Services;

use App\Support\WebPush;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Suscripciones Web Push de cada persona y envío de avisos a todos sus dispositivos.
 */
class PushService
{
    public function configurado(): bool
    {
        return config('services.push.publica') !== '' && config('services.push.privada') !== '';
    }

    public function clavePublica(): string
    {
        return (string) config('services.push.publica');
    }

    /** Guarda (o actualiza) la suscripción de un navegador. Si era de otra cuenta, pasa a esta. */
    public function suscribir(int $idUsuario, string $endpoint, string $p256dh, string $auth, ?string $agente): void
    {
        $hash = hash('sha256', $endpoint);
        $datos = ['id_usuario' => $idUsuario, 'endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth,
                  'agente' => $agente ? mb_substr($agente, 0, 160) : null];
        if (DB::table('suscripcion_push')->where('endpoint_hash', $hash)->exists()) {
            DB::table('suscripcion_push')->where('endpoint_hash', $hash)->update($datos);
        } else {
            DB::table('suscripcion_push')->insert($datos + ['endpoint_hash' => $hash, 'fecha_creacion' => now()->format('Y-m-d H:i:s')]);
        }
    }

    /** Quita la suscripción de un navegador (solo si es de esta cuenta). */
    public function quitar(int $idUsuario, string $endpoint): void
    {
        DB::table('suscripcion_push')->where('id_usuario', $idUsuario)->where('endpoint_hash', hash('sha256', $endpoint))->delete();
    }

    public function cantidad(int $idUsuario): int
    {
        return DB::table('suscripcion_push')->where('id_usuario', $idUsuario)->count();
    }

    /**
     * Envía un aviso a todos los dispositivos de la persona.
     * $aviso = ['title' => …, 'body' => …, 'url' => 'registro.html', 'tag' => …]
     * Devuelve cuántos dispositivos lo aceptaron. Las suscripciones vencidas (404/410) se borran.
     */
    public function enviarAUsuario(int $idUsuario, array $aviso): int
    {
        if (! $this->configurado()) {
            return 0;
        }
        $wp = new WebPush($this->clavePublica(), (string) config('services.push.privada'), (string) config('services.push.sujeto'));
        $mensaje = json_encode($aviso, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ok = 0;
        foreach (DB::table('suscripcion_push')->where('id_usuario', $idUsuario)->get() as $s) {
            try {
                $status = $wp->enviar(['endpoint' => $s->endpoint, 'p256dh' => $s->p256dh, 'auth' => $s->auth], $mensaje);
            } catch (Throwable $e) {
                Log::warning('Push: no se pudo enviar a la suscripción '.$s->id_suscripcion.': '.$e->getMessage());
                continue;
            }
            if ($status >= 200 && $status < 300) {
                $ok++;
                DB::table('suscripcion_push')->where('id_suscripcion', $s->id_suscripcion)->update(['ultimo_envio' => Carbon::now()->format('Y-m-d H:i:s')]);
            } elseif (in_array($status, [404, 410], true)) {
                // El navegador anuló el permiso o se desinstaló: ya no sirve
                DB::table('suscripcion_push')->where('id_suscripcion', $s->id_suscripcion)->delete();
            } else {
                Log::warning('Push: el servicio respondió '.$status.' para la suscripción '.$s->id_suscripcion);
            }
        }
        return $ok;
    }
}
