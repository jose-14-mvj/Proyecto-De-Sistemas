<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad en TODAS las respuestas.
 * La API solo devuelve JSON, así que la política es la más estricta posible:
 * nada se puede cargar ni incrustar desde estas respuestas.
 */
class CabecerasSeguridad
{
    public function handle(Request $request, Closure $next)
    {
        /** @var Response $response */
        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Content-Type-Options', 'nosniff');                 // el navegador no "adivina" tipos
        $h->set('X-Frame-Options', 'DENY');                            // no se puede meter en un <iframe>
        $h->set('Referrer-Policy', 'no-referrer');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        $h->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");

        if ($request->is('api/*')) {
            // Datos de salud: que ningún navegador ni proxy guarde copias
            $h->set('Cache-Control', 'no-store, private');
            $h->set('Pragma', 'no-cache');
        }

        // HSTS solo tiene sentido (y solo se respeta) por HTTPS
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $h->remove('X-Powered-By');
        if (! app()->runningInConsole()) {
            header_remove('X-Powered-By');   // la que añade PHP (expose_php)
        }

        return $response;
    }
}
