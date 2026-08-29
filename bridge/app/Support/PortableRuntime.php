<?php

namespace App\Support;

use Phar;

/**
 * When the app ships as a self-contained binary (PHP + phar in one file),
 * the phar is read-only — so all mutable state (.env, SQLite database,
 * storage) lives in an external data directory prepared on first run.
 */
final class PortableRuntime
{
    public static function active(): bool
    {
        return (class_exists(Phar::class) && Phar::running() !== '')
            || (getenv('OMNIFOCUS_MCP_DATA_DIR') ?: '') !== '';
    }

    public static function dataPath(): string
    {
        $custom = getenv('OMNIFOCUS_MCP_DATA_DIR') ?: '';

        if ($custom !== '') {
            return rtrim($custom, '/');
        }

        $home = $_SERVER['HOME'] ?? (getenv('HOME') ?: sys_get_temp_dir());

        return $home.'/Library/Application Support/MissingOmniFocusMCP';
    }

    /**
     * Idempotent first-run setup: storage layout, env file with fresh
     * APP_KEY and MCP token, and an empty SQLite database.
     */
    public static function prepare(?string $dir = null): string
    {
        $dir ??= self::dataPath();

        foreach ([
            '/storage/app',
            '/storage/framework/cache/data',
            '/storage/framework/sessions',
            '/storage/framework/views',
            '/storage/logs',
        ] as $sub) {
            if (! is_dir($dir.$sub)) {
                mkdir($dir.$sub, 0755, true);
            }
        }

        if (! is_file($dir.'/.env')) {
            file_put_contents($dir.'/.env', self::defaultEnv($dir));
        }

        if (! is_file($dir.'/database.sqlite')) {
            touch($dir.'/database.sqlite');
        }

        return $dir;
    }

    private static function defaultEnv(string $dir): string
    {
        $appKey = 'base64:'.base64_encode(random_bytes(32));
        $token = bin2hex(random_bytes(32));

        return <<<ENV
        APP_NAME="The Missing OmniFocus MCP"
        APP_ENV=production
        APP_KEY={$appKey}
        APP_DEBUG=false

        DB_CONNECTION=sqlite
        DB_DATABASE="{$dir}/database.sqlite"

        APP_SERVICES_CACHE="{$dir}/storage/framework/cache/services.php"
        APP_PACKAGES_CACHE="{$dir}/storage/framework/cache/packages.php"
        APP_CONFIG_CACHE="{$dir}/storage/framework/cache/config.php"
        APP_ROUTES_CACHE="{$dir}/storage/framework/cache/routes.php"
        APP_EVENTS_CACHE="{$dir}/storage/framework/cache/events.php"

        LOG_CHANNEL=single
        LOG_LEVEL=warning
        CACHE_STORE=database
        SESSION_DRIVER=file
        QUEUE_CONNECTION=sync

        MCP_AUTH_TOKEN={$token}

        ENV;
    }
}
