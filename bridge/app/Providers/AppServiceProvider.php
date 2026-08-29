<?php

namespace App\Providers;

use App\OmniFocus\Contracts\OmniJsRunner;
use App\OmniFocus\OsascriptRunner;
use App\OmniFocus\ScriptRepository;
use App\Support\PortableRuntime;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

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
        $this->migratePortableDatabase();
    }

    /**
     * The self-contained binary has no install step, so the database
     * migrates itself on first boot. The framework migrator globs for
     * files, which fails on phar streams — so walk them directly.
     */
    private function migratePortableDatabase(): void
    {
        if (! PortableRuntime::active() || $this->app->runningUnitTests()) {
            return;
        }

        try {
            if (Schema::hasTable('audit_logs')) {
                return;
            }

            $dir = database_path('migrations');

            foreach (scandir($dir) ?: [] as $file) {
                if (! str_ends_with($file, '.php')) {
                    continue;
                }

                try {
                    (require $dir.'/'.$file)->up();
                } catch (Throwable) {
                    // Already-applied migration from a previous version.
                }
            }
        } catch (Throwable) {
            // A failed migration surfaces on first audited write instead.
        }
    }
}
