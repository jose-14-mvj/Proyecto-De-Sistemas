<?php

namespace App\Http\Controllers;

use App\Services\RegistroService;
use Illuminate\Http\JsonResponse;

class CatalogoController extends Controller
{
    /** GET /api/catalogos — síntomas y desencadenantes activos */
    public function index(RegistroService $registros): JsonResponse
    {
        return response()->json(['ok' => true] + $registros->catalogos());
    }
}
