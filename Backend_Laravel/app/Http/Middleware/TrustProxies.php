<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * En Render la app está detrás de su balanceador (que maneja el HTTPS). Hay que confiar
     * en él para saber la IP real del visitante (los límites de intentos son por IP) y que
     * la petición llegó por HTTPS. Se controla con TRUSTED_PROXIES en el .env:
     *   vacío = no confiar en nadie (local) · "*" = el proxy que llama (Render).
     */
    protected function proxies()
    {
        $v = (string) config('app.trusted_proxies', '');
        return $v === '*' ? '*' : ($v !== '' ? array_map('trim', explode(',', $v)) : $this->proxies);
    }
}
