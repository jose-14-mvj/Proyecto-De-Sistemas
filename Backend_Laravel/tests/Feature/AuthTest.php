<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Tests\TestCase;

class AuthTest extends TestCase
{
    private function registrar(array $cambios = [])
    {
        return $this->postJson('/api/registro', array_merge([
            'name' => 'Ana Prueba', 'email' => 'ana@test.com',
            'password' => 'ClaveSegura123', 'password_confirmation' => 'ClaveSegura123',
        ], $cambios));
    }

    public function test_registro_crea_la_cuenta_y_devuelve_token(): void
    {
        $r = $this->registrar();

        $r->assertStatus(201)
          ->assertJsonPath('ok', true)
          ->assertJsonPath('usuario.email', 'ana@test.com')
          ->assertJsonPath('perfil', null);
        $this->assertNotEmpty($r->json('token'));
    }

    public function test_la_contrasena_se_guarda_con_argon2id_y_nunca_en_texto_plano(): void
    {
        $this->registrar()->assertStatus(201);
        $hash = Usuario::where('correo', 'ana@test.com')->value('contrasena');

        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertStringNotContainsString('ClaveSegura123', $hash);
    }

    public function test_registro_rechaza_contrasenas_debiles(): void
    {
        foreach (['corta1A', 'todominuscula123', 'TODOMAYUSCULA123', 'SinNumerosNunca'] as $debil) {
            $this->registrar(['password' => $debil, 'password_confirmation' => $debil])
                 ->assertStatus(422)->assertJsonValidationErrors('password');
        }
    }

    public function test_registro_rechaza_correo_repetido(): void
    {
        $this->registrar()->assertStatus(201);
        $this->registrar()->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_registro_rechaza_correos_con_saltos_de_linea(): void
    {
        // Mitigación de GHSA-5vg9-5847-vvmq (inyección CRLF en la regla email de Laravel 9)
        $this->registrar(['email' => "ana@test.com\r\nBcc: victima@test.com"])
             ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->registrar(['email' => "ana@test.com\nX: y"])
             ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_la_respuesta_nunca_incluye_la_contrasena(): void
    {
        $r = $this->registrar();
        $this->assertStringNotContainsString('contrasena', $r->getContent());
        $this->assertStringNotContainsString('argon2', $r->getContent());
    }

    public function test_login_correcto(): void
    {
        $this->usuarioConToken('beto@test.com');

        $this->postJson('/api/login', ['email' => 'beto@test.com', 'password' => 'ClaveSegura123'])
             ->assertOk()->assertJsonPath('usuario.email', 'beto@test.com')
             ->assertJsonStructure(['ok', 'usuario', 'perfil', 'token']);
    }

    public function test_login_fallido_da_el_mismo_mensaje_si_el_correo_existe_o_no(): void
    {
        $this->usuarioConToken('beto@test.com');

        $claveMala = $this->postJson('/api/login', ['email' => 'beto@test.com', 'password' => 'OtraClave999']);
        $noExiste  = $this->postJson('/api/login', ['email' => 'nadie@test.com', 'password' => 'OtraClave999']);

        $claveMala->assertStatus(401);
        $noExiste->assertStatus(401);
        $this->assertSame($claveMala->json(), $noExiste->json());   // no revela qué correos existen
    }

    public function test_login_se_bloquea_tras_muchos_intentos(): void
    {
        $this->usuarioConToken('beto@test.com');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'beto@test.com', 'password' => 'Incorrecta123'])->assertStatus(401);
        }
        $this->postJson('/api/login', ['email' => 'beto@test.com', 'password' => 'Incorrecta123'])->assertStatus(429);
    }

    public function test_sesion_y_logout_revoca_solo_ese_token(): void
    {
        [, $token1] = $this->usuarioConToken('beto@test.com');
        $token2 = Usuario::where('correo', 'beto@test.com')->first()->createToken('otro')->plainTextToken;

        $this->getJson('/api/sesion', $this->conToken($token1))->assertOk()->assertJsonPath('usuario.email', 'beto@test.com');

        $this->postJson('/api/logout', [], $this->conToken($token1))->assertOk();

        $this->getJson('/api/sesion', $this->conToken($token1))->assertStatus(401);   // revocado
        $this->getJson('/api/sesion', $this->conToken($token2))->assertOk();          // el otro dispositivo sigue
    }
}
