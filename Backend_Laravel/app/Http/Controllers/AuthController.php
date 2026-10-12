<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegistroRequest;
use App\Models\Usuario;
use App\Services\PerfilService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private PerfilService $perfiles)
    {
    }

    /** POST /api/registro */
    public function registro(RegistroRequest $request): JsonResponse
    {
        $usuario = Usuario::create([
            'nombres'    => $request->validated('name'),
            'correo'     => $request->validated('email'),
            'contrasena' => Hash::make($request->validated('password')),   // Argon2id
        ]);
        // El trigger trg_usuario_ai de la BD crea los 2 recordatorios por defecto.

        $usuario->refresh();   // trae fecha_registro y valores por defecto de la BD

        return response()->json([
            'ok'      => true,
            'usuario' => $usuario->paraFrontend(),
            'perfil'  => null,                         // cuenta recién creada: aún sin cuestionario
            'token'   => $this->nuevoToken($usuario),
        ], 201);
    }

    /** POST /api/login */
    public function login(LoginRequest $request): JsonResponse
    {
        $usuario = Usuario::where('correo', $request->validated('email'))
            ->where('activo', 1)
            ->first();

        // Si el correo no existe se compara contra un hash falso: así el tiempo de
        // respuesta es parecido y no se puede averiguar qué correos están registrados.
        $hash  = $usuario?->contrasena ?? Hash::make('relleno-para-igualar-tiempo');
        $clave = $request->validated('password');
        $valida = Hash::check($clave, $hash) && $usuario !== null;

        if (! $valida) {
            return response()->json(['error' => 'Correo o contraseña incorrectos'], 401);
        }

        // Si algún día se suben los parámetros de Argon2, rehashea la clave al iniciar sesión
        if (Hash::needsRehash($usuario->contrasena)) {
            $usuario->contrasena = Hash::make($clave);
        }
        $usuario->ultimo_acceso = now();
        $usuario->save();

        return response()->json([
            'ok'      => true,
            'usuario' => $usuario->paraFrontend(),
            'perfil'  => $this->perfiles->cargar($usuario->id_usuario),   // null si aún no hizo el cuestionario
            'token'   => $this->nuevoToken($usuario),
        ]);
    }

    /** GET /api/sesion  (requiere token) */
    public function sesion(Request $request): JsonResponse
    {
        return response()->json([
            'ok'      => true,
            'usuario' => $request->user()->paraFrontend(),
            'perfil'  => $this->perfiles->cargar($request->user()->id_usuario),
        ]);
    }

    /** POST /api/logout  (requiere token) — revoca SOLO el token actual */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['ok' => true]);
    }

    private function nuevoToken(Usuario $usuario): string
    {
        // Vence según SANCTUM_EXPIRATION (minutos) del .env
        return $usuario->createToken('app-web')->plainTextToken;
    }
}
