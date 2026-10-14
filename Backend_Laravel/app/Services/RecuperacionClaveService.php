<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * "¿Olvidaste tu contraseña?" con un código de 6 dígitos enviado por correo.
 *
 * Seguridad:
 * - El código se guarda solo como huella HMAC-SHA256 (con APP_KEY): si alguien lee la BD no lo ve.
 * - Vence a los 15 min, sirve una vez y se anula tras 5 intentos fallidos.
 * - Pedir un código nuevo anula el anterior. Máx. 1 código por minuto y 5 por día por cuenta.
 * - La respuesta es la MISMA exista o no el correo (no revela quién está registrado).
 * - Al cambiar la clave se cierran TODAS las sesiones abiertas.
 */
class RecuperacionClaveService
{
    public const MINUTOS_VALIDEZ = 15;
    public const MAX_INTENTOS = 5;
    public const SEGUNDOS_ENTRE_CODIGOS = 60;
    public const MAX_CODIGOS_DIA = 5;

    public function __construct(private CorreoService $correo)
    {
    }

    /** Crea un código y lo envía, si el correo pertenece a una cuenta activa. No revela nada. */
    public function solicitar(string $correo, ?string $ip): void
    {
        $usuario = Usuario::where('correo', $correo)->where('activo', 1)->first();
        if (! $usuario) {
            return;
        }

        $ultimos = DB::table('recuperacion_clave')
            ->where('id_usuario', $usuario->id_usuario)
            ->where('fecha_creacion', '>=', now()->subDay()->format('Y-m-d H:i:s'))
            ->orderByDesc('id_recuperacion')
            ->get(['fecha_creacion']);

        if ($ultimos->count() >= self::MAX_CODIGOS_DIA) {
            return;
        }
        if ($ultimos->isNotEmpty()
            && now()->diffInSeconds(\Carbon\Carbon::parse($ultimos->first()->fecha_creacion), true) < self::SEGUNDOS_ENTRE_CODIGOS) {
            return;   // el anterior acaba de salir: evita llenar la bandeja de la persona
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($usuario, $codigo, $ip) {
            // Solo vale el último código pedido
            DB::table('recuperacion_clave')
                ->where('id_usuario', $usuario->id_usuario)
                ->whereNull('fecha_uso')
                ->update(['fecha_uso' => now()->format('Y-m-d H:i:s')]);

            DB::table('recuperacion_clave')->insert([
                'id_usuario'     => $usuario->id_usuario,
                'codigo_hash'    => $this->huella($codigo),
                'intentos'       => 0,
                'fecha_creacion' => now()->format('Y-m-d H:i:s'),
                'fecha_expira'   => now()->addMinutes(self::MINUTOS_VALIDEZ)->format('Y-m-d H:i:s'),
                'ip'             => $ip ? mb_substr($ip, 0, 45) : null,
            ]);
        });

        try {
            [$asunto, $html, $texto] = $this->mensaje($usuario->nombres, $codigo);
            $this->correo->enviar($usuario->correo, (string) $usuario->nombres, $asunto, $html, $texto);
        } catch (\Throwable $e) {
            // Nunca se registra el código ni el correo completo
            Log::warning('Recuperación de clave: no se pudo enviar el correo al usuario '.$usuario->id_usuario.'. '.$e->getMessage());
        }
    }

    /**
     * Cambia la contraseña si el código es correcto. Devuelve false si el código
     * es incorrecto, venció, ya se usó o se agotaron los intentos (mismo error para todo).
     */
    public function restablecer(string $correo, string $codigo, string $clave): bool
    {
        $usuario = Usuario::where('correo', $correo)->where('activo', 1)->first();
        $fila = $usuario
            ? DB::table('recuperacion_clave')
                ->where('id_usuario', $usuario->id_usuario)
                ->whereNull('fecha_uso')
                ->orderByDesc('id_recuperacion')
                ->first()
            : null;

        $huella = $this->huella($codigo);   // se calcula siempre (tiempo parecido exista o no la cuenta)

        if (! $fila
            || $fila->intentos >= self::MAX_INTENTOS
            || now()->gt(\Carbon\Carbon::parse($fila->fecha_expira))) {
            return false;
        }

        if (! hash_equals($fila->codigo_hash, $huella)) {
            $intentos = $fila->intentos + 1;
            DB::table('recuperacion_clave')->where('id_recuperacion', $fila->id_recuperacion)->update([
                'intentos'  => $intentos,
                // Al 5.º fallo el código queda anulado: hay que pedir otro
                'fecha_uso' => $intentos >= self::MAX_INTENTOS ? now()->format('Y-m-d H:i:s') : null,
            ]);
            return false;
        }

        try {
            $this->aplicarNuevaClave($usuario, $fila->id_recuperacion, $clave);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'codigo_ya_usado') {
                return false;
            }
            throw $e;
        }

