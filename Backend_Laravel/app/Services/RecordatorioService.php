<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Recordatorios (diario / semanal) y preferencia de alerta de riesgo.
 * Escritura: sp_guardar_recordatorio. Lectura: tabla recordatorio + usuario.alerta_riesgo.
 * Formato = el de js/recordatorios.js: { daily:{on,time}, weekly:{on,day,time}, alerta:{on} }
 */
class RecordatorioService
{
    public function obtener(int $idUsuario): array
    {
        $filas = DB::table('recordatorio')->where('id_usuario', $idUsuario)->get();
        $d = $filas->first(fn ($r) => $r->frecuencia === 'diaria');
        $w = $filas->first(fn ($r) => $r->frecuencia === 'semanal');
        $alerta = DB::table('usuario')->where('id_usuario', $idUsuario)->value('alerta_riesgo');

        return [
            'daily'  => ['on' => $d ? (bool) $d->activo : true,  'time' => $d ? substr($d->hora_configurada, 0, 5) : '20:00'],
            'weekly' => ['on' => $w ? (bool) $w->activo : true,  'day' => $w ? (int) $w->dia_semana : 0,
                         'time' => $w ? substr($w->hora_configurada, 0, 5) : '19:00'],
            'alerta' => ['on' => $alerta === null ? true : (bool) $alerta],
        ];
    }

    /** Actualización parcial: solo cambia lo que llega; lo demás conserva su valor actual. */
    public function guardar(int $idUsuario, array $d): array
    {
        $actual = $this->obtener($idUsuario);

        DB::transaction(function () use ($idUsuario, $d, $actual) {
            if (isset($d['daily'])) {
                $x = array_merge($actual['daily'], $d['daily']);
                $this->guardarSp($idUsuario, 'diaria', (bool) $x['on'], $x['time'].':00', null);
            }
            if (isset($d['weekly'])) {
                $x = array_merge($actual['weekly'], $d['weekly']);
                $this->guardarSp($idUsuario, 'semanal', (bool) $x['on'], $x['time'].':00', (int) $x['day']);
            }
            if (isset($d['alerta']['on'])) {
                DB::table('usuario')->where('id_usuario', $idUsuario)->update(['alerta_riesgo' => $d['alerta']['on'] ? 1 : 0]);
            }
        });

        $nuevo = $this->obtener($idUsuario);
        $this->respetarCambioDeHora($idUsuario, $actual, $nuevo);
        return $nuevo;
    }

    /**
     * La persona cambió la hora (o el día) de un recordatorio: el aviso debe llegar a la hora NUEVA.
     *
     * El motor anota cada aviso en envio_recordatorio para no repetirlo (una vez por día el diario,
     * una vez por semana el semanal). Sin este ajuste, si el aviso de hoy ya había salido y la persona
     * movía la hora más tarde, el motor creía que «ya estaba enviado» y no llegaba nada hasta mañana.
     *
     *  - La hora nueva todavía no llegó hoy → se borra la anotación: saldrá a la hora nueva.
     *  - La hora nueva ya pasó hoy          → se anota como omitido: empieza desde mañana
     *    (así no le llega de golpe un aviso «atrasado» que no pidió).
     */
    private function respetarCambioDeHora(int $idUsuario, array $antes, array $ahora): void
    {
        try {
            $momento = Carbon::now()->setTimezone(config('app.timezone'));
            // "Ya pasó" = hace más de 5 minutos. Si eligió la hora de ahora mismo, se le envía enseguida.
            $pasada = fn (string $hhmm) => Carbon::parse($momento->toDateString().' '.$hhmm, $momento->getTimezone())->lt($momento->copy()->subMinutes(5));
            $reprogramar = function (string $tipo, string $clave, bool $yaPaso) use ($idUsuario, $momento) {
                DB::table('envio_recordatorio')->where(['id_usuario' => $idUsuario, 'tipo' => $tipo, 'clave' => $clave])->delete();
                if ($yaPaso) {
                    DB::table('envio_recordatorio')->insertOrIgnore([
                        'id_usuario' => $idUsuario, 'tipo' => $tipo, 'clave' => $clave,
                        'resultado' => 'omitido: la hora se cambió cuando ya había pasado', 'fecha_envio' => $momento->format('Y-m-d H:i:s'),
                    ]);
                }
            };

            if ($ahora['daily']['on'] && ($antes['daily']['time'] !== $ahora['daily']['time'] || ! $antes['daily']['on'])) {
                $reprogramar('diario', $momento->toDateString(), $pasada($ahora['daily']['time']));
            }
            $w = $ahora['weekly'];
            if ($w['on'] && ($antes['weekly']['time'] !== $w['time'] || $antes['weekly']['day'] !== $w['day'] || ! $antes['weekly']['on'])) {
                // El semanal solo sale el día elegido: si ese día es hoy y la hora ya pasó, queda para la próxima semana
                $reprogramar('semanal', $momento->format('o-\WW'), $w['day'] === $momento->dayOfWeek && $pasada($w['time']));
            }
        } catch (Throwable $e) {
            Log::warning('No se pudo reprogramar el recordatorio del usuario '.$idUsuario.': '.$e->getMessage());   // el horario ya quedó guardado
        }
    }

    protected function guardarSp(int $idUsuario, string $frecuencia, bool $activo, string $hora, ?int $dia): void
    {
        DB::statement('CALL sp_guardar_recordatorio(?,?,?,?,?)', [$idUsuario, $frecuencia, $activo ? 1 : 0, $hora, $dia]);
    }
}
