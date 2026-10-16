<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecuperacionClaveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.correo.brevo_key'        => 'xkeysib-prueba',
            'services.correo.remitente'        => 'equipo@test.com',
            'services.correo.respuesta_min_ms' => 0,
        ]);
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '<abc@brevo>'], 201)]);
    }

    private function pedir(string $correo = 'ana@test.com')
    {
        return $this->postJson('/api/clave/olvide', ['email' => $correo]);
    }

    /** Código que salió en el último correo enviado a Brevo */
    private function codigoEnviado(): string
    {
        $codigo = null;
        Http::assertSent(function (PeticionHttp $r) use (&$codigo) {
            if (preg_match('/Tu código es: (\d{6})/u', (string) ($r['textContent'] ?? ''), $m)) {
                $codigo = $m[1];
            }
            return true;
        });
        $this->assertNotNull($codigo, 'No se envió ningún código');
        return $codigo;
    }

    private function restablecer(string $codigo, string $clave = 'NuevaClave2026', string $correo = 'ana@test.com')
    {
        return $this->postJson('/api/clave/restablecer', [
            'email' => $correo, 'codigo' => $codigo,
            'password' => $clave, 'password_confirmation' => $clave,
        ]);
    }

    public function test_flujo_completo_cambia_la_clave_y_cierra_todas_las_sesiones(): void
    {
        [$usuario, $token] = $this->usuarioConToken('ana@test.com');

        $this->pedir('  ANA@test.com ')->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(fn (PeticionHttp $r) => $r->url() === 'https://api.brevo.com/v3/smtp/email'
            && $r->hasHeader('api-key', 'xkeysib-prueba')
            && $r['to'][0]['email'] === 'ana@test.com'
            && $r['sender']['email'] === 'equipo@test.com');

        $this->restablecer($this->codigoEnviado())->assertOk()->assertJsonPath('ok', true);

        $usuario->refresh();
        $this->assertTrue(Hash::check('NuevaClave2026', $usuario->contrasena));
        $this->assertStringStartsWith('$argon2id$', $usuario->contrasena);
        $this->assertSame(0, $usuario->tokens()->count());
        $this->getJson('/api/sesion', $this->conToken($token))->assertStatus(401);

        $this->postJson('/api/login', ['email' => 'ana@test.com', 'password' => 'NuevaClave2026'])->assertOk();
        $this->postJson('/api/login', ['email' => 'ana@test.com', 'password' => 'ClaveSegura123'])->assertStatus(401);
    }

    public function test_el_codigo_no_se_guarda_en_claro(): void
    {
        $this->usuarioConToken('ana@test.com');
        $this->pedir()->assertOk();
        $codigo = $this->codigoEnviado();

        $fila = DB::table('recuperacion_clave')->first();
        $this->assertNotSame($codigo, $fila->codigo_hash);
        $this->assertSame(64, strlen($fila->codigo_hash));
    }

    public function test_misma_respuesta_si_el_correo_no_existe_y_no_se_envia_nada(): void
    {
        $this->usuarioConToken('ana@test.com');
        $existe = $this->pedir('ana@test.com')->assertOk()->json();
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);   // reinicia el registro de envíos
        $noExiste = $this->pedir('nadie@test.com')->assertOk()->json();

        $this->assertSame($existe, $noExiste);
        Http::assertNothingSent();
    }

    public function test_el_codigo_sirve_una_sola_vez(): void
    {
        $this->usuarioConToken('ana@test.com');
        $this->pedir()->assertOk();
        $codigo = $this->codigoEnviado();

        $this->restablecer($codigo)->assertOk();
        $this->restablecer($codigo, 'OtraClave2026x')->assertStatus(422)->assertJsonValidationErrors('codigo');
    }

    public function test_codigo_incorrecto_y_se_anula_a_los_5_fallos(): void
    {
        $this->usuarioConToken('ana@test.com');
        $this->pedir()->assertOk();
        $codigo = $this->codigoEnviado();
        $malo = $codigo === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->restablecer($malo)->assertStatus(422)->assertJsonValidationErrors('codigo');
        }
        // Ya ni el código correcto sirve: hay que pedir otro
        $this->restablecer($codigo)->assertStatus(422);
        $this->assertTrue(Hash::check('ClaveSegura123', Usuario::where('correo', 'ana@test.com')->value('contrasena')));
    }

    public function test_el_codigo_vence_a_los_15_minutos(): void
    {
        $this->usuarioConToken('ana@test.com');
        $this->pedir()->assertOk();
        $codigo = $this->codigoEnviado();

        $this->travel(16)->minutes();
        $this->restablecer($codigo)->assertStatus(422);
    }

    public function test_pedir_otro_codigo_anula_el_anterior_y_hay_espera_de_un_minuto(): void
    {
        $this->usuarioConToken('ana@test.com');
        $this->pedir()->assertOk();
        $primero = $this->codigoEnviado();

        // Antes de 60 s no se manda otro correo
        $this->pedir()->assertOk();
        $this->assertSame(1, DB::table('recuperacion_clave')->count());

        $this->travel(2)->minutes();
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);
        $this->pedir()->assertOk();
        $segundo = $this->codigoEnviado();

        if ($primero !== $segundo) {
            $this->restablecer($primero)->assertStatus(422);
        }
        $this->restablecer($segundo)->assertOk();
    }

    public function test_valida_la_nueva_contrasena_y_el_formato_del_codigo(): void
    {
        $this->usuarioConToken('ana@test.com');
        $this->restablecer('123456', 'debil')->assertStatus(422)->assertJsonValidationErrors('password');
        $this->restablecer('12ab', 'NuevaClave2026')->assertStatus(422)->assertJsonValidationErrors('codigo');
        $this->postJson('/api/clave/restablecer', [
            'email' => 'ana@test.com', 'codigo' => '123456',
            'password' => 'NuevaClave2026', 'password_confirmation' => 'Distinta2026x',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/api/clave/olvide', ['email' => "ana@test.com\r\nBcc: x@y.com"])
             ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_limite_de_pedidos_por_correo(): void
    {
        $this->usuarioConToken('ana@test.com');
        for ($i = 0; $i < 3; $i++) {
            $this->pedir()->assertOk();
        }
        $this->pedir()->assertStatus(429);
    }

    public function test_si_brevo_falla_la_respuesta_es_la_misma(): void
    {
        $this->usuarioConToken('ana@test.com');
        Http::fake(['api.brevo.com/*' => Http::response(['code' => 'unauthorized', 'message' => 'Key not found'], 401)]);

        $this->pedir()->assertOk()->assertJsonPath('ok', true);
    }

    public function test_sin_configurar_el_correo_responde_503_en_produccion(): void
    {
        config(['services.correo.brevo_key' => '']);
        $this->usuarioConToken('ana@test.com');

        $this->pedir()->assertStatus(503)->assertJsonStructure(['error']);
        $this->pedir('nadie@test.com')->assertStatus(503);
    }

    public function test_al_borrar_la_cuenta_se_borran_sus_codigos(): void
    {
        [$usuario] = $this->usuarioConToken('ana@test.com');
        $this->pedir()->assertOk();
        $this->assertSame(1, DB::table('recuperacion_clave')->count());

        $usuario->delete();
        $this->assertSame(0, DB::table('recuperacion_clave')->count());
    }

    public function test_con_apps_script_el_correo_sale_por_google_y_no_por_brevo(): void
    {
        config([
            'services.correo.appsscript_url'     => 'https://script.google.com/macros/s/ABC/exec',
            'services.correo.appsscript_secreto' => 'secreto-de-prueba',
        ]);
        Http::fake([
            'script.google.com/*' => Http::response(['ok' => true], 200),
            'api.brevo.com/*'     => Http::response([], 201),
        ]);
        $this->usuarioConToken('ana@test.com');

        $this->pedir()->assertOk();

        Http::assertSent(fn (PeticionHttp $r) => $r->url() === 'https://script.google.com/macros/s/ABC/exec'
            && $r['para'] === 'ana@test.com'
            && $r['secreto'] === 'secreto-de-prueba'
            && preg_match('/Tu código es: \d{6}/u', $r['texto']) === 1);
        Http::assertNotSent(fn (PeticionHttp $r) => str_contains($r->url(), 'brevo.com'));

        // El código que llegó por Apps Script sirve para cambiar la clave
        $codigo = null;
        Http::assertSent(function (PeticionHttp $r) use (&$codigo) {
            if (preg_match('/Tu código es: (\d{6})/u', (string) ($r['texto'] ?? ''), $m)) {
                $codigo = $m[1];
            }
            return true;
        });
        $this->postJson('/api/clave/restablecer', [
            'email' => 'ana@test.com', 'codigo' => $codigo,
            'password' => 'OtraClave2026', 'password_confirmation' => 'OtraClave2026',
        ])->assertOk();
    }

    public function test_si_apps_script_falla_la_respuesta_es_la_misma(): void
    {
        config([
            'services.correo.appsscript_url'     => 'https://script.google.com/macros/s/ABC/exec',
            'services.correo.appsscript_secreto' => 'secreto-de-prueba',
        ]);
        Http::fake(['script.google.com/*' => Http::response(['ok' => false, 'error' => 'secreto incorrecto'], 200)]);
        $this->usuarioConToken('ana@test.com');

        $this->pedir()->assertOk()->assertJsonPath('ok', true);
    }

    public function test_solo_apps_script_sin_brevo_tambien_cuenta_como_configurado(): void
    {
        config([
            'services.correo.brevo_key'          => '',
            'services.correo.appsscript_url'     => 'https://script.google.com/macros/s/ABC/exec',
            'services.correo.appsscript_secreto' => 'secreto-de-prueba',
        ]);
        Http::fake(['script.google.com/*' => Http::response(['ok' => true], 200)]);
        $this->usuarioConToken('ana@test.com');

        $this->pedir()->assertOk();
    }

    public function test_si_apps_script_falla_y_hay_brevo_el_codigo_sale_por_brevo(): void
    {
        config([
            'services.correo.appsscript_url'     => 'https://script.google.com/macros/s/ABC/exec',
            'services.correo.appsscript_secreto' => 'secreto-de-prueba',
        ]);
        Http::fake([
            'script.google.com/*' => Http::response(['ok' => false, 'error' => 'Service invoked too many times for one day: email.'], 200),
            'api.brevo.com/*'     => Http::response(['messageId' => '<x@brevo>'], 201),
        ]);
        $this->usuarioConToken('ana@test.com');

        $this->pedir()->assertOk();
        $this->restablecer($this->codigoEnviado())->assertOk();   // el código que llegó por Brevo sirve
    }

    public function test_el_comando_de_diagnostico_muestra_el_limite_y_el_error(): void
    {
        // (el Http::fake de setUp responde 201 para Brevo: aquí se prueba con solo Apps Script, que falla)
        config(['services.correo.brevo_key' => '',
                'services.correo.appsscript_url' => 'https://script.google.com/macros/s/ABC/exec',
                'services.correo.appsscript_secreto' => 'secreto-de-prueba']);
        Http::fake(['script.google.com/*' => Http::response(['ok' => false, 'error' => 'Service invoked too many times for one day: email.'], 200)]);
        $this->usuarioConToken('ana@test.com');
        for ($i = 0; $i < 5; $i++) {
            \Illuminate\Support\Facades\DB::table('recuperacion_clave')->insert(['id_usuario' => \App\Models\Usuario::first()->id_usuario,
                'codigo_hash' => str_repeat('a', 64), 'intentos' => 0, 'fecha_creacion' => now()->subMinutes(10 + $i)->format('Y-m-d H:i:s'),
                'fecha_expira' => now()->format('Y-m-d H:i:s')]);
        }
        $this->artisan('migrasense:probar-correo ana@test.com')
            ->expectsOutputToContain('Google Apps Script')
            ->expectsOutputToContain('LÍMITE ALCANZADO')
            ->expectsOutputToContain('too many times')
            ->assertFailed();
    }
}