        return true;
    }

    private function aplicarNuevaClave(Usuario $usuario, int $idRecuperacion, string $clave): void
    {
        DB::transaction(function () use ($usuario, $idRecuperacion, $clave) {
            // Se marca usado solo si nadie lo usó en paralelo (evita doble uso)
            $marcado = DB::table('recuperacion_clave')
                ->where('id_recuperacion', $idRecuperacion)
                ->whereNull('fecha_uso')
                ->update(['fecha_uso' => now()->format('Y-m-d H:i:s')]);
            if ($marcado !== 1) {
                throw new \RuntimeException('codigo_ya_usado');
            }

            $usuario->contrasena = Hash::make($clave);   // Argon2id
            $usuario->save();
            $usuario->tokens()->delete();                 // cierra todas las sesiones abiertas
        });
    }

    /** Huella del código: HMAC-SHA256 con la APP_KEY (no se puede revertir ni probar sin la clave). */
    private function huella(string $codigo): string
    {
        return hash_hmac('sha256', $codigo, (string) config('app.key'));
    }

    /** Asunto, HTML y texto del correo. */
    private function mensaje(?string $nombre, string $codigo): array
    {
        $min = self::MINUTOS_VALIDEZ;
        $hola = $nombre ? 'Hola, '.e(strtok($nombre, ' ')).':' : 'Hola:';
        $holaTxt = $nombre ? 'Hola, '.strtok($nombre, ' ').':' : 'Hola:';
        $url = e((string) config('services.correo.frontend_url'));
        $enlace = $url !== ''
            ? '<p style="margin:0 0 16px">Escríbelo en la pantalla <a href="'.$url.'/recuperar.html" style="color:#4a5c82">Recuperar contraseña</a> de MigraSense.</p>'
            : '<p style="margin:0 0 16px">Escríbelo en la pantalla <b>Recuperar contraseña</b> de MigraSense.</p>';

        $asunto = "Tu código de MigraSense: {$codigo}";

        $html = <<<HTML
<!doctype html>
<html lang="es"><body style="margin:0;padding:24px;background:#f3f5fa;font-family:Arial,Helvetica,sans-serif;color:#1f2a44">
  <div style="max-width:460px;margin:0 auto;background:#ffffff;border-radius:14px;padding:28px">
    <p style="margin:0 0 4px;font-size:20px;font-weight:bold;color:#4a5c82">MigraSense</p>
    <p style="margin:16px 0">{$hola}</p>
    <p style="margin:0 0 16px">Recibimos un pedido para cambiar la contraseña de tu cuenta. Tu código es:</p>
    <p style="margin:0 0 16px;font-size:34px;letter-spacing:8px;font-weight:bold;text-align:center;background:#eef1f8;border-radius:10px;padding:14px 0">{$codigo}</p>
    {$enlace}
    <p style="margin:0 0 16px;color:#5b6478;font-size:14px">Vence en {$min} minutos y sirve una sola vez. Nadie de MigraSense te lo va a pedir.</p>
    <p style="margin:0;color:#5b6478;font-size:14px">Si no fuiste tú, ignora este correo: tu contraseña sigue igual.</p>
  </div>
</body></html>
HTML;

        $texto = "{$holaTxt}\n\nRecibimos un pedido para cambiar la contraseña de tu cuenta de MigraSense.\n\n"
            ."Tu código es: {$codigo}\n\nVence en {$min} minutos y sirve una sola vez. Nadie de MigraSense te lo va a pedir.\n\n"
            ."Si no fuiste tú, ignora este correo: tu contraseña sigue igual.\n";

        return [$asunto, $html, $texto];
    }
}
