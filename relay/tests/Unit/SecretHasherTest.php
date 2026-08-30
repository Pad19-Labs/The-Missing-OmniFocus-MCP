<?php

use App\Support\SecretHasher;

beforeEach(function () {
    $this->hasher = app(SecretHasher::class);
});

it('produces a deterministic hash for the same secret', function () {
    expect($this->hasher->hash('a-secret'))->toBe($this->hasher->hash('a-secret'));
});

it('produces a different hash for a different secret', function () {
    expect($this->hasher->hash('a-secret'))->not->toBe($this->hasher->hash('b-secret'));
});

it('never returns the plaintext', function () {
    expect($this->hasher->hash('a-secret'))->not->toContain('a-secret');
});

it('returns a fixed length hex digest', function () {
    expect($this->hasher->hash('short'))->toHaveLength(64)
        ->and($this->hasher->hash(str_repeat('x', 4096)))->toHaveLength(64);
});

it('is keyed by the application key so hashes are not portable between installs', function () {
    $ours = $this->hasher->hash('a-secret');

    expect($ours)->not->toBe(hash('sha256', 'a-secret'));
});

it('matches a correct secret against its hash', function () {
    expect($this->hasher->matches('a-secret', $this->hasher->hash('a-secret')))->toBeTrue();
});

it('rejects an incorrect secret', function () {
    expect($this->hasher->matches('b-secret', $this->hasher->hash('a-secret')))->toBeFalse();
});

it('rejects a mismatched hash without throwing on differing lengths', function () {
    expect($this->hasher->matches('a-secret', 'short'))->toBeFalse();
});

it('generates secrets with at least 256 bits of entropy', function () {
    $secret = SecretHasher::generateSecret();

    expect(strlen($secret))->toBeGreaterThanOrEqual(43);
});

it('generates a different secret every time', function () {
    $secrets = array_map(fn () => SecretHasher::generateSecret(), range(1, 100));

    expect(array_unique($secrets))->toHaveCount(100);
});

it('generates url safe secrets', function () {
    foreach (range(1, 20) as $ignored) {
        expect(SecretHasher::generateSecret())->toMatch('/^[A-Za-z0-9_-]+$/');
    }
});
