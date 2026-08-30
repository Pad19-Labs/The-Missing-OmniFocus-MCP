<?php

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\User;

it('accepts an access request and records it as pending', function () {
    $response = $this->postJson('/api/access-requests', [
        'email' => 'watson@example.test',
        'name' => 'John Watson',
    ]);

    $response->assertCreated()->assertJson(['status' => 'pending']);

    $request = AccessRequest::sole();

    expect($request->email)->toBe('watson@example.test')
        ->and($request->name)->toBe('John Watson')
        ->and($request->status)->toBe(AccessRequestStatus::Pending);
});

it('requires a valid email', function () {
    $this->postJson('/api/access-requests', ['email' => 'not-an-email'])
        ->assertStatus(422);

    expect(AccessRequest::count())->toBe(0);
});

it('normalises the email to lower case', function () {
    $this->postJson('/api/access-requests', ['email' => 'Watson@Example.Test'])
        ->assertCreated();

    expect(AccessRequest::sole()->email)->toBe('watson@example.test');
});

it('does not duplicate a pending request for the same email', function () {
    $this->postJson('/api/access-requests', ['email' => 'watson@example.test'])->assertCreated();
    $this->postJson('/api/access-requests', ['email' => 'watson@example.test'])->assertCreated();

    expect(AccessRequest::count())->toBe(1);
});

it('does not reveal whether an email was already approved or denied', function () {
    AccessRequest::create([
        'email' => 'watson@example.test',
        'status' => AccessRequestStatus::Denied,
    ]);

    $this->postJson('/api/access-requests', ['email' => 'watson@example.test'])
        ->assertCreated()
        ->assertJson(['status' => 'pending']);

    expect(AccessRequest::sole()->status)->toBe(AccessRequestStatus::Denied);
});

it('stores no ip address for an access request', function () {
    $this->postJson('/api/access-requests', ['email' => 'watson@example.test'])->assertCreated();

    expect(array_keys(AccessRequest::sole()->getAttributes()))
        ->not->toContain('ip_address', 'ip');
});

it('rate limits access requests', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/access-requests', ['email' => "watson{$i}@example.test"])
            ->assertCreated();
    }

    $this->postJson('/api/access-requests', ['email' => 'watson6@example.test'])
        ->assertStatus(429);
});

it('approves a pending request and creates the user', function () {
    AccessRequest::create([
        'email' => 'watson@example.test',
        'name' => 'John Watson',
        'status' => AccessRequestStatus::Pending,
    ]);

    $this->artisan('relay:approve', ['email' => 'watson@example.test'])
        ->assertSuccessful();

    expect(AccessRequest::sole()->status)->toBe(AccessRequestStatus::Approved)
        ->and(AccessRequest::sole()->reviewed_at)->not->toBeNull()
        ->and(User::where('email', 'watson@example.test')->exists())->toBeTrue();
});

it('fails to approve an email with no access request', function () {
    $this->artisan('relay:approve', ['email' => 'moriarty@example.test'])
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('is idempotent when approving an already approved request', function () {
    AccessRequest::create(['email' => 'watson@example.test', 'status' => AccessRequestStatus::Pending]);

    $this->artisan('relay:approve', ['email' => 'watson@example.test'])->assertSuccessful();
    $this->artisan('relay:approve', ['email' => 'watson@example.test'])->assertSuccessful();

    expect(User::count())->toBe(1);
});

it('denies a pending request without creating a user', function () {
    AccessRequest::create(['email' => 'moriarty@example.test', 'status' => AccessRequestStatus::Pending]);

    $this->artisan('relay:deny', ['email' => 'moriarty@example.test'])->assertSuccessful();

    expect(AccessRequest::sole()->status)->toBe(AccessRequestStatus::Denied)
        ->and(User::count())->toBe(0);
});

it('refuses to approve a denied request without the force flag', function () {
    AccessRequest::create(['email' => 'moriarty@example.test', 'status' => AccessRequestStatus::Denied]);

    $this->artisan('relay:approve', ['email' => 'moriarty@example.test'])->assertFailed();

    expect(User::count())->toBe(0);
});

it('has no open signup route', function () {
    $this->postJson('/register', ['email' => 'moriarty@example.test'])->assertNotFound();
});
