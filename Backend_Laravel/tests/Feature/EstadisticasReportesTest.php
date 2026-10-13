<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;

class EstadisticasReportesTest extends TestCase
{
    private function episodio(string $token, int $haceDias, array $cambios = []): int
    {
        return $this->postJson('/api/registros/episodio', array_merge([
            'intensidad' => 6, 'duracion' => '4_24h',
            'inicio' => Carbon::now()->subDays($haceDias)->setTime(10, 0)->format('Y-m-d\TH:i'),
            'sintomas' => ['luz'], 'disparadores' => ['estres'],
        ], $cambios), $this->conToken($token))->assertStatus(201)->json('registro.id');
    }

    public function test_estadisticas_sin_registros(): void
    {
        [, $t] = $this->usuarioConToken();

        $r = $this->getJson('/api/estadisticas', $this->conToken($t))->assertOk()
                  ->assertJsonPath('resumen.registrosTotales', 0)
                  ->assertJsonPath('patrones.suficiente', false)
                  ->assertJsonPath('patrones.porDia', [0, 0, 0, 0, 0, 0, 0]);
        $this->assertCount(12, $r->json('semanas'));
    }

    public function test_estadisticas_con_registros(): void
    {
        [, $t] = $this->usuarioConToken();
        $this->episodio($t, 2, ['intensidad' => 8, 'medicacion' => true, 'medicamento' => 'Ibuprofeno']);
        $this->episodio($t, 10, ['disparadores' => ['estres', 'sueno']]);
        $this->episodio($t, 45, ['duracion' => '1_3d']);

        $r = $this->getJson('/api/estadisticas?semanas=8&top=3', $this->conToken($t))->assertOk();

        $r->assertJsonPath('resumen.episodios30', 2)
          ->assertJsonPath('resumen.registrosTotales', 3)
          ->assertJsonPath('patrones.episodios', 3)
          ->assertJsonPath('patrones.duracionFrecuente', '4_24h')
          ->assertJsonPath('patrones.medicacionPct', 33)
          ->assertJsonPath('patrones.tendencia.actual', 2)
          ->assertJsonPath('patrones.tendencia.previo', 1)
          ->assertJsonPath('desencadenantes.0', ['codigo' => 'estres', 'nombre' => 'Estrés', 'n' => 3])
          ->assertJsonPath('sintomas.0.n', 3);
        $this->assertSame(3, array_sum($r->json('patrones.porDia')));
        $this->assertCount(8, $r->json('semanas'));
        $this->assertSame(3, array_sum(array_column($r->json('semanas'), 'episodios')));   // 8 semanas cubren ~49-55 días: entran los 3
    }

    public function test_estadisticas_validan_parametros(): void
    {
        [, $t] = $this->usuarioConToken();
        $this->getJson('/api/estadisticas?semanas=2', $this->conToken($t))->assertStatus(422);
        $this->getJson('/api/estadisticas?top=50', $this->conToken($t))->assertStatus(422);
    }

    public function test_reporte_de_30_dias_incluye_solo_el_periodo(): void
    {
        [$u, $t] = $this->usuarioConToken();
        $this->episodio($t, 5);
        $this->episodio($t, 60);

        $r = $this->postJson('/api/reportes', ['dias' => 30], $this->conToken($t))->assertStatus(201)
                  ->assertJsonPath('reporte.etiqueta', 'Últimos 30 días')
                  ->assertJsonPath('reporte.dias', 30)
                  ->assertJsonPath('usuario.id', $u->id_usuario)
                  ->assertJsonCount(1, 'registros');

        $id = $r->json('reporte.id');
        $this->getJson('/api/reportes', $this->conToken($t))->assertOk()
             ->assertJsonPath('reportes.0.id', $id)->assertJsonPath('reportes.0.episodios', 1);
        $this->getJson("/api/reportes/$id", $this->conToken($t))->assertOk()->assertJsonCount(1, 'registros');
    }

    public function test_reporte_de_todo_el_historial(): void
    {
        [, $t] = $this->usuarioConToken();
        $this->episodio($t, 5);
        $this->episodio($t, 200);

        $this->postJson('/api/reportes', ['dias' => 0], $this->conToken($t))->assertStatus(201)
             ->assertJsonPath('reporte.etiqueta', 'Todo el historial')
             ->assertJsonPath('reporte.dias', 0)
             ->assertJsonCount(2, 'registros');
    }

    public function test_reportes_validaciones(): void
    {
        [, $t] = $this->usuarioConToken();
        $this->postJson('/api/reportes', [], $this->conToken($t))->assertStatus(422)->assertJsonValidationErrors('dias');
        $this->postJson('/api/reportes', ['dias' => 30, 'formato' => 'doc'], $this->conToken($t))->assertStatus(422);
        $this->postJson('/api/reportes', ['dias' => -1], $this->conToken($t))->assertStatus(422);
    }

    public function test_reportes_ajenos_no_se_ven_ni_se_borran(): void
    {
        [, $tAna] = $this->usuarioConToken();
        [, $tBeto] = $this->usuarioConToken();
        $this->episodio($tAna, 3);
        $id = $this->postJson('/api/reportes', ['dias' => 30], $this->conToken($tAna))->json('reporte.id');

        $this->getJson("/api/reportes/$id", $this->conToken($tBeto))->assertStatus(404);
        $this->deleteJson("/api/reportes/$id", [], $this->conToken($tBeto))->assertStatus(404);
        $this->getJson('/api/reportes', $this->conToken($tBeto))->assertJsonCount(0, 'reportes');

        // Ana sí puede borrarlo
        $this->deleteJson("/api/reportes/$id", [], $this->conToken($tAna))->assertOk();
        $this->getJson("/api/reportes/$id", $this->conToken($tAna))->assertStatus(404);
    }
}
