<?php

namespace Tests\Feature;

use Tests\TestCase;

class SeguridadTest extends TestCase
{
    public function test_las_respuestas_de_la_api_llevan_cabeceras_de_seguridad(): void
    {
        $r = $this->getJson('/api/sesion');

        $r->assertHeader('X-Content-Type-Options', 'nosniff')
          ->assertHeader('X-Frame-Options', 'DENY')
          ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString("default-src 'none'", $r->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertFalse($r->headers->has('X-Powered-By'));
    }

    public function test_hsts_solo_por_https(): void
    {
        $this->getJson('/api/sesion')->assertHeaderMissing('Strict-Transport-Security');
        $this->getJson('https://localhost/api/sesion')->assertHeader('Strict-Transport-Security');
    }

    public function test_detras_del_proxy_de_render_reconoce_https_e_ip_real(): void
    {
        config(['app.trusted_proxies' => '*']);
        $r = $this->getJson('/api/sesion', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '200.87.1.1']);
        $r->assertHeader('Strict-Transport-Security');
        \Illuminate\Http\Request::setTrustedProxies([], \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR);   // limpiar (es global)
    }

    public function test_en_local_no_confia_en_cabeceras_de_proxy_falsificadas(): void
    {
        \Illuminate\Http\Request::setTrustedProxies([], \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR);
        config(['app.trusted_proxies' => '']);
        $this->getJson('/api/sesion', ['X-Forwarded-Proto' => 'https'])->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_todas_las_rutas_protegidas_piden_token(): void
    {
        $rutas = [
            ['GET', '/api/sesion'], ['POST', '/api/logout'],
            ['GET', '/api/perfil'], ['PUT', '/api/perfil'],
            ['GET', '/api/catalogos'],
            ['GET', '/api/registros'], ['POST', '/api/registros/episodio'], ['POST', '/api/registros/prodromico'],
            ['GET', '/api/registros/episodio/1'], ['PUT', '/api/registros/episodio/1'], ['DELETE', '/api/registros/episodio/1'],
            ['GET', '/api/recordatorios'], ['PUT', '/api/recordatorios'],
            ['GET', '/api/notificaciones'], ['POST', '/api/notificaciones'], ['PUT', '/api/notificaciones/1'],
            ['GET', '/api/estadisticas'], ['GET', '/api/prediccion'],
            ['GET', '/api/reportes'], ['POST', '/api/reportes'], ['GET', '/api/reportes/1'], ['DELETE', '/api/reportes/1'],
            ['PUT', '/api/cuenta'], ['PUT', '/api/cuenta/clave'], ['DELETE', '/api/cuenta'],
        ];

        foreach ($rutas as [$metodo, $url]) {
            $r = $this->json($metodo, $url);
            $this->assertSame(401, $r->status(), "$metodo $url debería pedir token");
        }
    }

    public function test_token_inventado_no_sirve(): void
    {
        $this->getJson('/api/sesion', ['Authorization' => 'Bearer 1|tokenfalsotokenfalsotokenfalso'])->assertStatus(401);
    }

    public function test_responde_json_aunque_el_cliente_no_lo_pida(): void
    {
        $r = $this->get('/api/sesion', ['Accept' => 'text/html']);
        $r->assertStatus(401);
        $this->assertStringContainsString('application/json', $r->headers->get('Content-Type'));
    }

    public function test_cors_acepta_el_frontend_y_rechaza_otros_origenes(): void
    {
        $preflight = fn (string $origen) => $this->call('OPTIONS', '/api/login', [], [], [], [
            'HTTP_ORIGIN' => $origen,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type, authorization',
        ]);

        $permitido = $preflight('http://127.0.0.1:5500');
        $this->assertSame('http://127.0.0.1:5500', $permitido->headers->get('Access-Control-Allow-Origin'));

        $ajeno = $preflight('https://sitio-malicioso.example');
        $this->assertNull($ajeno->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_ruta_inexistente_responde_404_json(): void
    {
        $this->getJson('/api/no-existe')->assertStatus(404);
    }
}
