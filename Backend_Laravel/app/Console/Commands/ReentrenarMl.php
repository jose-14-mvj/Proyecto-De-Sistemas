<?php

namespace App\Console\Commands;

use App\Services\PrediccionService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Aprendizaje del modelo de ML con los datos reales (anónimos) de la base de datos.
 *
 *   php artisan migrasense:ml-reentrenar            -> empieza a reentrenar YA (sin esperar las 24 h)
 *   php artisan migrasense:ml-reentrenar --estado   -> cómo va; si terminó, guarda el modelo en la BD
 *
 * Normalmente no hace falta: la API lo hace sola (máx. 1 vez al día, con 20 registros nuevos).
 */
class ReentrenarMl extends Command
{
    protected $signature = 'migrasense:ml-reentrenar
                            {--estado : Revisa el reentrenamiento en curso y muestra el historial de aprendizaje}';

    protected $description = 'Reentrena el modelo de ML con los datos reales (anónimos) y guarda lo aprendido en la BD';

    public function handle(PrediccionService $ml): int
    {
        if (! $ml->configurado()) {
            $this->error('Falta ML_URL o ML_API_KEY en el .env');
            return self::FAILURE;
        }
        if (! $ml->aprendizajeDisponible()) {
            $this->error('Falta la tabla modelo_ml. Corre primero: php artisan migrate --database=mysql_admin');
            return self::FAILURE;
        }

        try {
            if ($this->option('estado')) {
                $fila = $ml->revisarReentreno(true);
                if ($fila) {
                    $this->info("Último reentrenamiento (#{$fila->id_modelo}): {$fila->estado}".($fila->motivo ? " — {$fila->motivo}" : ''));
                    if ($fila->estado === 'en_curso') {
                        $this->line('Sigue entrenando. Vuelve a revisar en 1-2 minutos.');
                    }
                }
                $this->mostrarHistorial($ml);
                return self::SUCCESS;
            }

            $fila = $ml->iniciarReentreno(true);
            if (! $fila) {
                $this->warn('Ya hay un reentrenamiento en curso. Revisa con: php artisan migrasense:ml-reentrenar --estado');
                return self::SUCCESS;
            }
            $this->info("Reentrenamiento #{$fila->id_modelo} iniciado con {$fila->pacientes_reales} persona(s) (sin nombres ni correos).");
            $this->line('En Render tarda unos minutos. Revisa con: php artisan migrasense:ml-reentrenar --estado');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function mostrarHistorial(PrediccionService $ml): void
    {
        $filas = array_map(fn ($f) => [
            $f['id_modelo'], $f['version'] ?? '—', $f['estado'], $f['auc'] ?? '—', $f['auc_anterior'] ?? '—',
            $f['pacientes_reales'] ?? '—', $f['fecha_inicio'], mb_strimwidth((string) $f['motivo'], 0, 60, '…'),
        ], $ml->historialAprendizaje());

        $this->newLine();
        $this->line('Historial de aprendizaje (el "activo" es el que usa la app):');
        $filas
            ? $this->table(['#', 'Versión', 'Estado', 'AUC', 'AUC antes', 'Personas', 'Inicio', 'Motivo'], $filas)
            : $this->line('  (todavía no hay reentrenamientos: la app usa el modelo original)');
    }
}
