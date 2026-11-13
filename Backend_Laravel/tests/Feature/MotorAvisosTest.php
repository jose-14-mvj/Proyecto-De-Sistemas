<?php

namespace Tests\Feature;

use App\Services\AlertaMigranaService;
use App\Services\RecordatorioEnvioService;
use App\Support\WebPush;
use Carbon\Carbon;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Motor de avisos independiente: alerta de migraña enviada por el servidor (con probabilidad
 * y horas), renovación de la suscripción del navegador y respaldo del correo semanal.
 */
class MotorAvisosTest extends TestCase
{
    private const FCM = 'https://fcm.googleapis.com/fcm/send/abc123:XYZ';
    private const ML = 'https://ml-prueba.example';

    protected function setUp(): void
    {
        parent::setUp();
        $c = WebPush::generarClaves();
        config([
            'services.push.publica' => $c['publica'], 'services.push.privada' => $c['privada'],
            'services.push.sujeto' => 'mailto:equipo@test.com',
            'services.correo.brevo_key' => 'xkeysib-prueba', 'services.correo.remitente' => 'equipo@test.com',
            'services.correo.appsscript_url' => '', 'services.correo.appsscript_secreto' => '',
            'services.ml.url' => self::ML, 'services.ml.key' => 'clave-ml',
            'services.ml.reintentos' => 2, 'services.ml.pausa_ms' => 0, 'services.ml.auto_reentrenar' => false,
            'services.avisos.silencio' => '',
        ]);
    }

    private function ml(float $p, array $factores = []): array
    {
        return ['ok' => true, 'probabilidad' => $p, 'porcentaje' => round($p * 100, 1),
                'nivel' => $p >= 0.7 ? 'muy_alto' : 'bajo', 'mensaje' => 'Mensaje del ML',
                'ventana_horas' => 48, 'confianza' => 'media', 'factores' => $factores, 'modelo_version' => '2.0.0'];
    }

    private function falso(float $p, array $factores = [], int $push = 201): void
    {
        Http::fake([
            'ml-prueba.example/*' => Http::response($this->ml($p, $factores)),
            'fcm.googleapis.com/*' => Http::response('', $push),
            'api.brevo.com/*' => Http::response(['messageId' => 'x'], 201),
        ]);
    }

