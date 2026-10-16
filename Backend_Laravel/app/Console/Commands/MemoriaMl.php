<?php

namespace App\Console\Commands;

use App\Services\PrediccionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Muestra la memoria del ML guardada en Aiven (tabla modelo_ml).
 *
 *   php artisan migrasense:ml-memoria                 -> cada modelo guardado, si está sano y qué aprendió
 *   php artisan migrasense:ml-memoria --exportar      -> además baja el modelo activo a storage/app/memoria_ml/
 *   php artisan migrasense:ml-memoria --exportar --id=3 -> baja el modelo #3
 *   php artisan migrasense:ml-memoria --sin-ml        -> no consulta al ML (solo lee la BD)
 */
class MemoriaMl extends Command
{
    protected $signature = 'migrasense:ml-memoria
                            {--exportar : Guarda en storage/app/memoria_ml el modelo (joblib) y lo aprendido (json)}
                            {--id= : Con --exportar: número del modelo (por defecto el activo)}
                            {--sin-ml : No consulta al ML; solo muestra lo que hay en la BD}';

    protected $description = 'Muestra lo que el ML tiene guardado en Aiven (modelos, integridad y lo aprendido)';

    public function handle(PrediccionService $ml): int
    {
        if (! $ml->aprendizajeDisponible()) {
            $this->error('Falta la tabla modelo_ml. Corre primero: php artisan migrate --database=mysql_admin');
            return self::FAILURE;
        }
        if (! $ml->tieneColumnaReporte()) {
            $this->warn('Falta la columna "reporte" (lo aprendido en legible). Corre: php artisan migrate --database=mysql_admin');
        }

        // Los modelos guardados antes de existir la columna no tienen su "reporte": se completa el activo
        if (! $this->option('sin-ml') && $ml->configurado() && $ml->tieneColumnaReporte()) {
            try {
                $this->line('Consultando el ML (si estaba dormido tarda cerca de 1 minuto)...');
                $salud = Http::acceptJson()->timeout(30)->retry(8, 10000, null, false)->get(config('services.ml.url').'/salud');
                if ($ml->completarReporteActivo($salud->json('version'))) {
                    $this->info('Se guardó en Aiven lo aprendido por el modelo activo (no lo tenía).');
                }
            } catch (Throwable $e) {
                $this->warn('No se pudo consultar el ML: '.$e->getMessage());
            }
        }

        $memoria = $ml->memoria();
        if (! $memoria) {
            $this->line('La memoria está vacía: el ML todavía no aprendió de nadie (usa el modelo de los libros).');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('<options=bold>MEMORIA DEL ML EN AIVEN (tabla modelo_ml)</>');
        $this->table(['#', 'Versión', 'Estado', 'AUC', 'Personas', 'Tamaño', 'Archivo sano', 'Lo aprendido'], array_map(fn ($f) => [
            $f['id_modelo'], $f['version'] ?? '—', $f['estado'], $f['auc'] ?? '—', $f['pacientes_reales'] ?? '—',
            $f['kb'] !== null ? $f['kb'].' KB' : '—',
            $f['sano'] === null ? 'sin archivo' : ($f['sano'] ? 'SÍ (SHA-256 ok)' : 'NO: dañado'),
            $f['reporte'] ? 'guardado' : '—',
        ], $memoria));
        $this->line('Solo el modelo "activo" (y los "reemplazado") guardan archivo; los rechazados no se guardan porque no se usan.');

        $activo = collect($memoria)->firstWhere('estado', 'activo');
        if ($activo) {
            $this->mostrarAprendido($activo);
        }

        if ($this->option('exportar')) {
            return $this->exportar($ml, $memoria, $this->option('id'));
        }
        return self::SUCCESS;
    }

    private function mostrarAprendido(array $f): void
    {
        $this->newLine();
        $this->line("<options=bold>LO QUE APRENDIÓ EL MODELO EN USO (#{$f['id_modelo']}, {$f['version']})</>");
        $r = $f['reporte'];
        if (! $r) {
            $this->line('  (todavía no está guardado en legible; corre el comando sin --sin-ml para completarlo)');
            return;
        }
        $d = $r['datos'] ?? [];
        $this->line(sprintf('  Entrenado: %s · %d pacientes simulados (libros) + %d personas reales (%d momentos)',
            $r['entrenado'] ?? '?', $d['pacientes_simulados'] ?? 0, $d['pacientes_reales'] ?? 0, $d['filas_reales'] ?? 0));
        $this->line('  Acierto con personas nuevas (AUC): '.($r['prueba_personas_nuevas']['modelo']['auc'] ?? '?'));

        $pr = $r['prueba_datos_reales'] ?? null;
        if (is_array($pr)) {
            $this->line(sprintf('  Con sus compañeros (que no vio): libros %s → libros + ustedes %s',
                $pr['modelo_base_estudios']['auc'] ?? '?', $pr['modelo_con_datos_reales']['auc'] ?? '?'));
        } elseif (is_string($pr)) {
            $this->line('  Comparación con datos reales: '.$pr);
        }

        $imp = array_slice($r['lo_que_mas_pesa'] ?? [], 0, 8);
        if ($imp) {
            $this->line('  Lo que más pesa en la predicción:');
            $max = max(array_map(fn ($x) => $x['importancia'], $imp)) ?: 1;
            foreach ($imp as $x) {
                $this->line(sprintf('    %-30s %s %s', $x['caracteristica'], str_repeat('█', max(1, (int) round($x['importancia'] / $max * 20))), $x['importancia']));
            }
        }
    }

    private function exportar(PrediccionService $ml, array $memoria, $opcion): int
    {
        $fila = $opcion === null
            ? collect($memoria)->firstWhere('estado', 'activo')
            : collect($memoria)->firstWhere('id_modelo', (int) $opcion);
        if (! $fila || ! $fila['guardado']) {
            $this->error('Ese modelo no tiene archivo guardado.');
            return self::FAILURE;
        }
        $dir = storage_path('app/memoria_ml');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $base = $dir.DIRECTORY_SEPARATOR.'modelo_'.preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $fila['version']);
        file_put_contents($base.'.joblib', $ml->archivoModelo((int) $fila['id_modelo']));
        $this->info('Modelo exportado: '.$base.'.joblib');
        if ($fila['reporte']) {
            file_put_contents($base.'.json', json_encode($fila['reporte'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->info('Lo aprendido (legible): '.$base.'.json');
        }
        return self::SUCCESS;
    }
}
