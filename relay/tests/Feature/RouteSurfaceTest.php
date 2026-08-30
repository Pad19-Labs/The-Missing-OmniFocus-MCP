<?php

use Illuminate\Support\Facades\Route;

/**
 * Phase 1 exposes exactly two write endpoints and a health check. Pinning the
 * whole route table means a package or a stray scaffold cannot quietly add a
 * public endpoint to a service that fronts people's task databases.
 */
it('exposes only the phase 1 endpoints', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => implode('|', array_diff($route->methods(), ['HEAD'])).' '.$route->uri())
        ->sort()
        ->values()
        ->all();

    expect($routes)->toBe([
        'GET /',
        'GET up',
        'POST api/access-requests',
        'POST api/pair',
    ]);
});

it('serves no public file storage routes', function () {
    $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri());

    expect($uris)->not->toContain('storage/{path}');
});

it('rate limits both public endpoints', function () {
    foreach (['api/access-requests', 'api/pair'] as $uri) {
        $route = collect(Route::getRoutes()->getRoutes())->firstWhere(fn ($r) => $r->uri() === $uri);

        expect(collect($route->gatherMiddleware())->filter(fn ($m) => str_starts_with((string) $m, 'throttle:')))
            ->not->toBeEmpty("{$uri} must be rate limited");
    }
});

it('has no login, register, or password reset route', function () {
    foreach (['login', 'register', 'password/reset', 'forgot-password'] as $path) {
        $this->postJson('/'.$path)->assertNotFound();
    }
});
