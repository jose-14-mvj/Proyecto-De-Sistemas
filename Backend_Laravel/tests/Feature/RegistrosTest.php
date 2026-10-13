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
}
