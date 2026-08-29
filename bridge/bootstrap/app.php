<?php

use App\Support\PortableRuntime;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

if (PortableRuntime::active()) {
    // The micro SAPI reports PHP_SAPI "micro", not "cli", so Laravel would
    // otherwise skip console command registration entirely.
    $_ENV['APP_RUNNING_IN_CONSOLE'] = $_SERVER['APP_RUNNING_IN_CONSOLE'] = 'true';
    putenv('APP_RUNNING_IN_CONSOLE=true');
}

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

if (PortableRuntime::active()) {
    $dataPath = PortableRuntime::prepare();
    $app->useStoragePath($dataPath.'/storage');
    $app->useEnvironmentPath($dataPath);

    // Laravel's config scanner relies on realpath(), which fails inside a
    // phar; load and merge framework + app config ourselves instead.
    LoadConfiguration::alwaysUse(function () use ($app): array {
        $load = function (string $dir): array {
            $items = [];

            foreach (scandir($dir) ?: [] as $entry) {
                if (str_ends_with($entry, '.php')) {
                    $items[substr($entry, 0, -4)] = require $dir.'/'.$entry;
                }
            }

            return $items;
        };

        $base = $load($app->basePath('vendor/laravel/framework/config'));
        $local = $load($app->configPath());
        $items = [];

        foreach (array_unique([...array_keys($base), ...array_keys($local)]) as $name) {
            $items[$name] = array_merge($base[$name] ?? [], $local[$name] ?? []);
        }

        // "Cached" config skips RegisterProviders' merge of
        // bootstrap/providers.php, so fold the app providers in here.
        $items['app']['providers'] = array_merge(
            $items['app']['providers'] ?? ServiceProvider::defaultProviders()->toArray(),
            require $app->basePath('bootstrap/providers.php'),
        );

        return $items;
    });
}

return $app;
