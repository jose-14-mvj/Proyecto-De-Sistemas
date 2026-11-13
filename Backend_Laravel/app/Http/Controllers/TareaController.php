<?php

namespace App\Http\Controllers;

use App\Services\AlertaMigranaService;
use App\Services\RecordatorioEnvioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tareas programadas llamadas por el despertador externo (cron-job.org).
 * Requiere la cabecera  X-Cron-Key: <CRON_CLAVE>.
 */
class TareaController extends Controller
{
    /** POST /api/tareas/recordatorios  (cada 15 minutos) */
    public function recordatorios(Request $request, RecordatorioEnvioService $servicio, AlertaMigranaService $alertas): JsonResponse
    {
        $clave = (string) config('services.cron.clave');
        if ($clave === '' || ! hash_equals($clave, (string) $request->header('X-Cron-Key'))) {
            return response()->json(['ok' => false, 'mensaje' => 'No autorizado.'], 403);
        }
        // Si Render estaba dormido, cron-job.org puede cortar la espera: el trabajo sigue igual
        ignore_user_abort(true);
        @set_time_limit(140);

        $res = $servicio->ejecutar();
        try {
            $res['alertas'] = $alertas->ejecutar()['alertas'];
        } catch (\Throwable $e) {
            $res['alertas'] = 0;   // los recordatorios ya salieron; la alerta se reintenta en la siguiente llamada
        }
        return response()->json(['ok' => true] + $res);
    }
}
