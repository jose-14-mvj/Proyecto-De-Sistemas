<?php

namespace App\Console\Commands;

use App\Services\CorreoService;
use App\Services\RecuperacionClaveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Diagnóstico del envío de correos (recuperar contraseña).
 *
 *   php artisan migrasense:probar-correo tu@correo.com            -> estado + envía un correo de prueba
 *   php artisan migrasense:probar-correo tu@correo.com --sin-enviar
 *
 * Usa la misma configuración del .env (o de Render, si se corre allí). No muestra claves.
 */
class ProbarCorreo extends Command
{
    protected $signature = 'migrasense:probar-correo {correo : A quién enviar la prueba} {--sin-enviar : Solo muestra el estado}';

    protected $description = 'Muestra cómo se envían los correos, los límites de recuperación y manda un correo de prueba';

    public function handle(CorreoService $correo): int
    {
        $para = mb_strtolower(trim((string) $this->argument('correo')));

        $prov = $correo->proveedores();
        $this->line('Proveedor de correo: '.($prov ? implode(' → si falla: ', $prov) : 'NINGUNO configurado (en local solo se escribe en storage/logs/laravel.log)'));

        $u = DB::table('usuario')->where('correo', $para)->first(['id_usuario', 'activo']);
        if (! $u) {
            $this->warn('Ese correo NO tiene cuenta en MigraSense: "¿Olvidaste tu contraseña?" no le envía nada (a propósito, por seguridad).');
        } else {
            $hoy = DB::table('recuperacion_clave')->where('id_usuario', $u->id_usuario)
                ->where('fecha_creacion', '>=', now()->subDay()->format('Y-m-d H:i:s'))->count();
            $ultimo = DB::table('recuperacion_clave')->where('id_usuario', $u->id_usuario)->max('fecha_creacion');
            $this->line("Cuenta encontrada (activa: ".($u->activo ? 'sí' : 'NO').").");
            $this->line("Códigos pedidos en las últimas 24 h: {$hoy} de ".RecuperacionClaveService::MAX_CODIGOS_DIA.($ultimo ? " · último: {$ultimo}" : ''));
            if ($hoy >= RecuperacionClaveService::MAX_CODIGOS_DIA) {
                $this->warn('LÍMITE ALCANZADO: hasta que pasen 24 h desde el primero, no se envían más códigos a esta cuenta.');
            }
        }

        if ($this->option('sin-enviar')) {
            return self::SUCCESS;
        }

        $this->line('Enviando un correo de prueba a '.$para.'…');
        try {
            $correo->enviar($para, 'Prueba', 'Prueba de correo de MigraSense',
                '<p>Si lees esto, el envío de correos de MigraSense funciona.</p>',
                'Si lees esto, el envío de correos de MigraSense funciona.');
            $this->info($prov
                ? 'El proveedor ACEPTÓ el correo. Si no llega en 2 minutos, revisa spam o el panel del proveedor.'
                : 'Correo SIMULADO: está en storage/logs/laravel.log (no hay proveedor configurado).');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('NO se pudo enviar: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
