<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\PerfilController;
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

    // Cuenta
    Route::put('/cuenta', [CuentaController::class, 'actualizar']);
    Route::middleware('throttle:sensibles')->group(function () {
        Route::put('/cuenta/clave', [CuentaController::class, 'cambiarClave']);
        Route::delete('/cuenta',    [CuentaController::class, 'eliminar']);
    });
});
