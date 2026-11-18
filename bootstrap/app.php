<?php

use App\Http\Middleware\EnsureUserCanAny;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Vale para todas as rotas web: quem for desativado perde a sessão na próxima requisição.
        $middleware->web(append: [EnsureUserIsActive::class, SecurityHeaders::class]);

        // Na Hostinger o HTTPS termina no proxy: confiar nele para gerar URLs https e cookies seguros.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'can.any' => EnsureUserCanAny::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
