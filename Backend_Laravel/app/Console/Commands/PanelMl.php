<?php

namespace App\Console\Commands;

use App\Services\PrediccionService;
use App\Support\PanelMlHtml;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Panel LOCAL del aprendizaje del ML (solo para quien lo corre; no se publica nada).
 *
 *   php artisan migrasense:panel-ml              -> arma el panel y lo abre en el navegador
 *   php artisan migrasense:panel-ml --no-abrir   -> solo lo guarda (storage/app/panel_ml.html)
 *
 * Junta: la memoria guardada en Aiven (tabla modelo_ml, con integridad y lo aprendido), cuántos datos hay y cuántos faltan para
 * el próximo aprendizaje, y lo que el ML dice de sí mismo (/salud y /metricas).
 * Solo muestra conteos: ningún nombre, correo ni registro de nadie.
 */
class PanelMl extends Command
{
    protected $signature = 'migrasense:panel-ml {--no-abrir : Solo guarda el archivo, no abre el navegador}';

    protected $description = 'Abre un panel local con lo que va aprendiendo el ML (historial, datos, métricas)';

    public function handle(PrediccionService $ml): int
    {
        $this->line('Armando el panel del ML...');
        $datos = [
            'generado'  => Carbon::now()->format('d/m/Y H:i'),
            'reglas'    => [
                'auto'   => (bool) config('services.ml.auto_reentrenar'),
                'horas'  => (int) config('services.ml.reentrenar_horas', 24),
                'minimo' => (int) config('services.ml.reentrenar_minimo', 20),
            ],
            'historial' => [],
            'conteos'   => [],
            'actividad' => [],
            'salud'     => null,
            'metricas'  => null,
            'aviso_ml'  => null,
        ];

        // 1) Base de datos (Aiven)
        try {
            $datos['conteos'] = $this->conteos();
            $datos['actividad'] = $this->actividad30Dias();
            $datos['historial'] = $ml->memoria();   // filas de modelo_ml + tamaño, integridad y lo aprendido
        } catch (Throwable $e) {
            $this->error('No se pudo leer la base de datos: '.$e->getMessage());
            return self::FAILURE;
        }

        // 2) El ML (Render). Si estaba dormido se despierta; si tiene el modelo base, se le carga el aprendido.
        if ($ml->configurado()) {
            $this->line('Consultando el ML (si estaba dormido tarda cerca de 1 minuto)...');
            try {
                $salud = $this->get('/salud');
                $activo = collect($datos['historial'])->firstWhere('estado', 'activo');
                if ($activo && ($salud['version'] ?? null) !== $activo['version'] && $ml->asegurarModeloEnMl($salud['version'] ?? null)) {
                    $this->line('El ML había despertado con el modelo base: se le cargó el modelo aprendido.');
                    $salud = $this->get('/salud');
                }
                $datos['salud'] = $salud;
                $datos['metricas'] = $this->get('/metricas');
                if ($ml->completarReporteActivo($salud['version'] ?? null)) {
                    $this->line('Se guardó en Aiven lo aprendido por el modelo activo (no lo tenía).');
                    $datos['historial'] = $ml->memoria();
                }
            } catch (Throwable $e) {
                $datos['aviso_ml'] = 'No se pudo consultar el ML: '.$e->getMessage();
                $this->warn($datos['aviso_ml']);
            }
        } else {
            $datos['aviso_ml'] = 'Falta ML_URL o ML_API_KEY en el .env.';
        }

        // Si el ML no respondió, se muestra lo aprendido que está guardado en Aiven
        if (! $datos['metricas']) {
            $activo = collect($datos['historial'])->firstWhere('estado', 'activo');
            if ($activo && $activo['reporte']) {
                $datos['metricas'] = $activo['reporte'];
                $datos['metricas_de_aiven'] = true;
            }
        }

        $ruta = storage_path('app/panel_ml.html');
        file_put_contents($ruta, PanelMlHtml::generar($datos));
        $this->info('Panel listo: '.$ruta);

        if (! $this->option('no-abrir')) {
            $this->abrir($ruta);
        }
        return self::SUCCESS;
    }

    /** Conteos generales (sin datos personales) */
    private function conteos(): array
    {
        $demo = fn ($q) => $q->where('correo', 'like', '%@migrasense.demo');
        $conDatos = DB::table('registro_sintoma')->select('id_usuario')
            ->union(DB::table('episodio_migrana')->select('id_usuario'))->get()->count();
        $registros = DB::table('registro_sintoma')->count();
        $episodios = DB::table('episodio_migrana')->count();

        return [
            'usuarios'        => DB::table('usuario')->where('activo', 1)->count(),
            'usuarios_demo'   => $demo(DB::table('usuario'))->count(),
            'personas_datos'  => $conDatos,
            'registros'       => $registros,
            'episodios'       => $episodios,
            'total'           => $registros + $episodios,   // lo mismo que cuenta el reentrenamiento
        ];
    }

    /** Cuántos registros y episodios se cargaron cada día (últimos 30 días, por fecha de carga) */
    private function actividad30Dias(): array
    {
        $desde = Carbon::now()->subDays(29)->startOfDay();
        $dias = [];
        for ($d = $desde->copy(); $d->lte(Carbon::now()); $d->addDay()) {
            $dias[$d->format('Y-m-d')] = ['registros' => 0, 'episodios' => 0];
        }
        foreach (['registro_sintoma' => 'registros', 'episodio_migrana' => 'episodios'] as $tabla => $clave) {
            DB::table($tabla)->where('fecha_creacion', '>=', $desde->format('Y-m-d H:i:s'))
                ->pluck('fecha_creacion')
                ->each(function ($f) use (&$dias, $clave) {
                    $k = substr((string) $f, 0, 10);
                    if (isset($dias[$k])) {
                        $dias[$k][$clave]++;
                    }
                });
        }
        return $dias;
    }

    /** GET a un endpoint público del ML, reintentando mientras Render lo despierta */
    private function get(string $ruta): array
    {
        $despertando = fn ($e) => $e instanceof ConnectionException
            || ($e instanceof RequestException && in_array($e->response->status(), [502, 503, 504], true));

        $r = Http::acceptJson()->timeout(30)
            ->retry(8, 10000, $despertando, false)
            ->get(config('services.ml.url').$ruta);

        if (! $r->successful()) {
            throw new \RuntimeException("$ruta respondió ".$r->status());
        }
        return $r->json() ?? [];
    }

    private function abrir(string $ruta): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen('start "" "'.$ruta.'"', 'r'));
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            exec('open '.escapeshellarg($ruta));
        } else {
            exec('xdg-open '.escapeshellarg($ruta).' >/dev/null 2>&1 &');
        }
    }
}
