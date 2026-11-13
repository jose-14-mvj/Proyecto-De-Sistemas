<?php

namespace App\Console\Commands;

use App\Services\AlertaMigranaService;
use App\Services\CorreoService;
use App\Services\PrediccionService;
use App\Services\PushService;
use App\Services\RegistroService;
use App\Support\ReporteSemanalPdf;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Prueba de punta a punta de los 3 avisos para UNA cuenta, ahora mismo, sin esperar la hora:
 *
 *   php artisan migrasense:probar-avisos correo@ejemplo.com
 *   php artisan migrasense:probar-avisos correo@ejemplo.com --solo=diario      (diario | semanal | alerta)
 *   php artisan migrasense:probar-avisos correo@ejemplo.com --sin-correo       (no enviar el PDF por correo)
 *
 * Envía de verdad: la notificación diaria, el resumen semanal (notificación + correo con el PDF)
 * y la alerta de migraña con la probabilidad real de esa persona (marcada como PRUEBA).
 * No toca el control de "ya enviado": los avisos reales siguen saliendo a su hora.
 * La salida no muestra el correo ni el nombre (los registros de GitHub Actions son públicos).
 */
class ProbarAvisos extends Command
{
    protected $signature = 'migrasense:probar-avisos {correo : Correo de la cuenta}
        {--solo= : diario, semanal o alerta (por defecto los tres)}
        {--sin-correo : No enviar el correo con el PDF semanal}';

    protected $description = 'Envía ahora mismo los 3 avisos de prueba a una cuenta y dice qué canal funcionó';

    public function handle(PushService $push, CorreoService $correo, RegistroService $registros,
        PrediccionService $prediccion, AlertaMigranaService $alertas): int
    {
        $u = DB::table('usuario')->where('correo', mb_strtolower(trim((string) $this->argument('correo'))))->where('activo', 1)
            ->first(['id_usuario', 'nombres', 'correo']);
        if (! $u) {
            $this->error('No existe una cuenta activa con ese correo.');
            return self::FAILURE;
        }
        $solo = $this->option('solo');
        if ($solo && ! in_array($solo, ['diario', 'semanal', 'alerta'], true)) {
            $this->error('--solo debe ser diario, semanal o alerta.');
            return self::FAILURE;
        }
        $id = (int) $u->id_usuario;
        $ahora = Carbon::now()->setTimezone(config('app.timezone'));
        $fallos = 0;

        $this->line('Claves de avisos (VAPID): '.($push->configurado() ? 'configuradas' : 'FALTAN (VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY)'));
        $this->line('Dispositivos con avisos activados en la cuenta: '.$push->cantidad($id));
        $this->line('Proveedor de correo: '.(implode(' + ', $correo->proveedores()) ?: 'NINGUNO'));
        $config = DB::table('recordatorio')->where('id_usuario', $id)->get(['frecuencia', 'activo', 'hora_configurada', 'dia_semana']);
        foreach ($config as $c) {
            $this->line("Recordatorio {$c->frecuencia}: ".($c->activo ? 'activado' : 'APAGADO').' a las '.substr((string) $c->hora_configurada, 0, 5)
                .($c->frecuencia === 'semanal' ? ' (día '.$c->dia_semana.', 0 = domingo)' : ''));
        }
        $this->newLine();

        if (! $solo || $solo === 'diario') {
            $n = $push->enviarAUsuario($id, [
                'title' => '¿Cómo te fue hoy? (prueba)',
                'body'  => 'Toma un minuto para anotar tus síntomas. Al tocar este aviso se abre el registro de síntomas prodrómicos.',
                'url'   => 'registro.html?tab=prodromico', 'tag' => 'migrasense-daily',
            ]);
            $this->resultado('Aviso diario', $n);
            $fallos += $n > 0 ? 0 : 1;
        }

        if (! $solo || $solo === 'semanal') {
            $n = $push->enviarAUsuario($id, [
                'title' => 'Tu resumen semanal está listo (prueba)',
                'body'  => 'Te enviamos el PDF a tu correo.',
                'url'   => 'historial.html?reporte=1', 'tag' => 'migrasense-weekly',
            ]);
            $this->resultado('Resumen semanal (notificación)', $n);
            if (! $this->option('sin-correo')) {
                try {
                    $desde = $ahora->copy()->subDays(6)->startOfDay();
                    $regs = array_values(array_filter($registros->listar($id, 'todos', 8, 2000),
                        fn ($r) => Carbon::parse($r['date'], $ahora->getTimezone())->gte($desde)));
                    $pdf = ReporteSemanalPdf::generar((string) $u->nombres, $desde, $ahora, $regs);
                    $correo->enviar((string) $u->correo, (string) $u->nombres, 'Prueba: tu resumen semanal de MigraSense',
                        '<p>Este es un correo de prueba del resumen semanal de MigraSense. El PDF va adjunto.</p>',
                        'Este es un correo de prueba del resumen semanal de MigraSense. El PDF va adjunto.',
                        [['nombre' => 'MigraSense-resumen-prueba.pdf', 'tipo' => 'application/pdf', 'contenido' => $pdf]]);
                    $this->info('  ✔ Resumen semanal (correo con PDF de '.round(strlen($pdf) / 1024).' KB): el proveedor lo aceptó. Revisa la bandeja y también Spam.');
                } catch (Throwable $e) {
                    $fallos++;
                    $this->error('  ✘ Resumen semanal (correo): '.$e->getMessage());
                }
            }
        }

        if (! $solo || $solo === 'alerta') {
            try {
                $p = $prediccion->predecir($id);
                $pct = (int) round($p['porcentaje'] ?? (($p['probabilidad'] ?? 0) * 100));
                $a = $alertas->evaluar($id, $p, $ahora);
                $this->line("  Predicción actual del modelo: {$pct} %. ".($a ? 'Corresponde alerta real.' : 'Hoy NO corresponde alerta real (hace falta 70 % o más y una señal reciente); se envía un ejemplo.'));
                $a ??= ['titulo' => "Posible migraña · {$pct} % de probabilidad",
                    'cuerpo' => "En 5 a 10 horas puede que tengas un episodio de migraña (probabilidad {$pct} %). Descansa, hidrátate y evita tus desencadenantes."];
                $aviso = AlertaMigranaService::avisoPush($a);
                $aviso['title'] .= ' (prueba)';
                $n = $push->enviarAUsuario($id, $aviso);
                $this->resultado('Alerta de migraña', $n);
                $fallos += $n > 0 ? 0 : 1;
            } catch (Throwable $e) {
                $fallos++;
                $this->error('  ✘ Alerta de migraña: '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->line($fallos ? 'Hubo canales que no funcionaron (ver arriba).' : 'Todo salió. Si no ves los avisos en el dispositivo, revisa los permisos de notificación del navegador/celular.');
        return $fallos ? self::FAILURE : self::SUCCESS;
    }

    private function resultado(string $nombre, int $n): void
    {
        $n > 0
            ? $this->info("  ✔ {$nombre}: enviado a {$n} dispositivo(s).")
            : $this->error("  ✘ {$nombre}: ningún dispositivo lo recibió (la cuenta no tiene avisos activados en ningún navegador, o las claves VAPID no coinciden con las de Render).");
    }
}
