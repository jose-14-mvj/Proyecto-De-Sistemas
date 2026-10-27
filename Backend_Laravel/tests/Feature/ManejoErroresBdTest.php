<?php

namespace Tests\Feature;

use App\Http\Controllers\Controller;
use App\Services\RecordatorioService;
use Illuminate\Database\QueryException;
use PDOException;
use Tests\TestCase;

/**
 * Refactorización: el manejo de errores de la BD (SQLSTATE 45000 → 422) estaba copiado en cinco
 * controladores. Ahora vive en un solo método de la clase base: Controller::conReglasDeBd().
 */
class ManejoErroresBdTest extends TestCase
{
    /** Error de la BD como lo lanza PDO al ejecutar un CALL */
    private function errorBd(string $sqlstate, ?string $mensaje): QueryException
    {
        $pdo = new PDOException($mensaje ?? 'error');
        $pdo->errorInfo = [$sqlstate, 1644, $mensaje];

        return new QueryException('CALL sp_prueba()', [], $pdo);
    }

    private function controlador(): Controller
    {
        return new class extends Controller {
            public function probar(\Closure $accion, string $siFalla = 'No se pudo guardar')
            {
                return $this->conReglasDeBd($accion, $siFalla);
            }

            public function cuatroCientosCuatro(string $mensaje)
            {
                return $this->noEncontrado($mensaje);
            }
        };
    }

    public function test_sin_error_devuelve_la_respuesta_de_la_accion(): void
    {
        $r = $this->controlador()->probar(fn () => response()->json(['ok' => true], 201));

        $this->assertSame(201, $r->getStatusCode());
    }

    public function test_regla_de_negocio_45000_se_convierte_en_422_con_su_mensaje(): void
    {
        $r = $this->controlador()->probar(fn () => throw $this->errorBd('45000', 'La fecha no puede estar en el futuro'));

        $this->assertSame(422, $r->getStatusCode());
        $this->assertSame(['error' => 'La fecha no puede estar en el futuro'], $r->getData(true));
    }

    public function test_45000_sin_mensaje_usa_el_texto_por_defecto_de_cada_endpoint(): void
    {
        $r = $this->controlador()->probar(fn () => throw $this->errorBd('45000', null), 'No se pudo generar el reporte');

        $this->assertSame(['error' => 'No se pudo generar el reporte'], $r->getData(true));
    }

    public function test_otro_error_de_bd_no_se_oculta_se_relanza(): void
    {
        $this->expectException(QueryException::class);

        $this->controlador()->probar(fn () => throw $this->errorBd('23000', 'Duplicate entry'));
    }

    public function test_no_encontrado_responde_404_con_el_mensaje_de_cada_recurso(): void
    {
        $r = $this->controlador()->cuatroCientosCuatro('Notificación no encontrada');

        $this->assertSame(404, $r->getStatusCode());
        $this->assertSame(['error' => 'Notificación no encontrada'], $r->getData(true));
    }

    /** De punta a punta: un endpoint real cuyo procedimiento almacenado rechaza el dato → 422 */
    public function test_endpoint_real_devuelve_422_con_el_mensaje_del_procedimiento(): void
    {
        [, $t] = $this->usuarioConToken();
        $this->mock(RecordatorioService::class)->shouldReceive('guardar')
            ->andThrow($this->errorBd('45000', 'La hora no es válida'));

        $this->putJson('/api/recordatorios', ['daily' => ['on' => true, 'time' => '20:00']], $this->conToken($t))
            ->assertStatus(422)->assertExactJson(['error' => 'La hora no es válida']);
    }

    /** De punta a punta: un fallo inesperado de la BD → 500 genérico, sin filtrar detalles internos */
    public function test_endpoint_real_con_fallo_inesperado_responde_500_sin_detalles(): void
    {
        [, $t] = $this->usuarioConToken();
        $this->mock(RecordatorioService::class)->shouldReceive('guardar')
            ->andThrow($this->errorBd('HY000', 'Lost connection to MySQL server at 10.0.0.5'));

        $r = $this->putJson('/api/recordatorios', ['daily' => ['on' => true, 'time' => '20:00']], $this->conToken($t))
            ->assertStatus(500);
        $this->assertStringNotContainsString('10.0.0.5', $r->getContent());
    }
}
