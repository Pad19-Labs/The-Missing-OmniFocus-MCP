<?php

namespace App\Providers;

use App\OmniFocus\Contracts\OmniJsRunner;
use App\OmniFocus\OsascriptRunner;
use App\OmniFocus\ScriptRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OmniJsRunner::class, OsascriptRunner::class);

        $this->app->singleton(ScriptRepository::class, fn () => new ScriptRepository(resource_path('omnijs')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
