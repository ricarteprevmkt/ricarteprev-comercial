<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Libera a rota se o usuário tiver qualquer uma das permissões informadas.
 * Usa o Gate, então respeita o acesso total do administrador.
 *
 * Uso: ->middleware('can.any:agenda.view_all,agenda.view_own')
 */
class EnsureUserCanAny
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        abort_unless($request->user() && Gate::forUser($request->user())->any($abilities), 403);

        return $next($request);
    }
}
