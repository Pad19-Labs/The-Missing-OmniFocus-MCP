<?php

namespace App\Models;

use App\Support\SecretHasher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiToken extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * Returns the one and only copy of the plaintext token; only its hash is
     * persisted.
     */
    public static function mintFor(User $user, ?string $name = null): string
    {
        $token = SecretHasher::generateSecret();

        self::create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => app(SecretHasher::class)->hash($token),
        ]);

        return $token;
    }

    /**
     * Single indexed lookup on the keyed hash: constant work regardless of how
     * many tokens exist, so response time leaks nothing about the token space.
     */
    public static function findActive(#[\SensitiveParameter] string $token): ?self
    {
        return self::query()
            ->with('user')
            ->whereNull('revoked_at')
            ->where('token_hash', app(SecretHasher::class)->hash($token))
            ->first();
    }
}
