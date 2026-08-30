<?php

use App\Http\Requests\RedeemPairingCodeRequest;
use App\Models\Device;
use App\Models\PairingCode;
use App\Models\User;
use App\Support\SecretHasher;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->user = User::create(['name' => 'John Watson', 'email' => 'watson@example.test']);
});

it('mints a pairing code for an approved user', function () {
    $this->artisan('relay:pair', ['email' => 'watson@example.test'])->assertSuccessful();

    $code = PairingCode::sole();

    expect($code->user_id)->toBe($this->user->id)
        ->and($code->redeemed_at)->toBeNull()
        ->and($code->expires_at->diffInMinutes(now()))->toBeLessThanOrEqual(15);
});

it('refuses to mint a pairing code for an unknown user', function () {
    $this->artisan('relay:pair', ['email' => 'moriarty@example.test'])->assertFailed();

    expect(PairingCode::count())->toBe(0);
});

it('never stores the pairing code in plaintext', function () {
    $plain = PairingCode::mintFor($this->user);

    $stored = PairingCode::sole()->getAttributes();

    expect($stored)->not->toContain($plain)
        ->and($stored['code_hash'])->not->toBe($plain);
});

it('generates codes of at least eight characters from an unambiguous alphabet', function () {
    foreach (range(1, 50) as $ignored) {
        $plain = PairingCode::generateCode();

        expect(strlen($plain))->toBeGreaterThanOrEqual(8)
            ->and($plain)->toMatch('/^['.PairingCode::ALPHABET.']+$/');
    }
});

it('excludes ambiguous characters from the pairing alphabet', function () {
    foreach (['0', 'O', 'I', '1', 'l', 'L', 'U', 'V'] as $ambiguous) {
        expect(PairingCode::ALPHABET)->not->toContain($ambiguous);
    }
});

it('redeems a pairing code and returns the device credentials exactly once', function () {
    $plain = PairingCode::mintFor($this->user);

    $response = $this->postJson('/api/pair', ['code' => $plain, 'device_name' => "Watson's Mac"]);

    $response->assertOk()
        ->assertJsonStructure(['device_id', 'device_secret'])
        ->assertJsonMissingPath('user_id');

    $device = Device::sole();

    expect($device->user_id)->toBe($this->user->id)
        ->and($device->name)->toBe("Watson's Mac")
        ->and($device->revoked_at)->toBeNull()
        ->and($response->json('device_id'))->toBe($device->id);

    // A second redemption of the same code fails: single use.
    $this->postJson('/api/pair', ['code' => $plain, 'device_name' => 'Second Mac'])
        ->assertStatus(422);

    expect(Device::count())->toBe(1);
});

it('stores only a hash of the device secret', function () {
    $plain = PairingCode::mintFor($this->user);

    $secret = $this->postJson('/api/pair', ['code' => $plain, 'device_name' => 'Mac'])
        ->json('device_secret');

    $device = Device::sole();

    expect($device->secret_hash)->not->toBe($secret)
        ->and($device->getAttributes())->not->toContain($secret)
        ->and($device->secret_hash)->toBe(app(SecretHasher::class)->hash($secret));
});

it('mints a device secret with at least 256 bits of entropy', function () {
    $plain = PairingCode::mintFor($this->user);

    $secret = $this->postJson('/api/pair', ['code' => $plain, 'device_name' => 'Mac'])
        ->json('device_secret');

    expect(strlen($secret))->toBeGreaterThanOrEqual(43);
});

it('rejects an expired pairing code', function () {
    $plain = PairingCode::mintFor($this->user);

    $this->travel(16)->minutes();

    $this->postJson('/api/pair', ['code' => $plain, 'device_name' => 'Mac'])->assertStatus(422);

    expect(Device::count())->toBe(0);
});

it('rejects an unknown pairing code', function () {
    $this->postJson('/api/pair', ['code' => 'ZZZZZZZZZZ', 'device_name' => 'Mac'])
        ->assertStatus(422);

    expect(Device::count())->toBe(0);
});

it('requires a device name', function () {
    $plain = PairingCode::mintFor($this->user);

    $this->postJson('/api/pair', ['code' => $plain])->assertStatus(422);

    expect(Device::count())->toBe(0);
});

it('rate limits redemption attempts per code prefix', function () {
    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/pair', ['code' => 'ABCDEFGHJK', 'device_name' => 'Mac'])
            ->assertStatus(422);
    }

    $this->postJson('/api/pair', ['code' => 'ABCDEFGHJK', 'device_name' => 'Mac'])
        ->assertStatus(429);
});

it('rate limits redemption globally so the code space cannot be walked across prefixes', function () {
    // Each attempt uses a fresh prefix, so only the global limit can stop it.
    $alphabet = str_split(PairingCode::ALPHABET);

    foreach (range(0, 29) as $i) {
        $prefix = $alphabet[$i % count($alphabet)].$alphabet[intdiv($i, count($alphabet))].'AA';

        $this->postJson('/api/pair', ['code' => $prefix.'FGHJKM', 'device_name' => 'Mac']);
    }

    $this->postJson('/api/pair', ['code' => 'ZZZZFGHJKM', 'device_name' => 'Mac'])
        ->assertStatus(429);
});

it('derives the pairing rate limit key from the code prefix alone, never the ip', function () {
    $key = RedeemPairingCodeRequest::rateLimitKeyFor(
        Request::create('/api/pair', 'POST', ['code' => 'ABCDEFGHJK']),
    );

    expect($key)->toStartWith('pair:')
        ->and($key)->not->toContain('127.0.0.1')
        // Hashed, so the bucket name never carries the code itself.
        ->and($key)->not->toContain('ABCD');
});

it('buckets two codes sharing a prefix under the same rate limit key', function () {
    $first = RedeemPairingCodeRequest::rateLimitKeyFor(
        Request::create('/api/pair', 'POST', ['code' => 'ABCDEFGHJK']),
    );
    $second = RedeemPairingCodeRequest::rateLimitKeyFor(
        Request::create('/api/pair', 'POST', ['code' => 'ABCDZZZZZZ']),
    );

    expect($first)->toBe($second);
});

it('stores no ip address on a device record', function () {
    $plain = PairingCode::mintFor($this->user);
    $this->postJson('/api/pair', ['code' => $plain, 'device_name' => 'Mac'])->assertOk();

    expect(array_keys(Device::sole()->getAttributes()))
        ->not->toContain('ip_address', 'ip', 'last_ip');
});

it('rejects a pairing code belonging to a revoked user path once the code is redeemed', function () {
    $plain = PairingCode::mintFor($this->user);
    $this->postJson('/api/pair', ['code' => $plain, 'device_name' => 'Mac'])->assertOk();

    expect(PairingCode::sole()->redeemed_at)->not->toBeNull();
});
