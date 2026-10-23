<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Aprendizaje continuo del ML: guardar lo aprendido en la BD y no perderlo nunca. */
class AprendizajeTest extends TestCase
{
    private const ML = 'https://ml-prueba.example';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ml.url' => self::ML, 'services.ml.key' => 'clave-ml', 'services.ml.reintentos' => 1,
                'services.ml.pausa_ms' => 0, 'services.ml.reentrenar_minimo' => 2, 'services.ml.reentrenar_horas' => 24]);
    }

    private function prediccion(string $version = '2.0.0', float $p = 0.3): array
    {
        return ['ok' => true, 'probabilidad' => $p, 'porcentaje' => $p * 100, 'nivel' => 'moderado', 'mensaje' => 'm',
                'ventana_horas' => 48, 'confianza' => 'media', 'factores' => [], 'modelo_version' => $version];
    }

    private function conRegistros(string $token, int $n = 2): void
    {
        for ($i = 0; $i < $n; $i++) {
            $this->postJson('/api/registros/prodromico', ['intensidad' => 5, 'sintomas' => ['luz'],
                'inicio' => Carbon::now()->subHours(5 + $i)->format('Y-m-d\TH:i')], $this->conToken($token))->assertStatus(201);
        }
    }

    private function modeloActivo(string $version, string $bin = 'modelo-binario'): int
    {
        return DB::table('modelo_ml')->insertGetId(['version' => $version, 'estado' => 'activo', 'auc' => 0.88,
            'archivo' => base64_encode($bin), 'sha256' => hash('sha256', $bin), 'tamano_bytes' => strlen($bin),
            'registros_usados' => 0, 'fecha_inicio' => Carbon::now()->subDay()->format('Y-m-d H:i:s')], 'id_modelo');
    }

    public function test_si_el_ml_reinicio_le_devuelve_lo_aprendido_desde_la_bd(): void
    {
        $this->modeloActivo('2.0.0-r1');
        Http::fake([
            self::ML.'/predecir' => Http::sequence()->push($this->prediccion('2.0.0'))->push($this->prediccion('2.0.0-r1', 0.6)),
            self::ML.'/modelo/cargar' => Http::response(['ok' => true, 'version' => '2.0.0-r1']),
        ]);
        [, $t] = $this->usuarioConToken();

        $this->getJson('/api/prediccion', $this->conToken($t))->assertOk()
             ->assertJsonPath('prediccion.modelo_version', '2.0.0-r1')
             ->assertJsonPath('prediccion.probabilidad', 0.6);

        Http::assertSent(fn (PeticionHttp $r) => $r->url() === self::ML.'/modelo/cargar'
            && $r->hasHeader('X-API-Key', 'clave-ml')
            && $r->hasHeader('X-Sha256', hash('sha256', 'modelo-binario'))
            && $r->body() === base64_encode('modelo-binario'));
    }

    public function test_si_el_ml_ya_tiene_el_modelo_no_se_reenvia(): void
    {
        $this->modeloActivo('2.0.0-r1');
        Http::fake([self::ML.'/predecir' => Http::response($this->prediccion('2.0.0-r1'))]);
        [, $t] = $this->usuarioConToken();
        $this->getJson('/api/prediccion', $this->conToken($t))->assertOk();
        Http::assertSentCount(1);
    }

    public function test_si_falla_la_restauracion_la_prediccion_igual_funciona(): void
    {
        $this->modeloActivo('2.0.0-r1');
        Http::fake([
            self::ML.'/predecir' => Http::response($this->prediccion('2.0.0', 0.25)),
            self::ML.'/modelo/cargar' => Http::response(['detail' => 'roto'], 500),
        ]);
        [, $t] = $this->usuarioConToken();
        $this->getJson('/api/prediccion', $this->conToken($t))->assertOk()->assertJsonPath('prediccion.probabilidad', 0.25);
    }

    public function test_con_suficientes_registros_nuevos_empieza_a_aprender_solo_y_sin_datos_personales(): void
    {
        Http::fake([
            self::ML.'/predecir' => Http::response($this->prediccion()),
            self::ML.'/reentrenar' => Http::response(['ok' => true, 'mensaje' => 'iniciado'], 202),
        ]);
        [, $t] = $this->usuarioConToken('privado@test.com');
        $this->conRegistros($t, 2);

        $this->getJson('/api/prediccion', $this->conToken($t))->assertOk();

        $fila = DB::table('modelo_ml')->first();
        $this->assertSame('en_curso', $fila->estado);
        $this->assertSame(2, (int) $fila->registros_usados);
        Http::assertSent(fn (PeticionHttp $r) => $r->url() === self::ML.'/reentrenar'
            && count($r['pacientes']) === 1 && $r['simulados'] === 200
            && ! str_contains($r->body(), 'privado@test.com') && ! str_contains($r->body(), 'Prueba'));
    }

    public function test_no_reentrena_mas_de_una_vez_al_dia_ni_sin_datos_nuevos(): void
    {
        DB::table('modelo_ml')->insert(['estado' => 'rechazado', 'registros_usados' => 0,
            'fecha_inicio' => Carbon::now()->subHours(3)->format('Y-m-d H:i:s')]);
        Http::fake([self::ML.'/predecir' => Http::response($this->prediccion())]);
        [, $t] = $this->usuarioConToken();
        $this->conRegistros($t, 3);

        $this->getJson('/api/prediccion', $this->conToken($t))->assertOk();
        Http::assertNotSent(fn (PeticionHttp $r) => str_contains($r->url(), '/reentrenar'));
    }

    public function test_si_el_modelo_nuevo_pasa_el_filtro_se_guarda_en_la_bd_como_activo(): void
    {
        $anterior = $this->modeloActivo('2.0.0-r1', 'viejo');
        $id = DB::table('modelo_ml')->insertGetId(['estado' => 'en_curso', 'registros_usados' => 30,
            'fecha_inicio' => Carbon::now()->subMinutes(5)->format('Y-m-d H:i:s')], 'id_modelo');
        Http::fake([
            self::ML.'/reentrenar/estado' => Http::response(['estado' => 'terminado', 'aceptado' => true, 'motivo' => 'Aceptado: AUC 0.891',
                'version' => '2.0.0-r2', 'auc_personas_nuevas' => 0.891, 'auc_anterior' => 0.883, 'pacientes_reales' => 9, 'filas_reales' => 700]),
            self::ML.'/modelo/descargar' => Http::response('nuevo-modelo', 200,
                ['X-Sha256' => hash('sha256', 'nuevo-modelo'), 'X-Modelo-Version' => '2.0.0-r2']),
            self::ML.'/metricas' => Http::response(['version' => '2.0.0-r2', 'datos' => ['pacientes_reales' => 9],
                'lo_que_mas_pesa' => [['caracteristica' => 'tasa_mensual_personal', 'importancia' => 0.15]]]),
        ]);

        $fila = app(\App\Services\PrediccionService::class)->revisarReentreno();
        $this->assertSame('tasa_mensual_personal', json_decode($fila->reporte, true)['lo_que_mas_pesa'][0]['caracteristica']);

        $this->assertSame('activo', $fila->estado);
        $this->assertSame('2.0.0-r2', $fila->version);
        $this->assertSame(base64_encode('nuevo-modelo'), $fila->archivo);
        $this->assertSame(9, (int) $fila->pacientes_reales);
        $this->assertSame('reemplazado', DB::table('modelo_ml')->where('id_modelo', $anterior)->value('estado'));
        $this->assertSame(1, DB::table('modelo_ml')->where('estado', 'activo')->count());
        $this->assertSame($id, (int) $fila->id_modelo);
    }

    public function test_si_el_modelo_nuevo_acierta_peor_se_rechaza_y_sigue_el_anterior(): void
    {
        $anterior = $this->modeloActivo('2.0.0-r1');
        DB::table('modelo_ml')->insert(['estado' => 'en_curso', 'registros_usados' => 30,
            'fecha_inicio' => Carbon::now()->subMinutes(5)->format('Y-m-d H:i:s')]);
        Http::fake([self::ML.'/reentrenar/estado' => Http::response(['estado' => 'terminado', 'aceptado' => false,
            'motivo' => 'Acierta peor que el actual', 'version' => '2.0.0-r2', 'auc_personas_nuevas' => 0.80])]);

        $fila = app(\App\Services\PrediccionService::class)->revisarReentreno();

        $this->assertSame('rechazado', $fila->estado);
        $this->assertNull($fila->archivo);
        $this->assertSame('activo', DB::table('modelo_ml')->where('id_modelo', $anterior)->value('estado'));
        Http::assertNotSent(fn (PeticionHttp $r) => str_contains($r->url(), '/modelo/descargar'));
    }

    public function test_descarga_danada_no_activa_nada(): void
    {
        $anterior = $this->modeloActivo('2.0.0-r1');
        DB::table('modelo_ml')->insert(['estado' => 'en_curso', 'registros_usados' => 30,
            'fecha_inicio' => Carbon::now()->subMinutes(5)->format('Y-m-d H:i:s')]);
        Http::fake([
            self::ML.'/reentrenar/estado' => Http::response(['estado' => 'terminado', 'aceptado' => true, 'version' => '2.0.0-r2']),
            self::ML.'/modelo/descargar' => Http::response('dañado', 200, ['X-Sha256' => str_repeat('0', 64), 'X-Modelo-Version' => '2.0.0-r2']),
        ]);

        $fila = app(\App\Services\PrediccionService::class)->revisarReentreno();
        $this->assertSame('error', $fila->estado);
        $this->assertSame('activo', DB::table('modelo_ml')->where('id_modelo', $anterior)->value('estado'));
    }

    public function test_si_el_ml_se_reinicio_durante_el_entrenamiento_se_marca_error(): void
    {
        DB::table('modelo_ml')->insert(['estado' => 'en_curso', 'registros_usados' => 30,
            'fecha_inicio' => Carbon::now()->subMinutes(5)->format('Y-m-d H:i:s')]);
        Http::fake([self::ML.'/reentrenar/estado' => Http::response(['estado' => 'sin_iniciar'])]);
        $fila = app(\App\Services\PrediccionService::class)->revisarReentreno();
        $this->assertSame('error', $fila->estado);
    }

    public function test_un_modelo_nuevo_cambia_la_prediccion_guardada_en_cache(): void
    {
        Http::fake([self::ML.'/predecir' => Http::sequence()->push($this->prediccion('2.0.0'))->push($this->prediccion('2.0.0-r9')),
                    self::ML.'/modelo/cargar' => Http::response(['ok' => true])]);
        [, $t] = $this->usuarioConToken();
        $this->getJson('/api/prediccion', $this->conToken($t))->assertOk()->assertJsonPath('prediccion.modelo_version', '2.0.0');
        $this->modeloActivo('2.0.0-r9');          // se activó un modelo nuevo: no debe usar la predicción vieja
        Cache::forget('ml:mantenimiento');
        $this->getJson('/api/prediccion', $this->conToken($t))->assertOk()->assertJsonPath('prediccion.modelo_version', '2.0.0-r9');
    }

    public function test_el_comando_muestra_el_historial(): void
    {
        $this->modeloActivo('2.0.0-r1');
        $this->artisan('migrasense:ml-reentrenar --estado')->expectsOutputToContain('Historial de aprendizaje')->assertSuccessful();
    }

    public function test_si_metricas_es_de_otra_version_no_guarda_reporte_pero_si_el_modelo(): void
    {
        $id = DB::table('modelo_ml')->insertGetId(['estado' => 'en_curso', 'registros_usados' => 30,
            'fecha_inicio' => Carbon::now()->subMinutes(5)->format('Y-m-d H:i:s')], 'id_modelo');
        Http::fake([
            self::ML.'/reentrenar/estado' => Http::response(['estado' => 'terminado', 'aceptado' => true, 'version' => '2.0.0-r2']),
            self::ML.'/modelo/descargar' => Http::response('m', 200, ['X-Sha256' => hash('sha256', 'm'), 'X-Modelo-Version' => '2.0.0-r2']),
            self::ML.'/metricas' => Http::response(['version' => '2.0.0']),
        ]);
        $fila = app(\App\Services\PrediccionService::class)->revisarReentreno();
        $this->assertSame('activo', $fila->estado);
        $this->assertNull($fila->reporte);
        $this->assertSame($id, (int) $fila->id_modelo);
    }

    public function test_la_memoria_detecta_archivos_sanos_y_danados(): void
    {
        $bueno = $this->modeloActivo('2.0.0-r1', 'binario-bueno');
        $malo = DB::table('modelo_ml')->insertGetId(['version' => '2.0.0-r0', 'estado' => 'reemplazado',
            'archivo' => base64_encode('otro'), 'sha256' => str_repeat('a', 64), 'registros_usados' => 0], 'id_modelo');
        $rechazado = DB::table('modelo_ml')->insertGetId(['estado' => 'rechazado', 'registros_usados' => 0], 'id_modelo');

        $m = collect(app(\App\Services\PrediccionService::class)->memoria())->keyBy('id_modelo');
        $this->assertTrue($m[$bueno]['sano']);
        $this->assertFalse($m[$malo]['sano']);
        $this->assertNull($m[$rechazado]['sano']);
        $this->assertArrayNotHasKey('archivo', $m[$bueno]);
    }

    public function test_el_comando_de_memoria_completa_lo_aprendido_y_exporta(): void
    {
        $id = $this->modeloActivo('2.0.0-r1', 'binario-x');
        Http::fake([
            self::ML.'/salud' => Http::response(['version' => '2.0.0-r1']),
            self::ML.'/metricas' => Http::response(['version' => '2.0.0-r1', 'datos' => ['pacientes_reales' => 7],
                'lo_que_mas_pesa' => [['caracteristica' => 'ciclo_relativo', 'importancia' => 0.02]]]),
        ]);
        $this->artisan('migrasense:ml-memoria --exportar')
            ->expectsOutputToContain('MEMORIA DEL ML EN AIVEN')
            ->expectsOutputToContain('ciclo_relativo')
            ->assertSuccessful();

        $this->assertNotNull(DB::table('modelo_ml')->where('id_modelo', $id)->value('reporte'));
        $archivo = storage_path('app/memoria_ml/modelo_2.0.0-r1.joblib');
        $this->assertSame('binario-x', file_get_contents($archivo));
        @unlink($archivo);
        @unlink(storage_path('app/memoria_ml/modelo_2.0.0-r1.json'));
    }

    public function test_el_panel_local_se_genera_sin_datos_personales(): void
    {
        $this->modeloActivo('2.0.0-r1');
        [$u, $t] = $this->usuarioConToken();
        $this->conRegistros($t);
        Http::fake([
            self::ML.'/salud' => Http::response(['version' => '2.0.0-r1', 'aprendido_con_usuarios' => true, 'auc_personas_nuevas' => 0.88]),
            self::ML.'/metricas' => Http::response(['version' => '2.0.0-r1', 'datos' => ['pacientes_reales' => 1]]),
        ]);
        $this->artisan('migrasense:panel-ml --no-abrir')->assertSuccessful();

        $html = file_get_contents(storage_path('app/panel_ml.html'));
        $this->assertStringContainsString('Memoria guardada en Aiven', $html);
        $this->assertStringContainsString('2.0.0-r1', $html);
        $this->assertStringNotContainsString($u->correo, $html);
        @unlink(storage_path('app/panel_ml.html'));
    }

    public function test_el_panel_explica_fiabilidad_y_datos_en_palabras_simples(): void
    {
        $this->modeloActivo('2.0.0-r1');
        $rep = [
            'version' => '2.0.0-r1', 'entrenado' => '2026-10-20T10:00:00', 'horizonte_horas' => 48,
            'datos' => ['pacientes_simulados' => 200, 'filas_simuladas' => 17888, 'pacientes_reales' => 4, 'filas_reales' => 103],
            'prueba_personas_nuevas' => [
                'modelo' => ['casos' => 4969, 'tasa_real' => 0.421, 'auc' => 0.883],
                'referencia_solo_cuestionario' => ['auc' => 0.81],
                'calibracion' => [['rango' => '0-10%', 'casos' => 100, 'prob_media' => 0.05, 'real' => 0.04]],
                'alertas' => [['umbral' => 0.7, 'alertas' => 10, 'aciertos_%' => 85.4, 'migranas_avisadas_%' => 57.0]],
            ],
            'prueba_datos_reales' => 'Aún no hay suficientes datos reales.',
        ];
        Http::fake([
            self::ML.'/salud' => Http::response(['version' => '2.0.0-r1', 'aprendido_con_usuarios' => true]),
            self::ML.'/metricas' => Http::response($rep),
        ]);
        $this->artisan('migrasense:panel-ml --no-abrir')
            ->expectsOutputToContain('Fiabilidad buena: 88 de 100 puntos')
            ->expectsOutputToContain('Entrenado con 17.991 momentos')
            ->assertSuccessful();

        $html = file_get_contents(storage_path('app/panel_ml.html'));
        $this->assertStringContainsString('¿Qué tan confiable es hoy?', $html);
        $this->assertStringContainsString('¿Con cuántos datos se entrenó?', $html);
        $this->assertStringContainsString('17.888 momentos', $html);
        $this->assertStringContainsString('faltan 1 persona(s) más', $html);
        $this->assertStringContainsString('acierta 85 de cada 100', $html);
        @unlink(storage_path('app/panel_ml.html'));
    }

    public function test_la_traduccion_de_fiabilidad_funciona_con_reportes_incompletos(): void
    {
        $r = \App\Support\FiabilidadMl::resumen([]);
        $this->assertSame('sin evaluar', $r['nota']);
        $this->assertSame(0, $r['datos']['momentos_total']);
        $r = \App\Support\FiabilidadMl::resumen(['prueba_personas_nuevas' => ['modelo' => ['auc' => 0.65]],
            'prueba_datos_reales' => ['personas_de_prueba' => 5, 'modelo_con_datos_reales' => ['auc' => 0.9]]]);
        $this->assertSame('débil', $r['nota']);
        $this->assertTrue($r['reales']['medido']);
    }
}
