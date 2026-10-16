<?php

namespace App\Console\Commands;

use App\Support\WebPush;
use Illuminate\Console\Command;

/**
 * Genera las claves para los avisos al dispositivo y la clave del despertador.
 *   php artisan migrasense:claves-push
 * Se hace UNA sola vez: si se cambian, los dispositivos tienen que volver a activar los avisos.
 */
class ClavesPush extends Command
{
    protected $signature = 'migrasense:claves-push';

    protected $description = 'Genera VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY y CRON_CLAVE para los recordatorios';

    public function handle(): int
    {
        if (config('services.push.publica') !== '') {
            $this->warn('Ya hay claves VAPID configuradas. Si las cambias, cada persona tendrá que volver a activar los avisos.');
        }
        $c = WebPush::generarClaves();
        $this->line('Copia estas 3 líneas en tu .env y en Render → Environment (son secretas, no las subas a GitHub):');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$c['publica']);
        $this->line('VAPID_PRIVATE_KEY="'.$c['privada'].'"');
        $this->line('CRON_CLAVE="'.bin2hex(random_bytes(24)).'"');
        $this->newLine();
        $this->line('Y además VAPID_SUBJECT="mailto:el_correo_del_equipo@gmail.com"');
        return self::SUCCESS;
    }
}
