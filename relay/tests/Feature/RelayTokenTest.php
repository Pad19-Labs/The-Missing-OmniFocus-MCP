<?php

use App\Models\ApiToken;
use App\Models\User;
use App\Support\SecretHasher;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->user = User::create(['name' => 'John Watson', 'email' => 'watson@example.test']);

    Route::middleware('relay.token')->get('/_test/whoami', fn () => response()->json([
        'user_id' => request()->attributes->get('relay_user')->id,
        'token_id' => request()->attributes->get('relay_token')->id,
    ]));
});

it('mints a token for an approved user and shows it once', function () {
    $this->artisan('relay:token', ['email' => 'watson@example.test'])->assertSuccessful();

    $token = ApiToken::sole();

    expect($token->user_id)->toBe($this->user->id)
        ->and($token->revoked_at)->toBeNull()
        ->and($token->last_used_at)->toBeNull();
});

it('refuses to mint a token for an unknown user', function () {
    $this->artisan('relay:token', ['email' => 'moriarty@example.test'])->assertFailed();

    expect(ApiToken::count())->toBe(0);
});

it('stores only a hash of the token', function () {
    $plain = ApiToken::mintFor($this->user);

    $token = ApiToken::sole();

    expect($token->token_hash)->not->toBe($plain)
        ->and($token->getAttributes())->not->toContain($plain)
        ->and($token->token_hash)->toBe(app(SecretHasher::class)->hash($plain));
});

it('authenticates a request bearing a valid token', function () {
    $plain = ApiToken::mintFor($this->user);

    $this->withToken($plain)->getJson('/_test/whoami')
        ->assertOk()
        ->assertJson(['user_id' => $this->user->id, 'token_id' => ApiToken::sole()->id]);
});

it('records last_used_at on a successful authentication', function () {
    $plain = ApiToken::mintFor($this->user);

    $this->withToken($plain)->getJson('/_test/whoami')->assertOk();

    expect(ApiToken::sole()->last_used_at)->not->toBeNull();
});

it('fails closed with no authorization header', function () {
    $this->getJson('/_test/whoami')->assertUnauthorized();
});

it('fails closed with an empty bearer token', function () {
    $this->withHeader('Authorization', 'Bearer ')->getJson('/_test/whoami')->assertUnauthorized();
});

it('fails closed with a garbage bearer token', function () {
    ApiToken::mintFor($this->user);

    $this->withToken('not-a-real-token')->getJson('/_test/whoami')->assertUnauthorized();
});

it('rejects a token whose plaintext was truncated', function () {
    $plain = ApiToken::mintFor($this->user);

    $this->withToken(substr($plain, 0, -1))->getJson('/_test/whoami')->assertUnauthorized();
});

it('rejects a revoked token', function () {
    $plain = ApiToken::mintFor($this->user);
    ApiToken::sole()->update(['revoked_at' => now()]);

    $this->withToken($plain)->getJson('/_test/whoami')->assertUnauthorized();
});

it('rejects a token whose user no longer exists', function () {
    $plain = ApiToken::mintFor($this->user);
    $this->user->delete();

    $this->withToken($plain)->getJson('/_test/whoami')->assertUnauthorized();
});

it('does not leak whether the token exists in the failure body', function () {
    $plain = ApiToken::mintFor($this->user);
    ApiToken::sole()->update(['revoked_at' => now()]);

    $revoked = $this->withToken($plain)->getJson('/_test/whoami');
    $unknown = $this->withToken('totally-unknown-token')->getJson('/_test/whoami');

    expect($revoked->getContent())->toBe($unknown->getContent());
});

it('looks the token up by an indexed hash rather than scanning every row', function () {
    $plain = ApiToken::mintFor($this->user);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $this->withToken($plain)->getJson('/_test/whoami')->assertOk();

    $selects = array_values(array_filter($queries, fn ($sql) => str_starts_with(strtolower($sql), 'select')));

    expect($selects)->not->toBeEmpty();

    foreach ($selects as $sql) {
        expect(strtolower($sql))->toContain('where');
    }
});
