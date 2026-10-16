<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;

class RegistrosTest extends TestCase
{
    private function episodio(string $token, array $cambios = [])
    {
        return $this->postJson('/api/registros/episodio', array_merge([
            'intensidad' => 7, 'duracion' => '4_24h',
            'inicio' => Carbon::now()->subDays(2)->format('Y-m-d\TH:i'),
            'sintomas' => ['luz', 'ruido'], 'disparadores' => ['estres'],
            'medicacion' => true, 'medicamento' => 'Ibuprofeno',
        ], $cambios), $this->conToken($token));
    }

    public function test_catalogos_solo_muestran_lo_activo(): void
    {
        [, $t] = $this->usuarioConToken();
        $r = $this->getJson('/api/catalogos', $this->conToken($t))->assertOk();

        $codigos = array_column($r->json('sintomas'), 'id');
        $this->assertContains('ruido', $codigos);
        $this->assertNotContains('sonido', $codigos);   // desactivado
    }

    public function test_crear_episodio_con_el_formato_del_frontend(): void
    {
        [, $t] = $this->usuarioConToken();

        $this->episodio($t)->assertStatus(201)
             ->assertJsonPath('registro.type', 'episodio')
             ->assertJsonPath('registro.intensidad', 7)
             ->assertJsonPath('registro.sintomas', ['Sensibilidad a la luz', 'Sensibilidad al ruido'])
             ->assertJsonPath('registro.disparadores', ['Estrés'])
             ->assertJsonPath('registro.medicacion', true)
             ->assertJsonPath('registro.medicamento', 'Ibuprofeno');
    }

    public function test_crear_prodromico_y_filtrar_por_tipo(): void
    {
        [, $t] = $this->usuarioConToken();
        $this->episodio($t)->assertStatus(201);
        $this->postJson('/api/registros/prodromico', [
            'intensidad' => 3, 'sintomas' => ['nauseas'], 'inicio' => Carbon::now()->subHour()->format('Y-m-d\TH:i'),
        ], $this->conToken($t))->assertStatus(201)->assertJsonPath('registro.type', 'prodromico');

        $this->assertCount(2, $this->getJson('/api/registros', $this->conToken($t))->json('registros'));
        $soloEp = $this->getJson('/api/registros?tipo=episodio', $this->conToken($t))->json('registros');
        $this->assertCount(1, $soloEp);
        $this->assertSame('episodio', $soloEp[0]['type']);
    }

    public function test_validaciones_de_registro(): void
    {
        [, $t] = $this->usuarioConToken();

        $this->episodio($t, ['sintomas' => ['sonido']])->assertStatus(422)->assertJsonValidationErrors('sintomas.0');
        $this->episodio($t, ['intensidad' => 11])->assertStatus(422)->assertJsonValidationErrors('intensidad');
        $this->episodio($t, ['duracion' => '5_dias'])->assertStatus(422)->assertJsonValidationErrors('duracion');
        $this->episodio($t, ['inicio' => Carbon::now()->addDay()->format('Y-m-d\TH:i')])
             ->assertStatus(422)->assertJsonValidationErrors('inicio');
        $this->episodio($t, ['notas' => str_repeat('x', 501)])->assertStatus(422)->assertJsonValidationErrors('notas');
    }

    public function test_nadie_puede_ver_editar_ni_borrar_registros_ajenos(): void
    {
        [, $ana] = $this->usuarioConToken();
        [, $beto] = $this->usuarioConToken();
        $id = $this->episodio($ana)->json('registro.id');

        $this->getJson("/api/registros/episodio/$id", $this->conToken($beto))->assertStatus(404);
        $this->putJson("/api/registros/episodio/$id", ['intensidad' => 1], $this->conToken($beto))->assertStatus(404);
        $this->deleteJson("/api/registros/episodio/$id", [], $this->conToken($beto))->assertStatus(404);
        $this->assertCount(0, $this->getJson('/api/registros', $this->conToken($beto))->json('registros'));

        // Ana sigue viendo su registro intacto
        $this->getJson("/api/registros/episodio/$id", $this->conToken($ana))->assertOk()->assertJsonPath('registro.intensidad', 7);
    }

    /* ---------- Correcciones 3 (PDF): Auras, Ningún síntoma y edición completa ---------- */

    public function test_auras_es_un_sintoma_valido_y_ninguno_se_ignora(): void
    {
        [, $t] = $this->usuarioConToken();
        $this->episodio($t, ['sintomas' => ['auras']])->assertStatus(201)->assertJsonPath('registro.sintomas', ['Auras']);
        $this->episodio($t, ['sintomas' => ['ninguno']])->assertStatus(201)->assertJsonPath('registro.sintomas', []);
        $this->episodio($t, ['sintomas' => ['ninguno', 'auras']])->assertStatus(201)->assertJsonPath('registro.sintomas', ['Auras']);
        $this->assertContains('auras', array_column($this->getJson('/api/catalogos', $this->conToken($t))->json('sintomas'), 'id'));
    }

