<?php

namespace Tests\Feature;

use App\Services\RecordatorioEnvioService;
use App\Support\WebPush;
use Carbon\Carbon;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Recordatorios que llegan aunque la app esté cerrada: Web Push, despertador y resumen semanal. */
class RecordatoriosPushTest extends TestCase
{
    private const FCM = 'https://fcm.googleapis.com/fcm/send/abc123:XYZ';

    protected function setUp(): void
    {
        parent::setUp();
        $c = WebPush::generarClaves();
        config([
            'services.push.publica' => $c['publica'], 'services.push.privada' => $c['privada'],
            'services.push.sujeto' => 'mailto:equipo@test.com', 'services.cron.clave' => 'clave-del-cron',
            'services.correo.brevo_key' => 'xkeysib-prueba', 'services.correo.remitente' => 'equipo@test.com',
            'services.correo.appsscript_url' => '', 'services.correo.appsscript_secreto' => '',
        ]);
    }

    /** Suscripción con claves reales del "navegador" (para que el cifrado funcione) */
    private function suscripcion(string $endpoint = self::FCM): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => WebPush::generarClaves()['publica'], 'auth' => WebPush::b64u(random_bytes(16))]];
    }

    private function usuarioConRecordatorios(string $diaria = '20:00', int $dia = 0, string $semanal = '19:00'): array
    {
        [$u, $t] = $this->usuarioConToken();
        DB::table('recordatorio')->where('id_usuario', $u->id_usuario)->delete();
        DB::table('recordatorio')->insert([
            ['id_usuario' => $u->id_usuario, 'frecuencia' => 'diaria', 'dia_semana' => null, 'hora_configurada' => $diaria.':00', 'activo' => 1],
            ['id_usuario' => $u->id_usuario, 'frecuencia' => 'semanal', 'dia_semana' => $dia, 'hora_configurada' => $semanal.':00', 'activo' => 1],
        ]);
        return [$u, $t];
    }

    private function correr(string $cuando): array
    {
        return app(RecordatorioEnvioService::class)->ejecutar(Carbon::parse($cuando, 'America/La_Paz'));
    }

    private function pushes(): int
    {
        return Http::recorded(fn (PeticionHttp $r) => str_starts_with($r->url(), 'https://fcm.googleapis.com'))->count();
    }

    private function correos(): \Illuminate\Support\Collection
    {
        return Http::recorded(fn (PeticionHttp $r) => str_contains($r->url(), 'brevo.com'))->map(fn ($p) => $p[0]);
    }

    /* ---------- Suscripciones ---------- */

    public function test_suscribirse_y_quitar_solo_lo_propio(): void
    {
        [, $ana] = $this->usuarioConToken();
        [, $beto] = $this->usuarioConToken();
        $this->postJson('/api/push/suscripcion', $this->suscripcion(), $this->conToken($ana))->assertStatus(201)->assertJsonPath('dispositivos', 1);
        $this->getJson('/api/push/clave', $this->conToken($ana))->assertOk()->assertJsonPath('configurado', true)
             ->assertJsonPath('clave', config('services.push.publica'));

        $this->deleteJson('/api/push/suscripcion', ['endpoint' => self::FCM], $this->conToken($beto))->assertOk();
        $this->assertSame(1, DB::table('suscripcion_push')->count());   // Beto no puede borrar la de Ana
        $this->deleteJson('/api/push/suscripcion', ['endpoint' => self::FCM], $this->conToken($ana))->assertOk();
        $this->assertSame(0, DB::table('suscripcion_push')->count());
    }

    public function test_solo_acepta_servicios_de_push_reales(): void
    {
        [, $t] = $this->usuarioConToken();
        foreach (['http://fcm.googleapis.com/x', 'https://127.0.0.1/x', 'https://fcm.googleapis.com.evil.com/x', 'https://mi-servidor.com/x'] as $mal) {
            $this->postJson('/api/push/suscripcion', $this->suscripcion($mal), $this->conToken($t))->assertStatus(422);
        }
        $this->postJson('/api/push/suscripcion', ['endpoint' => self::FCM, 'keys' => ['p256dh' => 'corta', 'auth' => 'x']], $this->conToken($t))
             ->assertStatus(422)->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
        foreach (['https://updates.push.services.mozilla.com/wpush/v2/abc', 'https://web.push.apple.com/abc', 'https://wns2-par02p.notify.windows.com/w/?token=abc'] as $bien) {
            $this->postJson('/api/push/suscripcion', $this->suscripcion($bien), $this->conToken($t))->assertStatus(201);
        }
        $this->app['auth']->forgetGuards();   // el entorno de pruebas recuerda la sesión entre pedidos
        $this->postJson('/api/push/suscripcion', $this->suscripcion())->assertStatus(401);
    }

    public function test_aviso_de_prueba_cifrado_con_vapid_y_limpia_suscripciones_vencidas(): void
    {
        Http::fake([
            'fcm.googleapis.com/fcm/send/vieja*' => Http::response('', 410),
            'fcm.googleapis.com/*' => Http::response('', 201),
        ]);
        [, $t] = $this->usuarioConToken();
        $this->postJson('/api/push/suscripcion', $this->suscripcion(), $this->conToken($t))->assertStatus(201);
        $this->postJson('/api/push/suscripcion', $this->suscripcion('https://fcm.googleapis.com/fcm/send/vieja'), $this->conToken($t))->assertStatus(201);

        $this->postJson('/api/push/prueba', [], $this->conToken($t))->assertOk()->assertJsonPath('enviados', 1);

        Http::assertSent(fn (PeticionHttp $r) => $r->url() === self::FCM
            && str_starts_with($r->header('Authorization')[0], 'vapid t=')
            && str_contains($r->header('Authorization')[0], 'k='.config('services.push.publica'))
            && $r->header('Content-Encoding')[0] === 'aes128gcm'
            && ! str_contains($r->body(), 'Avisos activados'));          // el texto viaja cifrado
        $this->assertSame(1, DB::table('suscripcion_push')->count());   // la vencida (410) se borró
    }

    /* ---------- Despertador ---------- */

    public function test_el_despertador_exige_su_clave(): void
    {
        $this->postJson('/api/tareas/recordatorios')->assertStatus(403);
        $this->postJson('/api/tareas/recordatorios', [], ['X-Cron-Key' => 'otra'])->assertStatus(403);
        $this->postJson('/api/tareas/recordatorios', [], ['X-Cron-Key' => 'clave-del-cron'])->assertOk()->assertJsonPath('ok', true);
        config(['services.cron.clave' => '']);
        $this->postJson('/api/tareas/recordatorios', [], ['X-Cron-Key' => ''])->assertStatus(403);   // sin clave configurada, nadie entra
    }

    /* ---------- Recordatorio diario ---------- */

    public function test_diario_llega_a_su_hora_y_una_sola_vez(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201), 'api.brevo.com/*' => Http::response([], 201)]);
        [, $t] = $this->usuarioConRecordatorios('20:00');
        $this->postJson('/api/push/suscripcion', $this->suscripcion(), $this->conToken($t))->assertStatus(201);
        Carbon::setTestNow('2026-10-14 19:50:00');

        $this->assertSame(0, $this->correr('2026-10-14 19:50')['diarios']);   // antes de la hora: nada
        $this->assertSame(1, $this->correr('2026-10-14 20:05')['diarios']);
        $this->assertSame(0, $this->correr('2026-10-14 20:20')['diarios']);   // no se repite el mismo día
        $this->assertSame(1, $this->pushes());
        $this->assertSame(0, $this->correos()->count());                       // tiene dispositivo: no gasta correo
        $this->assertSame(1, $this->correr('2026-10-15 20:10')['diarios']);   // al día siguiente, otra vez
        $this->assertSame(0, $this->correr('2026-10-16 23:30')['diarios']);   // más de 3 h tarde: ya no
        Carbon::setTestNow();
    }

    public function test_diario_no_molesta_si_ya_registro_hoy(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201)]);
        [, $t] = $this->usuarioConRecordatorios('20:00');
        $this->postJson('/api/push/suscripcion', $this->suscripcion(), $this->conToken($t));
        $this->postJson('/api/registros/prodromico', ['intensidad' => 4, 'sintomas' => ['luz'],
            'inicio' => Carbon::now()->subMinutes(30)->format('Y-m-d\TH:i')], $this->conToken($t))->assertStatus(201);

        $r = $this->correr(Carbon::now('America/La_Paz')->setTime(20, 5)->format('Y-m-d H:i'));
        $this->assertSame(1, $r['omitidos']);
        $this->assertSame(0, $this->pushes());
    }

    public function test_diario_sin_dispositivos_llega_por_correo(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'x'], 201)]);
        [$u] = $this->usuarioConRecordatorios('08:30');
        $this->correr('2026-10-14 08:31');
        $c = $this->correos();
        $this->assertCount(1, $c);
        $this->assertSame($u->correo, $c[0]['to'][0]['email']);
        $this->assertStringContainsString('¿Cómo te fue hoy?', $c[0]['subject']);
        $this->assertStringContainsString('/registro.html', $c[0]['htmlContent']);
        $this->assertStringContainsString('correo', DB::table('envio_recordatorio')->value('resultado'));
    }

    public function test_recordatorio_apagado_no_se_envia(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);
        [$u] = $this->usuarioConRecordatorios('20:00');
        DB::table('recordatorio')->where('id_usuario', $u->id_usuario)->update(['activo' => 0]);
        $this->assertSame(0, $this->correr('2026-10-14 20:05')['diarios']);
        $this->assertSame(0, $this->correos()->count());
    }

    /* ---------- Resumen semanal ---------- */

    public function test_resumen_semanal_con_pdf_adjunto_y_aviso(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201), 'api.brevo.com/*' => Http::response(['messageId' => 'x'], 201)]);
        // 18/10/2026 es domingo (0)
        [$u, $t] = $this->usuarioConRecordatorios('20:00', 0, '19:00');
        $this->postJson('/api/push/suscripcion', $this->suscripcion(), $this->conToken($t));
        $this->postJson('/api/registros/episodio', ['intensidad' => 7, 'duracion' => '4_24h', 'inicio' => '2026-10-15T09:00',
            'sintomas' => ['luz', 'auras'], 'disparadores' => ['estres']], $this->conToken($t))->assertStatus(201);
        Carbon::setTestNow(Carbon::parse('2026-10-18 19:02', 'America/La_Paz'));

        $this->assertSame(0, $this->correr('2026-10-17 19:05')['semanales']);   // sábado: no toca
        $this->assertSame(1, $this->correr('2026-10-18 19:05')['semanales']);
        $this->assertSame(0, $this->correr('2026-10-18 19:20')['semanales']);   // una vez por semana
        Carbon::setTestNow();

        $correo = $this->correos()->first(fn ($c) => str_contains($c['subject'], 'resumen semanal'));
        $this->assertNotNull($correo);
        $this->assertSame($u->correo, $correo['to'][0]['email']);
        $pdf = base64_decode($correo['attachment'][0]['content']);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringEndsWith('.pdf', $correo['attachment'][0]['name']);
        $this->assertStringContainsString('1 migraña', $correo['htmlContent']);
        $this->assertGreaterThanOrEqual(1, $this->pushes());
        $this->assertStringContainsString('correo con PDF', DB::table('envio_recordatorio')->where('tipo', 'semanal')->value('resultado'));
    }

    public function test_comando_de_claves_y_de_envio_manual(): void
    {
        $this->artisan('migrasense:claves-push')->expectsOutputToContain('VAPID_PUBLIC_KEY=')->assertSuccessful();
        $this->artisan('migrasense:enviar-recordatorios --ahora="2026-10-14 03:00"')->expectsOutputToContain('Diarios enviados: 0')->assertSuccessful();
        $this->artisan('migrasense:enviar-recordatorios --historial')->assertSuccessful();
    }
}
