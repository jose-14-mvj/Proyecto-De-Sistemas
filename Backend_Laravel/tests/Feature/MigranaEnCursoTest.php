<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Correcciones 4: migraña "Aún en curso", "La migraña terminó", hora de fin exacta
 * y la pregunta "¿Es una migraña con aura?".
 */
class MigranaEnCursoTest extends TestCase
{
    private function crear(string $t, array $datos)
    {
        return $this->postJson('/api/registros/episodio', array_merge([
            'intensidad' => 6, 'sintomas' => ['luz'], 'disparadores' => ['estres'], 'medicacion' => false,
        ], $datos), $this->conToken($t));
    }

    public function test_ahora_mismo_en_curso_sin_duracion(): void
    {
        [, $t] = $this->usuarioConToken();
        $r = $this->crear($t, ['inicio' => Carbon::now()->toIso8601String(), 'en_curso' => true, 'aura' => 'no'])
            ->assertStatus(201)
            ->assertJsonPath('registro.enCurso', true)
            ->assertJsonPath('registro.fin', null)
            ->assertJsonPath('registro.aura', 'no')
            ->assertJsonPath('registro.duracion', 'menos_4h');

        $this->getJson('/api/registros/en-curso', $this->conToken($t))->assertOk()
            ->assertJsonPath('registro.id', $r->json('registro.id'));
    }

    public function test_solo_una_migrana_en_curso_a_la_vez(): void
    {
        [, $t] = $this->usuarioConToken();
        $this->crear($t, ['en_curso' => true])->assertStatus(201);
        $this->crear($t, ['en_curso' => true])->assertStatus(422)->assertJsonValidationErrors('en_curso');
        // una migraña ya terminada sí se puede registrar mientras otra sigue en curso
        $this->crear($t, ['inicio' => Carbon::now()->subDays(3)->format('Y-m-d\TH:i'),
            'fin' => Carbon::now()->subDays(3)->addHours(5)->format('Y-m-d\TH:i')])->assertStatus(201);
    }

    public function test_la_migrana_termino_guarda_la_hora_y_calcula_la_duracion(): void
    {
        [, $t] = $this->usuarioConToken();
        $inicio = Carbon::now()->subHours(6)->startOfMinute();
        $id = $this->crear($t, ['inicio' => $inicio->format('Y-m-d\TH:i'), 'en_curso' => true])
            ->assertStatus(201)->assertJsonPath('registro.duracion', '4_24h')->json('registro.id');

        Carbon::setTestNow(Carbon::now()->startOfMinute());
        $r = $this->postJson("/api/registros/episodio/$id/terminar", [], $this->conToken($t))->assertOk()
            ->assertJsonPath('registro.enCurso', false)
            ->assertJsonPath('registro.fin', Carbon::now()->format('Y-m-d\TH:i:s'))
            ->assertJsonPath('registro.duracion', '4_24h');
        $this->assertSame((int) $inicio->diffInMinutes(Carbon::now()), $r->json('registro.duracionMinutos'));
        Carbon::setTestNow();

        // ya no hay migraña en curso y no se puede "terminar" dos veces
        $this->getJson('/api/registros/en-curso', $this->conToken($t))->assertOk()->assertJsonPath('registro', null);
        $this->postJson("/api/registros/episodio/$id/terminar", [], $this->conToken($t))->assertStatus(422);
    }

    public function test_terminar_con_hora_indicada_despues_de_olvidarse(): void
    {
        [, $t] = $this->usuarioConToken();
        $inicio = Carbon::now()->subDays(4)->setTime(9, 0);
        $id = $this->crear($t, ['inicio' => $inicio->format('Y-m-d\TH:i'), 'en_curso' => true])->json('registro.id');
        $fin = $inicio->copy()->addHours(10);

        $this->postJson("/api/registros/episodio/$id/terminar", ['fin' => $fin->format('Y-m-d\TH:i')], $this->conToken($t))
            ->assertOk()->assertJsonPath('registro.duracion', '4_24h')->assertJsonPath('registro.duracionMinutos', 600);

        // fin antes del inicio o en el futuro → 422
        $id2 = $this->crear($t, ['inicio' => Carbon::now()->subHours(2)->format('Y-m-d\TH:i'), 'en_curso' => true])->json('registro.id');
        $this->postJson("/api/registros/episodio/$id2/terminar", ['fin' => Carbon::now()->subHours(5)->format('Y-m-d\TH:i')], $this->conToken($t))
            ->assertStatus(422);
        $this->postJson("/api/registros/episodio/$id2/terminar", ['fin' => Carbon::now()->addDay()->format('Y-m-d\TH:i')], $this->conToken($t))
            ->assertStatus(422);
    }

