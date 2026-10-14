<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        // Login: 5 intentos/min por (correo + IP) y 20/min por IP -> frena fuerza bruta
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        // Registro: 10 cuentas por hora por IP
        RateLimiter::for('registro', function (Request $request) {
            return Limit::perHour(10)->by($request->ip());
        });

        // Pedir código de recuperación: 3 cada 15 min por (correo + IP) y 10 por hora por IP
        RateLimiter::for('recuperar', function (Request $request) {
            return [
                Limit::perMinutes(15, 3)->by('rec|'.strtolower((string) $request->input('email')).'|'.$request->ip()),
                Limit::perHour(10)->by('rec|'.$request->ip()),
            ];
        });

        // Probar el código: 10 cada 15 min por correo y 20 por IP (el código además se anula a los 5 fallos)
        RateLimiter::for('restablecer', function (Request $request) {
            return [
                Limit::perMinutes(15, 10)->by('res|'.strtolower((string) $request->input('email'))),
                Limit::perMinutes(15, 20)->by('res|'.$request->ip()),
            ];
        });

        // Acciones delicadas (cambiar clave, borrar cuenta): 5 por minuto por usuario
        RateLimiter::for('sensibles', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id_usuario ?: $request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            // El token se resuelve aquí (el throttle corre antes que auth:sanctum); la PK es id_usuario, no id
            return Limit::perMinute(60)->by(optional($request->user('sanctum'))->id_usuario ?: $request->ip());
        });
    }
}
