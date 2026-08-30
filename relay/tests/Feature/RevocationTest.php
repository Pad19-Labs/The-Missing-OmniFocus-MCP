<?php

use App\Models\ApiToken;
use App\Models\Device;
use App\Models\PairingCode;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->user = User::create(['name' => 'John Watson', 'email' => 'watson@example.test']);

    Route::middleware('relay.token')->get('/_test/whoami', fn () => response()->json(['ok' => true]));
});

it('revokes a device', function () {
    $plain = PairingCode::mintFor($this->user);
    $deviceId = $this->postJson('/api/pair', ['code' => $plain, 'device_name' => 'Mac'])->json('device_id');

    $this->artisan('relay:revoke-device', ['device' => $deviceId])->assertSuccessful();

    $device = Device::find($deviceId);

    expect($device->revoked_at)->not->toBeNull()
        ->and($device->isActive())->toBeFalse();
});

it('fails to revoke an unknown device', function () {
    $this->artisan('relay:revoke-device', ['device' => '01000000-0000-0000-0000-000000000000'])
        ->assertFailed();
});

it('is idempotent when revoking an already revoked device', function () {
    $plain = PairingCode::mintFor($this->user);
    $deviceId = $this->postJson('/api/pair', ['code' => $plain, 'device_name' => 'Mac'])->json('device_id');

    $this->artisan('relay:revoke-device', ['device' => $deviceId])->assertSuccessful();
    $revokedAt = Device::find($deviceId)->revoked_at;

    $this->travel(1)->minute();
    $this->artisan('relay:revoke-device', ['device' => $deviceId])->assertSuccessful();

    expect(Device::find($deviceId)->revoked_at->equalTo($revokedAt))->toBeTrue();
});

it('revokes a token so authentication fails immediately', function () {
    $plain = ApiToken::mintFor($this->user);
    $tokenId = ApiToken::sole()->id;

    $this->withToken($plain)->getJson('/_test/whoami')->assertOk();

    $this->artisan('relay:revoke-token', ['token' => $tokenId])->assertSuccessful();

    $this->withToken($plain)->getJson('/_test/whoami')->assertUnauthorized();
});

it('fails to revoke an unknown token', function () {
    $this->artisan('relay:revoke-token', ['token' => 999])->assertFailed();
});

it('leaves other tokens of the same user working', function () {
    $first = ApiToken::mintFor($this->user);
    $second = ApiToken::mintFor($this->user);

    $this->artisan('relay:revoke-token', ['token' => ApiToken::orderBy('id')->first()->id])
        ->assertSuccessful();

    $this->withToken($first)->getJson('/_test/whoami')->assertUnauthorized();
    $this->withToken($second)->getJson('/_test/whoami')->assertOk();
});

it('reports an active device as active until it is revoked', function () {
    $plain = PairingCode::mintFor($this->user);
    $deviceId = $this->postJson('/api/pair', ['code' => $plain, 'device_name' => 'Mac'])->json('device_id');

    expect(Device::find($deviceId)->isActive())->toBeTrue();
});
