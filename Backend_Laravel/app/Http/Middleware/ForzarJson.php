<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** La API siempre responde JSON (errores de validación, 401, etc.), aunque el cliente olvide el header Accept. */
class ForzarJson
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');
        return $next($request);
    }
}
