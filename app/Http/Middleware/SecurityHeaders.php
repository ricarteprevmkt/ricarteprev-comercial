<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de segurança em todas as respostas web.
 *
 * - Impede que o sistema seja aberto dentro de outro site (clickjacking).
 * - Impede o navegador de "adivinhar" o tipo de arquivos enviados.
 * - Não vaza o endereço completo das páginas para sites externos.
 * - Libera localização e câmera só para o próprio sistema (check-in e comprovantes).
 * - Em produção com HTTPS, obriga o navegador a usar sempre HTTPS (HSTS).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(self), camera=(self), microphone=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
