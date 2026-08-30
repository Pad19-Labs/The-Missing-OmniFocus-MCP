<?php

use App\Http\Middleware\EnsureRelayToken;
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
        $middleware->alias(['relay.token' => EnsureRelayToken::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The relay serves API clients only; an HTML error page would be
        // useless here and a stack-trace page would leak request data.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => true);

        // Belt to the MetadataOnlyProcessor's braces: no request attribute is
        // ever flashed to a session on a validation failure.
        $exceptions->dontFlash(['*']);
    })->create();
