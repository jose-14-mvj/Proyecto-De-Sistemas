<?php

namespace App\Console\Commands;

use App\Services\PrediccionService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Envía los datos reales (anónimos) de la BD al servicio de ML para que se reentrene.
 *
 *   php artisan migrasense:ml-reentrenar            -> inicia el reentrenamiento
 *   php artisan migrasense:ml-reentrenar --estado   -> ver cómo va / resultado
 */
class ReentrenarMl extends Command
{
    protected $signature = 'migrasense:ml-reentrenar
                            {--estado : Solo consulta el estado del último reentrenamiento}
                            {--simulados=800 : Pacientes simulados (base de estudios) que se mezclan con los reales}';

    protected $description = 'Reentrena el modelo de ML con los datos reales (anónimos) de la base de datos';

    public function handle(PrediccionService $ml): int
    {
        try {
            if ($this->option('estado')) {
                $this->line(json_encode($ml->estadoReentreno(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return self::SUCCESS;
            }

            $n = count($ml->datosParaEntrenar());
            $this->info("Enviando datos de $n persona(s) con registros (sin nombres ni correos)...");
            $r = $ml->reentrenar((int) $this->option('simulados'));
            $this->info($r['mensaje'] ?? 'Enviado.');
            $this->line('Revisa el resultado en 1-5 minutos con: php artisan migrasense:ml-reentrenar --estado');
            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
