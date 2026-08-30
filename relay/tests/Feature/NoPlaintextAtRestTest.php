<?php

use App\Models\ApiToken;
use App\Models\Device;
use App\Models\PairingCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The relay's central promise: every credential it issues is unrecoverable from
 * its own database. This sweeps every row of every table for the plaintext,
 * rather than trusting each model to have hashed correctly.
 */
function everyStoredValue(): string
{
    $blob = '';

    foreach (DB::select("select name from sqlite_master where type = 'table'") as $table) {
        foreach (DB::table($table->name)->get() as $row) {
            $blob .= json_encode($row);
        }
    }

    return $blob;
}

beforeEach(function () {
    $this->user = User::create(['name' => 'John Watson', 'email' => 'watson@example.test']);
});

it('stores no pairing code, device secret, or api token in plaintext', function () {
    $code = PairingCode::mintFor($this->user);

    $secret = $this->postJson('/api/pair', ['code' => $code, 'device_name' => 'Mac'])
        ->assertOk()
        ->json('device_secret');

    $token = ApiToken::mintFor($this->user);

    $stored = everyStoredValue();

    expect($stored)->not->toContain($code)
        ->and($stored)->not->toContain($secret)
        ->and($stored)->not->toContain($token);
});

it('cannot re-derive a device secret from the stored hash', function () {
    $code = PairingCode::mintFor($this->user);

    $secret = $this->postJson('/api/pair', ['code' => $code, 'device_name' => 'Mac'])
        ->json('device_secret');

    // An attacker holding the database but not APP_KEY cannot even confirm a
    // guess, because the hash is keyed.
    expect(DB::table('devices')->value('secret_hash'))
        ->not->toBe(hash('sha256', $secret));
});

it('hides credential hashes from model serialisation', function () {
    $code = PairingCode::mintFor($this->user);
    $this->postJson('/api/pair', ['code' => $code, 'device_name' => 'Mac'])->assertOk();
    ApiToken::mintFor($this->user);

    expect(Device::sole()->toArray())->not->toHaveKey('secret_hash')
        ->and(ApiToken::sole()->toArray())->not->toHaveKey('token_hash')
        ->and(PairingCode::sole()->toArray())->not->toHaveKey('code_hash');
});
