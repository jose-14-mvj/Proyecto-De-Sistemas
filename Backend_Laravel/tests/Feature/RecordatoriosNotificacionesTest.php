<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecordatoriosNotificacionesTest extends TestCase
{
    public function test_recordatorios_por_defecto(): void
    {
        [, $t] = $this->usuarioConToken();

        $this->getJson('/api/recordatorios', $this->conToken($t))->assertOk()
             ->assertJsonPath('recordatorios.daily', ['on' => true, 'time' => '20:00'])
             ->assertJsonPath('recordatorios.weekly', ['on' => true, 'day' => 0, 'time' => '19:00'])
             ->assertJsonPath('recordatorios.alerta', ['on' => true]);
    }

    public function test_cambio_parcial_solo_toca_lo_enviado(): void
    {
        [, $t] = $this->usuarioConToken();

        $this->putJson('/api/recordatorios', ['weekly' => ['on' => true, 'day' => 3, 'time' => '18:30']], $this->conToken($t))
             ->assertOk()
             ->assertJsonPath('recordatorios.weekly', ['on' => true, 'day' => 3, 'time' => '18:30'])
             ->assertJsonPath('recordatorios.daily', ['on' => true, 'time' => '20:00']);

        $this->putJson('/api/recordatorios', ['alerta' => ['on' => false]], $this->conToken($t))
             ->assertOk()->assertJsonPath('recordatorios.alerta.on', false)
             ->assertJsonPath('recordatorios.weekly.day', 3);
    }

    public function test_recordatorios_validaciones(): void
    {
        [, $t] = $this->usuarioConToken();

        $this->putJson('/api/recordatorios', [], $this->conToken($t))->assertStatus(422);
        $this->putJson('/api/recordatorios', ['weekly' => ['day' => 9]], $this->conToken($t))->assertStatus(422);
        $this->putJson('/api/recordatorios', ['daily' => ['time' => '25:00']], $this->conToken($t))->assertStatus(422);
    }

    public function test_notificaciones_crear_listar_y_validar(): void
    {
        [, $t] = $this->usuarioConToken();

        $this->postJson('/api/notificaciones', ['tipo' => 'resumen_semanal', 'mensaje' => 'Tu resumen'], $this->conToken($t))
             ->assertStatus(201)->assertJsonPath('notificacion.estado', 'pendiente');

        $this->getJson('/api/notificaciones', $this->conToken($t))->assertOk()
             ->assertJsonPath('pendientes', 1)->assertJsonCount(1, 'notificaciones');

        // 'sistema' lo reserva el servidor; el cliente no puede crearlas
        $this->postJson('/api/notificaciones', ['tipo' => 'sistema', 'mensaje' => 'x'], $this->conToken($t))->assertStatus(422);
        $this->postJson('/api/notificaciones', ['tipo' => 'resumen_semanal', 'mensaje' => str_repeat('x', 256)], $this->conToken($t))->assertStatus(422);
    }

    public function test_no_se_pueden_tocar_notificaciones_ni_recordatorios_ajenos(): void
    {
        [$ana, $tAna] = $this->usuarioConToken();
        [, $tBeto] = $this->usuarioConToken();

        $id = $this->postJson('/api/notificaciones', ['tipo' => 'alerta_riesgo', 'mensaje' => 'Cuidado'], $this->conToken($tAna))
                   ->json('notificacion.id');

        $this->putJson("/api/notificaciones/$id", ['estado' => 'leida'], $this->conToken($tBeto))->assertStatus(404);
        $this->assertSame(0, $this->getJson('/api/notificaciones', $this->conToken($tBeto))->json('pendientes'));

        // Beto no puede colgar su notificación de un recordatorio de Ana
        $recAna = DB::table('recordatorio')->insertGetId([
            'id_usuario' => $ana->id_usuario, 'frecuencia' => 'diaria', 'hora_configurada' => '20:00:00',
        ]);
        $this->postJson('/api/notificaciones', ['tipo' => 'recordatorio_diario', 'mensaje' => 'x', 'id_recordatorio' => $recAna], $this->conToken($tBeto))
             ->assertStatus(422);
    }
}
