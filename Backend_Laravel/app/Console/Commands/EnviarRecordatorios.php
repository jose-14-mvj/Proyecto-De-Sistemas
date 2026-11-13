<?php

namespace App\Console\Commands;

use App\Services\AlertaMigranaService;
use App\Services\RecordatorioEnvioService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * MOTOR DE AVISOS: envía lo que toca ahora (recordatorio diario, resumen semanal con PDF y
 * alerta de posible migraña). Lo ejecuta GitHub Actions cada 5 minutos conectado directo a
 * la base de datos, así que no depende de que la API en Render esté despierta.
 *
 *   php artisan migrasense:enviar-recordatorios
 *   php artisan migrasense:enviar-recordatorios --ahora="2026-10-18 19:05"   (simular otra hora)
 *   php artisan migrasense:enviar-recordatorios --sin-alertas                (solo diario y semanal)
 *   php artisan migrasense:enviar-recordatorios --historial                  (últimos envíos)
 *
 * La salida normal no muestra nombres ni correos (los registros de GitHub Actions son públicos).
 */
class EnviarRecordatorios extends Command
{
    protected $signature = 'migrasense:enviar-recordatorios
        {--ahora= : Fecha y hora a simular (hora de Bolivia)}
        {--sin-alertas : No revisar la alerta de posible migraña}
        {--historial : Muestra los últimos envíos}';

    protected $description = 'Envía los recordatorios diarios, los resúmenes semanales y las alertas de migraña que corresponden a esta hora';

    public function handle(RecordatorioEnvioService $servicio, AlertaMigranaService $alertas): int
    {
        if ($this->option('historial')) {
            $filas = DB::table('envio_recordatorio as e')->join('usuario as u', 'u.id_usuario', '=', 'e.id_usuario')
                ->where('e.tipo', '<>', 'alerta_rev')
                ->orderByDesc('e.id_envio')->limit(25)->get(['e.fecha_envio', 'e.tipo', 'e.clave', 'u.correo', 'e.resultado']);
            $this->table(['Fecha', 'Tipo', 'Día/semana', 'Cuenta', 'Resultado'], $filas->map(fn ($f) => (array) $f)->all());
            return self::SUCCESS;
        }
        $ahora = $this->option('ahora') ? Carbon::parse($this->option('ahora'), config('app.timezone')) : null;
        $hora = ($ahora ?? Carbon::now())->copy()->setTimezone(config('app.timezone'))->format('Y-m-d H:i');

        $r = $servicio->ejecutar($ahora);
        $this->info("[{$hora} Bolivia] Diarios enviados: {$r['diarios']} · Semanales: {$r['semanales']} · Omitidos (ya registró hoy): {$r['omitidos']} · Errores: {$r['errores']}");

        $erroresAlerta = 0;
        if (! $this->option('sin-alertas')) {
            try {
                $a = $alertas->ejecutar($ahora);
                $erroresAlerta = $a['errores'];
                $this->info("Alertas de migraña enviadas: {$a['alertas']} · Personas revisadas: {$a['revisados']} · Estado: {$a['estado']}");
            } catch (Throwable $e) {
                $erroresAlerta = 1;
                $this->warn('Alertas de migraña: no se pudieron revisar ('.class_basename($e).').');
            }
        }
        // Un fallo del ML (dormido) no es un fallo del motor: se reintenta solo en la siguiente vuelta
        return $r['errores'] ? self::FAILURE : self::SUCCESS;
    }
}