    private function suscripcion(string $endpoint = self::FCM): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => WebPush::generarClaves()['publica'], 'auth' => WebPush::b64u(random_bytes(16))]];
    }

    /** Usuario con un síntoma prodrómico de hace $horas horas y (opcional) un dispositivo */
    private function persona(float $horas = 3, bool $dispositivo = true): array
    {
        [$u, $t] = $this->usuarioConToken();
        $this->postJson('/api/registros/prodromico', ['intensidad' => 6, 'sintomas' => ['luz'],
            'inicio' => Carbon::now()->subMinutes((int) ($horas * 60))->format('Y-m-d\TH:i')], $this->conToken($t))->assertStatus(201);
        if ($dispositivo) {
            $this->postJson('/api/push/suscripcion', $this->suscripcion(), $this->conToken($t))->assertStatus(201);
        }
        return [$u, $t];
    }

    private function correr(): array
    {
        return app(AlertaMigranaService::class)->ejecutar();
    }

    private function pushes(): int
    {
        return Http::recorded(fn (PeticionHttp $r) => str_starts_with($r->url(), 'https://fcm.googleapis.com'))->count();
    }

    private function correos(): \Illuminate\Support\Collection
    {
        return Http::recorded(fn (PeticionHttp $r) => str_contains($r->url(), 'brevo.com'))->map(fn ($p) => $p[0])->values();
    }

    /* ---------- Alerta de posible migraña ---------- */

    public function test_alerta_llega_con_la_app_cerrada_e_incluye_probabilidad_y_horas(): void
    {
        $this->falso(0.85);
        [$u] = $this->persona(3);

        $r = $this->correr();
        $this->assertSame(1, $r['alertas']);
        $this->assertSame(1, $r['revisados']);
        $this->assertSame(1, $this->pushes());
        $envio = DB::table('envio_recordatorio')->where('id_usuario', $u->id_usuario)->where('tipo', 'alerta')->first();
        $this->assertStringContainsString('85 %', $envio->resultado);
        $this->assertStringContainsString('push a 1', $envio->resultado);

        // El texto que ve la persona: probabilidad + en cuántas horas (12 h de referencia − 3 h ya pasadas → de 4 a 14 h)
        $a = app(AlertaMigranaService::class)->evaluar($u->id_usuario, $this->ml(0.85));
        $this->assertSame(85, $a['porcentaje']);
        $this->assertSame('Posible migraña · 85 % de probabilidad', $a['titulo']);
        $this->assertStringContainsString('En 4 a 14 horas', $a['cuerpo']);
        $this->assertStringContainsString('probabilidad 85 %', $a['cuerpo']);
        $push = AlertaMigranaService::avisoPush($a);
        $this->assertSame('registro.html?tab=prodromico', $push['url']);
        $this->assertSame('migrasense-alerta', $push['tag']);
    }

    public function test_alerta_no_se_repite_aunque_el_motor_corra_cada_5_minutos(): void
    {
        $this->falso(0.9);
        [, $t] = $this->persona(2);
        $this->assertSame(1, $this->correr()['alertas']);
        $this->assertSame(0, $this->correr()['alertas']);                 // misma vuelta repetida
        // Registra otro síntoma: se vuelve a revisar, pero no pasa de una alerta cada 12 h
        $this->postJson('/api/registros/prodromico', ['intensidad' => 4, 'sintomas' => ['cervical']], $this->conToken($t))->assertStatus(201);
        DB::table('registro_sintoma')->update(['fecha_creacion' => Carbon::now()->addMinute()->format('Y-m-d H:i:s')]);
        $r = $this->correr();
        $this->assertSame(1, $r['revisados']);
        $this->assertSame(0, $r['alertas']);
        $this->assertSame(1, $this->pushes());
    }

    public function test_probabilidad_baja_o_aviso_apagado_no_alerta(): void
    {
        $this->falso(0.55);
        $this->persona(2);
        $r = $this->correr();
        $this->assertSame(['alertas' => 0, 'revisados' => 1], ['alertas' => $r['alertas'], 'revisados' => $r['revisados']]);

        $this->falso(0.95);
        [$u, $t] = $this->persona(2);
        $this->putJson('/api/recordatorios', ['alerta' => ['on' => false]], $this->conToken($t))->assertOk();
        DB::table('envio_recordatorio')->delete();
        $this->correr();
        $this->assertSame(0, DB::table('envio_recordatorio')->where('id_usuario', $u->id_usuario)->count());   // ni siquiera se le revisa
        $this->assertSame(0, $this->pushes());
    }

    public function test_probabilidad_alta_sin_senal_reciente_no_alerta_pero_con_ritmo_si(): void
    {
        [$u, $t] = $this->usuarioConToken();
        $this->postJson('/api/registros/episodio', ['intensidad' => 7, 'duracion' => '4_24h', 'sintomas' => ['luz'],
            'inicio' => Carbon::now()->subDays(9)->format('Y-m-d\TH:i')], $this->conToken($t))->assertStatus(201);
        $servicio = app(AlertaMigranaService::class);

        $this->assertNull($servicio->evaluar($u->id_usuario, $this->ml(0.8)));
        $a = $servicio->evaluar($u->id_usuario, $this->ml(0.8, [['factor' => 'ciclo', 'texto' => 'Ya podría tocar', 'efecto_puntos' => 12]]));
        $this->assertStringContainsString('En 6 a 24 horas', $a['cuerpo']);
        $this->assertStringStartsWith('r', $a['clave']);
    }

    public function test_las_horas_usan_el_historial_de_la_persona(): void
    {
        // Dos veces el dolor llegó 8 h después del síntoma → centro 8 h, margen 3,2 h
        $datos = ['episodios' => [['inicio' => '2026-09-01T16:00:00'], ['inicio' => '2026-09-10T18:00:00']],
                  'registros' => [['fecha' => '2026-09-01T08:00:00'], ['fecha' => '2026-09-10T10:00:00'], ['fecha' => '2026-09-20T10:00:00']]];
        $this->assertSame(8.0, AlertaMigranaService::horasTipicas($datos));
        $this->assertNull(AlertaMigranaService::horasTipicas(['episodios' => [], 'registros' => $datos['registros']]));
    }

    public function test_sin_dispositivos_la_alerta_llega_por_correo(): void
    {
        $this->falso(0.82);
        [$u] = $this->persona(1, false);
        $this->assertSame(1, $this->correr()['alertas']);
        $c = $this->correos();
        $this->assertCount(1, $c);
        $this->assertSame($u->correo, $c[0]['to'][0]['email']);
        $this->assertStringContainsString('82 %', $c[0]['subject']);
        $this->assertStringContainsString('registro.html?tab=prodromico', $c[0]['htmlContent']);
    }

    public function test_si_el_ml_esta_dormido_no_falla_y_se_reintenta_en_la_siguiente_vuelta(): void
    {
        Http::fake([
            'ml-prueba.example/*' => Http::sequence()->push('', 503)->push('', 503)->push($this->ml(0.9)),   // 2 intentos dormido; luego despierta
            'fcm.googleapis.com/*' => Http::response('', 201),
        ]);
        [$u] = $this->persona(2);
        $r = $this->correr();
        $this->assertSame(0, $r['alertas']);
        $this->assertSame(1, $r['errores']);
        $this->assertStringContainsString('se reintenta', $r['estado']);
        $this->assertSame(0, DB::table('envio_recordatorio')->where('id_usuario', $u->id_usuario)->count());   // no quedó marcada: se reintenta

        $this->assertSame(1, $this->correr()['alertas']);                   // siguiente vuelta: el ML ya despertó
    }

    public function test_horario_de_descanso_no_envia_alertas(): void
    {
        $this->falso(0.9);
        $this->persona(2);
        config(['services.avisos.silencio' => '23-6']);
        $servicio = app(AlertaMigranaService::class);
        $this->assertSame(0, $servicio->ejecutar(Carbon::now('America/La_Paz')->setTime(2, 30))['revisados']);
        $this->assertSame(0, $servicio->ejecutar(Carbon::now('America/La_Paz')->setTime(23, 10))['revisados']);
        $this->assertSame(0, $this->pushes());
    }

    /* ---------- Comando del motor (lo que corre GitHub Actions) ---------- */

    public function test_el_comando_envia_recordatorios_y_alertas_sin_mostrar_datos_personales(): void
    {
        $this->falso(0.88);
        [$u] = $this->persona(2);
        $this->artisan('migrasense:enviar-recordatorios')
            ->expectsOutputToContain('Alertas de migraña enviadas: 1')
            ->doesntExpectOutputToContain($u->correo)
            ->assertExitCode(0);
        $this->artisan('migrasense:enviar-recordatorios --sin-alertas')->doesntExpectOutputToContain('Alertas')->assertExitCode(0);
    }

    public function test_despertador_de_respaldo_tambien_revisa_alertas(): void
    {
        $this->falso(0.9);
        $this->persona(2);
        config(['services.cron.clave' => 'clave-del-cron']);
        $this->postJson('/api/tareas/recordatorios', [], ['X-Cron-Key' => 'clave-del-cron'])->assertOk()->assertJsonPath('alertas', 1);
    }

    /* ---------- El aviso diario abre el registro de síntomas prodrómicos ---------- */

    public function test_correo_del_recordatorio_diario_lleva_al_registro_prodromico(): void
    {
        $this->falso(0.1);
        [$u] = $this->usuarioConToken();
        DB::table('recordatorio')->where('id_usuario', $u->id_usuario)->delete();
        DB::table('recordatorio')->insert(['id_usuario' => $u->id_usuario, 'frecuencia' => 'diaria', 'dia_semana' => null, 'hora_configurada' => '08:30:00', 'activo' => 1]);
        app(RecordatorioEnvioService::class)->ejecutar(Carbon::parse('2026-10-14 08:31', 'America/La_Paz'));
        $this->assertStringContainsString('/registro.html?tab=prodromico', $this->correos()[0]['htmlContent']);
    }

    /* ---------- Resumen semanal: si el adjunto falla, el correo sale igual ---------- */

    public function test_semanal_si_el_pdf_adjunto_es_rechazado_se_envia_sin_pdf(): void
    {
        Http::fake(['api.brevo.com/*' => Http::sequence()->push(['message' => 'attachment rejected'], 400)->push(['messageId' => 'x'], 201)]);
        [$u] = $this->usuarioConToken();
        DB::table('recordatorio')->where('id_usuario', $u->id_usuario)->delete();
        DB::table('recordatorio')->insert(['id_usuario' => $u->id_usuario, 'frecuencia' => 'semanal', 'dia_semana' => 0, 'hora_configurada' => '19:00:00', 'activo' => 1]);

        $r = app(RecordatorioEnvioService::class)->ejecutar(Carbon::parse('2026-10-18 19:05', 'America/La_Paz'));   // domingo
        $this->assertSame(1, $r['semanales']);
        $c = $this->correos();
        $this->assertCount(2, $c);
        $this->assertNotEmpty($c[0]['attachment']);
        $this->assertArrayNotHasKey('attachment', $c[1]->data());
        $this->assertStringContainsString('Historial', $c[1]['htmlContent']);
        $this->assertStringContainsString('sin PDF', DB::table('envio_recordatorio')->where('tipo', 'semanal')->value('resultado'));
    }

    /* ---------- El navegador renovó su suscripción (Service Worker, sin sesión) ---------- */

    public function test_renovar_suscripcion_conserva_la_cuenta(): void
    {
        $this->falso(0.1);
        [$u, $t] = $this->usuarioConToken();
        $vieja = $this->suscripcion();
        $this->postJson('/api/push/suscripcion', $vieja, $this->conToken($t))->assertStatus(201);
        $this->app['auth']->forgetGuards();

        $nueva = $this->suscripcion('https://fcm.googleapis.com/fcm/send/NUEVA:123');
        $this->postJson('/api/push/renovar', $nueva + ['anterior' => $vieja['endpoint']])->assertOk()->assertJsonPath('ok', true);
        $filas = DB::table('suscripcion_push')->get();
        $this->assertCount(1, $filas);
        $this->assertSame($u->id_usuario, (int) $filas[0]->id_usuario);
        $this->assertSame($nueva['endpoint'], $filas[0]->endpoint);

        // Quien no conoce la dirección anterior no puede colgarse de ninguna cuenta
        $this->postJson('/api/push/renovar', $this->suscripcion('https://fcm.googleapis.com/fcm/send/OTRA') + ['anterior' => 'https://fcm.googleapis.com/fcm/send/inventada'])
             ->assertStatus(404);
        $this->postJson('/api/push/renovar', $nueva)->assertStatus(422);                                    // falta "anterior"
        $this->postJson('/api/push/renovar', ['endpoint' => 'https://malo.example/x', 'anterior' => $nueva['endpoint'],
            'keys' => $nueva['keys']])->assertStatus(422);                                                 // solo servicios de push reales
        $this->assertCount(1, DB::table('suscripcion_push')->get());
    }
}
