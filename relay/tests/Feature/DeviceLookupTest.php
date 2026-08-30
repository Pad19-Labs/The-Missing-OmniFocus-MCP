<?php

use App\Models\Device;
use App\Models\User;

/*
 * Device::findActiveBySecret is the method Phase 2's tunnel authentication
 * will ride on — its revocation check must be impossible to break silently.
 */

function lookupUser(): User
{
    return User::create(['name' => 'Device Lookup', 'email' => 'device-lookup@example.test']);
}

it('finds a device by its plaintext secret', function () {
    [$device, $secret] = Device::register(lookupUser(), 'Peters-Mac');

    $found = Device::findActiveBySecret($secret);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($device->id);
});

it('returns null for an unknown secret', function () {
    Device::register(lookupUser(), 'Peters-Mac');

    expect(Device::findActiveBySecret('not-a-real-secret'))->toBeNull();
});

it('returns null for a revoked device even with the correct secret', function () {
    [$device, $secret] = Device::register(lookupUser(), 'Peters-Mac');

    $device->update(['revoked_at' => now()]);

    expect(Device::findActiveBySecret($secret))->toBeNull();
});

it('does not resolve one device with another device secret', function () {
    $user = lookupUser();
    [$deviceA] = Device::register($user, 'Mac A');
    [, $secretB] = Device::register($user, 'Mac B');

    $found = Device::findActiveBySecret($secretB);

    expect($found->name)->toBe('Mac B')
        ->and($found->id)->not->toBe($deviceA->id);
});
