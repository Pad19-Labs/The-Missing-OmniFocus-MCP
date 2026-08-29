<?php

use App\Support\PortableRuntime;

beforeEach(function () {
    $this->dataDir = sys_get_temp_dir().'/portable-runtime-test-'.bin2hex(random_bytes(4));
});

afterEach(function () {
    if (is_dir($this->dataDir)) {
        exec('rm -rf '.escapeshellarg($this->dataDir));
    }
});

it('is inactive by default outside a phar', function () {
    expect(PortableRuntime::active())->toBeFalse();
});

it('prepares the data directory with storage layout, env file, and database', function () {
    $dir = PortableRuntime::prepare($this->dataDir);

    expect($dir)->toBe($this->dataDir)
        ->and(is_dir($dir.'/storage/framework/views'))->toBeTrue()
        ->and(is_dir($dir.'/storage/framework/sessions'))->toBeTrue()
        ->and(is_dir($dir.'/storage/logs'))->toBeTrue()
        ->and(is_file($dir.'/.env'))->toBeTrue()
        ->and(is_file($dir.'/database.sqlite'))->toBeTrue();

    $env = file_get_contents($dir.'/.env');
    expect($env)->toContain('APP_KEY=base64:')
        ->toContain('MCP_AUTH_TOKEN=')
        ->toContain('DB_DATABASE="'.$dir.'/database.sqlite"');
});

it('never overwrites an existing env file on re-run', function () {
    PortableRuntime::prepare($this->dataDir);
    $original = file_get_contents($this->dataDir.'/.env');

    PortableRuntime::prepare($this->dataDir);

    expect(file_get_contents($this->dataDir.'/.env'))->toBe($original);
});

it('generates a unique 64-char hex MCP token', function () {
    PortableRuntime::prepare($this->dataDir);

    preg_match('/^MCP_AUTH_TOKEN=([0-9a-f]+)$/m', file_get_contents($this->dataDir.'/.env'), $m);
    expect($m[1] ?? '')->toHaveLength(64);
});
