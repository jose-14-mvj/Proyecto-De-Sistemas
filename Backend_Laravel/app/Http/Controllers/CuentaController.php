<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarCuentaRequest;
use App\Http\Requests\CambiarClaveRequest;
use App\Http\Requests\EliminarCuentaRequest;
use App\Services\PerfilService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CuentaController extends Controller
{
    /** PUT /api/cuenta — nombre, correo (pide la clave actual) y foto */
    public function actualizar(ActualizarCuentaRequest $request): JsonResponse
    {
        $usuario = $request->user();
        $d = $request->validated();

        $usuario->nombres = $d['name'];
        if (isset($d['email'])) {
            $usuario->correo = $d['email'];
        }
        if ($request->exists('photo')) {          // null = volver a la foto predeterminada
            $usuario->foto = $d['photo'] ?? null;
        }
        $usuario->save();

        return response()->json(['ok' => true, 'usuario' => $usuario->fresh()->paraFrontend()]);
    }

    /** PUT /api/cuenta/clave — cambia la contraseña y cierra las sesiones de los demás dispositivos */
    public function cambiarClave(CambiarClaveRequest $request): JsonResponse
    {
        $usuario = $request->user();
        $usuario->contrasena = Hash::make($request->validated('password'));
        $usuario->save();

        $usuario->tokens()
            ->where('id', '!=', $usuario->currentAccessToken()->id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    /** DELETE /api/cuenta — borra la cuenta y TODOS sus datos (ON DELETE CASCADE) */
    public function eliminar(EliminarCuentaRequest $request): JsonResponse
    {
        $usuario = $request->user();

        DB::transaction(function () use ($usuario) {
            $usuario->tokens()->delete();
            $usuario->delete();
        });

        return response()->json(['ok' => true]);
    }
}
