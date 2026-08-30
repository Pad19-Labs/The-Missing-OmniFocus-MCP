<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Keyed SHA-256 HMAC rather than Argon2id, deliberately.
 *
 * Argon2id exists to make brute force expensive against LOW-entropy secrets
 * (human passwords). The long-lived secrets hashed here — device secrets and
 * API tokens — are machine-generated with 256 bits of entropy, so there is
 * nothing to brute force: an attacker who steals the database gains no shortcut
 * a slow KDF would deny them. Pairing codes are shorter (~49 bits: 10 chars of
 * a 29-character alphabet, chosen for typability); they are NOT protected by
 * entropy alone but by being single-use, expiring in 15 minutes, and
 * rate-limited at redemption — an offline brute force against a stolen
 * database also requires APP_KEY and must win within the code's lifetime.
 *
 * What a deterministic keyed hash buys us instead is decisive: an O(1) indexed
 * lookup of a presented credential. Argon2id salts every row, forcing a full
 * table scan with one expensive verify per row — that is both a timing oracle
 * (auth latency scales with row count and match position) and a trivial CPU DoS.
 * Keying with APP_KEY means a leaked database alone cannot be used to build a
 * lookup table, since the attacker also needs the application key.
 *
 * Consequence of keying with APP_KEY: rotating APP_KEY invalidates every
 * stored credential hash at once (devices must re-pair, tokens must be
 * re-minted). Multi-key verification is deliberately unsupported in v1.
 */
final readonly class SecretHasher
{
    public function __construct(private string $key)
    {
        if ($key === '') {
            throw new \InvalidArgumentException('SecretHasher requires a non-empty key; APP_KEY is not configured.');
        }
    }

    public function hash(#[\SensitiveParameter] string $secret): string
    {
        return hash_hmac('sha256', $secret, $this->key);
    }

    public function matches(#[\SensitiveParameter] string $secret, string $hash): bool
    {
        return hash_equals($hash, $this->hash($secret));
    }

    public static function generateSecret(int $bytes = 32): string
    {
        return Str::replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes($bytes)));
    }
}
