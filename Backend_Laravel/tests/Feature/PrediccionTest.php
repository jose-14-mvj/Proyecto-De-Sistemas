<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrediccionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ml.url' => 'https://ml-prueba.example', 'services.ml.key' => 'clave-ml']);
    }

    private function respuestaMl(float $p = 0.82): array
    {
        return ['ok' => true, 'probabilidad' => $p, 'porcentaje' => round($p * 100, 1),
                'nivel' => $p >= 0.7 ? 'muy_alto' : 'bajo', 'mensaje' => 'Mensaje del ML',
                'ventana_horas' => 48, 'confianza' => 'media', 'factores' => [], 'modelo_version' => '2.0.0'];
    }

    private function conDatos(string $token): void
    {
        $this->postJson('/api/registros/episodio', [
            'intensidad' => 7, 'duracion' => '4_24h', 'inicio' => Carbon::now()->subDays(5)->format('Y-m-d\TH:i'),
            'sintomas' => ['luz'], 'disparadores' => ['estres'],
        ], $this->conToken($token))->assertStatus(201);
        $this->postJson('/api/registros/prodromico', [
            'intensidad' => 5, 'sintomas' => ['bostezos_repetidos', 'cervical'],
            'inicio' => Carbon::now()->subHours(3)->format('Y-m-d\TH:i'),
        ], $this->conToken($token))->assertStatus(201);
    }

    public function test_envia_al_ml_los_datos_de_la_bd_sin_datos_personales(): void
    {
        Http::fake(['ml-prueba.example/*' => Http::response($this->respuestaMl(0.3))]);
        [, $t] = $this->usuarioConToken('secreto@test.com');
        $this->conDatos($t);

        $this->getJson('/api/prediccion', $this->conToken($t))->assertOk()
             ->assertJsonPath('ok', true)
             ->assertJsonPath('prediccion.probabilidad', 0.3)
             ->assertJsonPath('prediccion.ventana_horas', 48);

        Http::assertSent(function (PeticionHttp $r) {
            $cuerpo = $r->body();
            return $r->url() === 'https://ml-prueba.example/predecir'
                && $r->hasHeader('X-API-Key', 'clave-ml')
                && $r['episodios'][0]['desencadenantes'] === ['estres']           // códigos, no nombres
                && $r['registros'][0]['sintomas'] === ['bostezos_repetidos', 'cervical']
                && isset($r['ahora'])
                && ! str_contains($cuerpo, 'secreto@test.com')                     // nunca el correo
                && ! str_contains($cuerpo, 'Prueba');                              // nunca el nombre
        });
    }

    public function test_si_el_ml_no_responde_devuelve_503_amigable(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));
        [, $t] = $this->usuarioConToken();

        $this->getJson('/api/prediccion', $this->conToken($t))->assertStatus(503)
             ->assertJsonPath('ok', false)
             ->assertJson(fn ($j) => $j->where('error', fn ($e) => str_contains($e, 'despertar'))->etc());
    }

    public function test_si_el_ml_responde_error_devuelve_503(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'boom'], 500)]);
        [, $t] = $this->usuarioConToken();
        $this->getJson('/api/prediccion', $this->conToken($t))->assertStatus(503);
    }

    public function test_sin_configurar_devuelve_503(): void
    {
        config(['services.ml.url' => '']);
        Http::fake();
        [, $t] = $this->usuarioConToken();
        $this->getJson('/api/prediccion', $this->conToken($t))->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_riesgo_muy_alto_crea_una_sola_alerta_si_la_persona_la_permite(): void
    {
        Http::fake(['*' => Http::response($this->respuestaMl(0.85))]);
        [$u, $t] = $this->usuarioConToken();
        $this->conDatos($t);

        $this->getJson('/api/prediccion', $this->conToken($t))->assertOk();
        $this->postJson('/api/registros/prodromico', ['intensidad' => 4, 'sintomas' => ['luz']], $this->conToken($t));
        $this->getJson('/api/prediccion', $this->conToken($t))->assertOk();   // datos nuevos: no usa caché

        $alertas = DB::table('notificacion')->where('id_usuario', $u->id_usuario)->where('tipo', 'alerta_riesgo')->get();
        $this->assertCount(1, $alertas);                                     // máximo 1 cada 24 h
        $this->assertStringContainsString('85', $alertas[0]->mensaje);
    }

    public function test_sin_permiso_de_alertas_no_crea_notificacion(): void
    {
        Http::fake(['*' => Http::response($this->respuestaMl(0.9))]);
        [$u, $t] = $this->usuarioConToken();
        $this->putJson('/api/recordatorios', ['alerta' => ['on' => false]], $this->conToken($t))->assertOk();

        $this->getJson('/api/prediccion', $this->conToken($t))->assertOk();
        $this->assertSame(0, DB::table('notificacion')->where('id_usuario', $u->id_usuario)->count());
    }

    public function test_usa_cache_si_no_hay_registros_nuevos(): void
    {
        Http::fake(['*' => Http::response($this->respuestaMl(0.2))]);
        [, $t] = $this->usuarioConToken();
        $this->getJson('/api/prediccion', $this->conToken($t))->assertOk();
        $this->getJson('/api/prediccion', $this->conToken($t))->assertOk();
        Http::assertSentCount(1);
    }

    public function test_cada_persona_recibe_solo_su_prediccion(): void
    {
        Http::fake(['*' => Http::response($this->respuestaMl(0.2))]);
        [, $tAna] = $this->usuarioConToken();
        [, $tBeto] = $this->usuarioConToken();
        $this->conDatos($tAna);

        $this->getJson('/api/prediccion', $this->conToken($tBeto))->assertOk();
        Http::assertSent(fn (PeticionHttp $r) => $r['episodios'] === [] && $r['registros'] === []);
    }
}
