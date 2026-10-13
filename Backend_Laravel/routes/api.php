<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\RecordatorioController;
use App\Http\Controllers\RegistroController;
use Illuminate\Support\Facades\Route;

/*
| API de MigraSense. Prefijo automático: /api
*/

// Públicas (con límite de intentos, ver RouteServiceProvider)
Route::post('/registro', [AuthController::class, 'registro'])->middleware('throttle:registro');
Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:login');

// Protegidas: requieren "Authorization: Bearer <token>"
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/sesion',  [AuthController::class, 'sesion']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Cuestionario de migraña
    Route::get('/perfil', [PerfilController::class, 'mostrar']);
    Route::put('/perfil', [PerfilController::class, 'guardar']);

    // Catálogos (síntomas y desencadenantes)
    Route::get('/catalogos', [CatalogoController::class, 'index']);

    // Registros: síntomas prodrómicos y episodios de migraña
    Route::get('/registros', [RegistroController::class, 'index']);
    Route::post('/registros/prodromico', [RegistroController::class, 'crearProdromico']);
    Route::post('/registros/episodio', [RegistroController::class, 'crearEpisodio']);
    Route::prefix('/registros/{tipo}/{id}')->whereIn('tipo', ['prodromico', 'episodio'])->whereNumber('id')->group(function () {
        Route::get('/', [RegistroController::class, 'mostrar']);
        Route::put('/', [RegistroController::class, 'editar']);
        Route::delete('/', [RegistroController::class, 'eliminar']);
    });

    // Recordatorios y notificaciones
    Route::get('/recordatorios', [RecordatorioController::class, 'mostrar']);
    Route::put('/recordatorios', [RecordatorioController::class, 'actualizar']);
    Route::get('/notificaciones', [NotificacionController::class, 'index']);
    Route::post('/notificaciones', [NotificacionController::class, 'crear']);
    Route::put('/notificaciones/{id}', [NotificacionController::class, 'marcar'])->whereNumber('id');

    // Cuenta
    Route::put('/cuenta', [CuentaController::class, 'actualizar']);
    Route::middleware('throttle:sensibles')->group(function () {
        Route::put('/cuenta/clave', [CuentaController::class, 'cambiarClave']);
        Route::delete('/cuenta',    [CuentaController::class, 'eliminar']);
    });
});