    public function test_prodromico_con_solo_ninguno_pide_elegir_un_sintoma(): void
    {
        [, $t] = $this->usuarioConToken();
        $this->postJson('/api/registros/prodromico', ['intensidad' => 3, 'sintomas' => ['ninguno']], $this->conToken($t))
             ->assertStatus(422)->assertJsonPath('errors.sintomas.0', 'Selecciona al menos un síntoma.');
    }

    public function test_editar_episodio_cambia_todos_sus_campos(): void
    {
        [, $t] = $this->usuarioConToken();
        $id = $this->episodio($t)->json('registro.id');
        $nuevoInicio = Carbon::now()->subDays(5)->setTime(8, 15);

        $this->putJson("/api/registros/episodio/$id", [
            'intensidad' => 4, 'duracion' => 'menos_4h', 'inicio' => $nuevoInicio->format('Y-m-d\TH:i'),
            'sintomas' => ['auras', 'nauseas'], 'disparadores' => ['sueno'],
            'medicacion' => false, 'medicamento' => '', 'notas' => 'Corregido',
            'fin' => $nuevoInicio->copy()->addHours(2)->toIso8601String(), 'duracionMinutos' => 120,   // campos extra del frontend: se ignoran
        ], $this->conToken($t))->assertOk()
            ->assertJsonPath('registro.intensidad', 4)
            ->assertJsonPath('registro.duracion', 'menos_4h')
            ->assertJsonPath('registro.inicio', $nuevoInicio->format('Y-m-d\TH:i:00'))
            ->assertJsonPath('registro.disparadores', ['Falta de sueño'])
            ->assertJsonPath('registro.medicacion', false)
            ->assertJsonPath('registro.notas', 'Corregido');

        $sint = $this->getJson("/api/registros/episodio/$id", $this->conToken($t))->json('registro.sintomas');
        sort($sint);
        $this->assertSame(['Auras', 'Náuseas'], $sint);

        // Quitar todos los síntomas ("Ningún síntoma") y volver a tomar medicación sin nombre
        $this->putJson("/api/registros/episodio/$id", ['intensidad' => 4, 'sintomas' => ['ninguno'], 'medicacion' => true, 'medicamento' => ''],
            $this->conToken($t))->assertOk()->assertJsonPath('registro.sintomas', [])->assertJsonPath('registro.medicacion', true)
            ->assertJsonPath('registro.disparadores', ['Falta de sueño']);   // lo que no se envía no cambia
    }

    public function test_editar_prodromico_cambia_fecha_y_sintoma(): void
    {
        [, $t] = $this->usuarioConToken();
        $id = $this->postJson('/api/registros/prodromico', ['intensidad' => 3, 'sintomas' => ['nauseas'],
            'inicio' => Carbon::now()->subHour()->format('Y-m-d\TH:i')], $this->conToken($t))->json('registro.id');
        $otraFecha = Carbon::now()->subDays(3)->setTime(19, 40);

        $this->putJson("/api/registros/prodromico/$id", ['intensidad' => 6, 'sintomas' => ['luz'], 'notas' => '',
            'inicio' => $otraFecha->format('Y-m-d\TH:i')], $this->conToken($t))->assertOk()
            ->assertJsonPath('registro.sintomas', ['Sensibilidad a la luz'])
            ->assertJsonPath('registro.intensidad', 6)
            ->assertJsonPath('registro.inicio', $otraFecha->format('Y-m-d\TH:i:00'));

        $this->putJson("/api/registros/prodromico/$id", ['intensidad' => 6, 'sintomas' => []], $this->conToken($t))
             ->assertStatus(422)->assertJsonValidationErrors('sintomas');
    }

    public function test_la_edicion_completa_valida_igual_que_al_crear(): void
    {
        [, $t] = $this->usuarioConToken();
        [, $otro] = $this->usuarioConToken();
        $id = $this->episodio($t)->json('registro.id');
        $put = fn (array $d, $tok = null) => $this->putJson("/api/registros/episodio/$id", ['intensidad' => 5] + $d, $this->conToken($tok ?? $t));

        $put(['sintomas' => ['sonido']])->assertStatus(422)->assertJsonValidationErrors('sintomas.0');
        $put(['disparadores' => ['inventado']])->assertStatus(422)->assertJsonValidationErrors('disparadores.0');
        $put(['duracion' => '9_dias'])->assertStatus(422)->assertJsonValidationErrors('duracion');
        $put(['inicio' => Carbon::now()->addDay()->format('Y-m-d\TH:i')])->assertStatus(422)->assertJsonValidationErrors('inicio');
        $put(['sintomas' => ['luz']], $otro)->assertStatus(404);
        $this->getJson("/api/registros/episodio/$id", $this->conToken($t))->assertJsonPath('registro.intensidad', 7);   // nada cambió
    }
}
