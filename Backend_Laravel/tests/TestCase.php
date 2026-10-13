<?php

namespace Tests;

use App\Models\Usuario;
use App\Services\NotificacionService;
use App\Services\RecordatorioService;
use App\Services\RegistroService;
use App\Services\ReporteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\Support\NotificacionServiceFalso;
use Tests\Support\RecordatorioServiceFalso;
use Tests\Support\RegistroServiceFalso;
use Tests\Support\ReporteServiceFalso;

require_once __DIR__.'/Support/ServiciosFalsos.php';

/**
 * Base de todas las pruebas.
 * SEGURIDAD: las pruebas usan SIEMPRE una base SQLite en memoria que se crea y se
 * destruye en cada prueba. Todas las conexiones (mysql y mysql_admin incluidas) se
 * redirigen a esa base, así que es imposible tocar la base real de Aiven.
 */
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $memoria = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true];
        config([
            'database.default'                  => 'sqlite',
            'database.connections.sqlite'       => $memoria,
            'database.connections.mysql'        => $memoria,
            'database.connections.mysql_admin'  => $memoria,
        ]);
        DB::purge('mysql');
        DB::purge('mysql_admin');
        DB::purge('sqlite');

        $pdo = DB::connection()->getPdo();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('Las pruebas solo pueden correr en SQLite en memoria.');
        }

        // Funciones que usan las vistas de prueba (hora de Bolivia, como la conexión MySQL)
        $pdo->sqliteCreateFunction('ahora', fn () => Carbon::now()->format('Y-m-d H:i:s'), 0);
        $pdo->sqliteCreateFunction('hoy', fn () => Carbon::today()->toDateString(), 0);
        DB::unprepared(file_get_contents(__DIR__.'/Support/esquema_sqlite.sql'));

        // Los procedimientos almacenados se reemplazan por equivalentes en PHP
        $registros = new RegistroServiceFalso();
        $this->app->instance(RegistroService::class, $registros);
        $this->app->instance(RecordatorioService::class, new RecordatorioServiceFalso());
        $this->app->instance(NotificacionService::class, new NotificacionServiceFalso());
        $this->app->instance(ReporteService::class, new ReporteServiceFalso($registros));

        RateLimiter::clear('');
        $this->app['cache']->store()->flush();   // reinicia los contadores de throttle entre pruebas
    }

    /** Crea un usuario directamente en la base de prueba y devuelve [usuario, token]. */
    protected function usuarioConToken(string $correo = null, string $clave = 'ClaveSegura123'): array
    {
        $usuario = Usuario::create([
            'nombres'    => 'Prueba',
            'correo'     => $correo ?? 'u'.uniqid().'@test.com',
            'contrasena' => Hash::make($clave),
        ]);
        $usuario->refresh();

        return [$usuario, $usuario->createToken('pruebas')->plainTextToken];
    }

    /** Cabeceras con el token Bearer. */
    protected function conToken(string $token): array
    {
        // Laravel recuerda el usuario autenticado entre peticiones de la misma prueba: se olvida
        // para que cada petición se autentique de verdad con SU token.
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
    }
}