    public function test_no_se_puede_terminar_la_migrana_de_otra_persona(): void
    {
        [, $ana] = $this->usuarioConToken();
        [, $beto] = $this->usuarioConToken();
        $id = $this->crear($ana, ['en_curso' => true])->json('registro.id');
        $this->postJson("/api/registros/episodio/$id/terminar", [], $this->conToken($beto))->assertStatus(404);
        $this->getJson('/api/registros/en-curso', $this->conToken($beto))->assertJsonPath('registro', null);
        $this->assertSame(1, (int) DB::table('episodio_migrana')->where('id_episodio', $id)->value('en_curso'));
    }

    public function test_elegir_otra_hora_con_fin_exacto(): void
    {
        [, $t] = $this->usuarioConToken();
        $inicio = Carbon::now()->subDays(2)->setTime(22, 30);
        $this->crear($t, ['inicio' => $inicio->format('Y-m-d\TH:i'), 'fin' => $inicio->copy()->addHours(30)->format('Y-m-d\TH:i'), 'aura' => 'no_se'])
            ->assertStatus(201)
            ->assertJsonPath('registro.duracion', '1_3d')
            ->assertJsonPath('registro.duracionMinutos', 1800)
            ->assertJsonPath('registro.fin', $inicio->copy()->addHours(30)->format('Y-m-d\TH:i:s'))
            ->assertJsonPath('registro.enCurso', false)
            ->assertJsonPath('registro.aura', 'no_se');
    }

    public function test_validaciones_de_fin_y_aura(): void
    {
        [, $t] = $this->usuarioConToken();
        $inicio = Carbon::now()->subDay();
        // sin fin, sin "en curso" y sin duración
        $this->crear($t, ['inicio' => $inicio->format('Y-m-d\TH:i')])->assertStatus(422)->assertJsonValidationErrors('duracion');
        // fin anterior al inicio
        $this->crear($t, ['inicio' => $inicio->format('Y-m-d\TH:i'), 'fin' => $inicio->copy()->subHour()->format('Y-m-d\TH:i')])
            ->assertStatus(422)->assertJsonValidationErrors('fin');
        // en curso y con fin a la vez
        $this->crear($t, ['inicio' => $inicio->format('Y-m-d\TH:i'), 'fin' => Carbon::now()->format('Y-m-d\TH:i'), 'en_curso' => true])
            ->assertStatus(422)->assertJsonValidationErrors('fin');
        // aura con valor inventado
        $this->crear($t, ['en_curso' => true, 'aura' => 'quizas'])->assertStatus(422)->assertJsonValidationErrors('aura');
        // compatibilidad: la duración estimada sola sigue funcionando
        $this->crear($t, ['inicio' => $inicio->format('Y-m-d\TH:i'), 'duracion' => 'menos_4h'])->assertStatus(201)
            ->assertJsonPath('registro.aura', null)->assertJsonPath('registro.fin', null);
    }

    public function test_editar_una_migrana_en_curso_para_ponerle_fin_y_aura(): void
    {
        [, $t] = $this->usuarioConToken();
        $inicio = Carbon::now()->subHours(3)->startOfMinute();
        $id = $this->crear($t, ['inicio' => $inicio->format('Y-m-d\TH:i'), 'en_curso' => true])->json('registro.id');

        $this->putJson("/api/registros/episodio/$id", [
            'intensidad' => 8, 'inicio' => $inicio->format('Y-m-d\TH:i'),
            'fin' => $inicio->copy()->addHours(2)->format('Y-m-d\TH:i'), 'en_curso' => false, 'aura' => 'si',
        ], $this->conToken($t))->assertOk()
            ->assertJsonPath('registro.enCurso', false)
            ->assertJsonPath('registro.aura', 'si')
            ->assertJsonPath('registro.duracionMinutos', 120);

        // y volver a dejarla "en curso" (por si se equivocó)
        $this->putJson("/api/registros/episodio/$id", ['intensidad' => 8, 'en_curso' => true], $this->conToken($t))
            ->assertOk()->assertJsonPath('registro.enCurso', true)->assertJsonPath('registro.fin', null);
    }

    public function test_el_historial_trae_los_campos_nuevos(): void
    {
        [, $t] = $this->usuarioConToken();
        $this->crear($t, ['en_curso' => true, 'aura' => 'si'])->assertStatus(201);
        $this->postJson('/api/registros/prodromico', ['intensidad' => 3, 'sintomas' => ['luz'],
            'inicio' => Carbon::now()->subDays(3)->setTime(7, 10)->format('Y-m-d\TH:i')], $this->conToken($t))
            ->assertStatus(201)->assertJsonPath('registro.inicio', Carbon::now()->subDays(3)->setTime(7, 10)->format('Y-m-d\TH:i:s'));

        $regs = collect($this->getJson('/api/registros', $this->conToken($t))->assertOk()->json('registros'));
        $ep = $regs->firstWhere('type', 'episodio');
        $this->assertTrue($ep['enCurso']);
        $this->assertSame('si', $ep['aura']);
        $this->assertArrayNotHasKey('enCurso', $regs->firstWhere('type', 'prodromico'));
    }
}
