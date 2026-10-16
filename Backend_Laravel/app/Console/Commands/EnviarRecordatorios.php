<?php

namespace App\Console\Commands;

use App\Services\RecordatorioEnvioService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Envía los recordatorios que tocan ahora (lo mismo que hace cron-job.org en la nube).
 *   php artisan migrasense:enviar-recordatorios
 *   php artisan migrasense:enviar-recordatorios --ahora="2026-10-18 19:05"   (simular otra hora)
 *   php artisan migrasense:enviar-recordatorios --historial                  (últimos envíos)
 */
class EnviarRecordatorios extends Command
{
    protected $signature = 'migrasense:enviar-recordatorios {--ahora= : Fecha y hora a simular (hora de Bolivia)} {--historial : Muestra los últimos envíos}';

    protected $description = 'Envía los recordatorios diarios y semanales que corresponden a esta hora';

    public function handle(RecordatorioEnvioService $servicio): int
    {
        if ($this->option('historial')) {
            $filas = DB::table('envio_recordatorio as e')->join('usuario as u', 'u.id_usuario', '=', 'e.id_usuario')
                ->orderByDesc('e.id_envio')->limit(25)->get(['e.fecha_envio', 'e.tipo', 'e.clave', 'u.correo', 'e.resultado']);
            $this->table(['Fecha', 'Tipo', 'Día/semana', 'Cuenta', 'Resultado'], $filas->map(fn ($f) => (array) $f)->all());
            return self::SUCCESS;
        }
        $ahora = $this->option('ahora') ? Carbon::parse($this->option('ahora'), config('app.timezone')) : null;
        $r = $servicio->ejecutar($ahora);
        $this->info("Diarios enviados: {$r['diarios']} · Semanales: {$r['semanales']} · Omitidos (ya registró hoy): {$r['omitidos']} · Errores: {$r['errores']}");
        return $r['errores'] ? self::FAILURE : self::SUCCESS;
    }
}
