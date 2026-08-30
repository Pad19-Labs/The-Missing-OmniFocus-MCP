<?php

use App\Logging\MetadataOnlyProcessor;
use App\Logging\ScrubRequestData;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\TestHandler;
use Monolog\Logger as Monolog;

/**
 * These tests exercise the REAL reporting path: an uncaught exception inside a
 * request goes through Laravel's handler, into the log channel, through the
 * scrubbing tap, and lands in a Monolog TestHandler we can read back.
 */
beforeEach(function () {
    $this->handler = new TestHandler;

    $monolog = new Monolog('testing', [$this->handler]);
    $monolog->pushProcessor(new MetadataOnlyProcessor);

    Log::swap(new Logger($monolog));
});

function loggedText(TestHandler $handler): string
{
    $flat = '';

    foreach ($handler->getRecords() as $record) {
        $flat .= $record->message.' '.json_encode(
            array_map(fn ($value) => $value instanceof Throwable ? (string) $value : $value, $record->context),
            JSON_PARTIAL_OUTPUT_ON_ERROR,
        );
    }

    return $flat;
}

it('never logs the request body when a request throws', function () {
    Route::post('/_test/boom', function () {
        throw new RuntimeException('Something went wrong');
    });

    $this->postJson('/_test/boom', [
        'jsonrpc' => '2.0',
        'method' => 'tools/call',
        'params' => ['name' => 'create-task', 'arguments' => ['note' => 'Buy a deerstalker hat']],
    ])->assertStatus(500);

    $logged = loggedText($this->handler);

    expect($this->handler->getRecords())->not->toBeEmpty()
        ->and($logged)->not->toContain('deerstalker')
        ->and($logged)->not->toContain('create-task')
        ->and($logged)->not->toContain('tools/call');
});

it('never logs query strings from a thrown request', function () {
    Route::get('/_test/boom-query', function () {
        throw new RuntimeException('Something went wrong');
    });

    $this->getJson('/_test/boom-query?secret=deerstalker')->assertStatus(500);

    expect(loggedText($this->handler))->not->toContain('deerstalker');
});

it('never logs the bearer token from a thrown request', function () {
    Route::post('/_test/boom-auth', function () {
        throw new RuntimeException('Something went wrong');
    });

    $this->withToken('super-secret-token-value')
        ->postJson('/_test/boom-auth', ['a' => 'bcd'])
        ->assertStatus(500);

    expect(loggedText($this->handler))->not->toContain('super-secret-token-value');
});

it('still logs the exception message so failures remain diagnosable', function () {
    Route::post('/_test/boom-meta', function () {
        throw new RuntimeException('Something went wrong');
    });

    $this->postJson('/_test/boom-meta', ['a' => 'bcd'])->assertStatus(500);

    expect(loggedText($this->handler))->toContain('Something went wrong');
});

it('scrubs the body even when app code logs the request explicitly', function () {
    Route::post('/_test/boom-context', function () {
        Log::error('handling request', ['request' => request()->all()]);

        throw new RuntimeException('Something went wrong');
    });

    $this->postJson('/_test/boom-context', ['arguments' => ['note' => 'deerstalker']])
        ->assertStatus(500);

    $logged = loggedText($this->handler);

    expect($logged)->toContain('handling request')
        ->and($logged)->not->toContain('deerstalker');
});

it('scrubs a body interpolated into the log message itself', function () {
    Route::post('/_test/boom-message', function () {
        Log::error('failed for '.request()->input('note'));

        throw new RuntimeException('Something went wrong');
    });

    $this->postJson('/_test/boom-message', ['note' => 'deerstalker'])->assertStatus(500);

    expect(loggedText($this->handler))->not->toContain('deerstalker');
});

it('redacts forbidden context keys regardless of the current request', function () {
    Log::error('manual', [
        'params' => ['name' => 'create-task'],
        'authorization' => 'Bearer abc123',
        'ip_address' => '203.0.113.9',
        'safe' => 'kept',
    ]);

    $records = $this->handler->getRecords();
    $context = $records[0]->context;

    expect($context['params'])->toBe(MetadataOnlyProcessor::REDACTED)
        ->and($context['authorization'])->toBe(MetadataOnlyProcessor::REDACTED)
        ->and($context['ip_address'])->toBe(MetadataOnlyProcessor::REDACTED)
        ->and($context['safe'])->toBe('kept');
});

/**
 * The tests above swap in a TestHandler, which proves the processor works but
 * not that it is actually wired. This one writes through the REAL, unswapped
 * `single` channel to a temp file — if the tap were ever unregistered, this is
 * the test that fails.
 */
it('scrubs the body on the real unswapped log channel', function () {
    // Undo the TestHandler swap: this test must exercise the app's own wiring.
    $this->refreshApplication();

    $path = sys_get_temp_dir().'/relay-logging-policy-'.getmypid().'.log';
    @unlink($path);

    config(['logging.default' => 'single', 'logging.channels.single.path' => $path]);

    Route::post('/_test/real-log', function () {
        // A body reaches the log the only way it realistically can: app code
        // logging it. The tap must strip it even so.
        Log::error('handling request', ['params' => request()->all()]);

        throw new RuntimeException('boom while handling');
    });

    $this->withToken('super-secret-token-value')
        ->postJson('/_test/real-log', ['params' => ['arguments' => ['note' => 'deerstalker']]])
        ->assertStatus(500);

    $contents = file_get_contents($path);
    @unlink($path);

    expect($contents)->toContain('boom while handling')
        ->and($contents)->not->toContain('deerstalker')
        ->and($contents)->not->toContain('super-secret-token-value');
});

it('applies the scrubbing tap to every configured log channel', function () {
    foreach (array_keys(config('logging.channels')) as $channel) {
        expect(config("logging.channels.{$channel}.tap", []))
            ->toBeArray()
            ->toContain(ScrubRequestData::class);
    }
});

it('stores no ip address or user agent column in any table', function () {
    $columns = [];

    foreach (DB::select("select name from sqlite_master where type = 'table'") as $table) {
        foreach (DB::select('pragma table_info('.$table->name.')') as $column) {
            $columns[] = strtolower($column->name);
        }
    }

    expect($columns)->not->toContain('ip_address')
        ->and($columns)->not->toContain('ip')
        ->and($columns)->not->toContain('remote_addr')
        ->and($columns)->not->toContain('user_agent');
});

it('has no migration mentioning an ip address', function () {
    foreach (glob(database_path('migrations/*.php')) as $migration) {
        expect(strtolower(file_get_contents($migration)))
            ->not->toContain('ip_address')
            ->and(strtolower(file_get_contents($migration)))->not->toContain('user_agent');
    }
});

it('has no sessions table, whose framework default carries an ip address', function () {
    $tables = array_map(
        fn ($table) => $table->name,
        DB::select("select name from sqlite_master where type = 'table'"),
    );

    expect($tables)->not->toContain('sessions')
        ->and(config('session.driver'))->not->toBe('database');
});

it('defaults the session driver away from database even with no env set', function () {
    // Guards a deploy that forgets SESSION_DRIVER: the framework default would
    // demand a sessions table, which this app deliberately does not have.
    $config = require config_path('session.php');

    expect($config['driver'])->toBe('array');
});

it('never reads the client ip anywhere in application code', function () {
    foreach (glob(app_path('**/*.php')) + glob(app_path('**/**/*.php')) as $file) {
        $source = file_get_contents($file);

        expect($source)->not->toContain('->ip()')
            ->and($source)->not->toContain('->ips()')
            ->and($source)->not->toContain('REMOTE_ADDR');
    }
});
