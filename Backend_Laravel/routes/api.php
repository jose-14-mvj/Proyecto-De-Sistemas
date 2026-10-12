<?php

use App\Http\Controllers\AuthController;
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
});
