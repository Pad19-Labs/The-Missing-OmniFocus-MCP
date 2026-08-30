<?php

namespace App\Models;

use App\Support\SecretHasher;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['secret_hash'];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
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
     * Mints the device and returns the one and only copy of its plaintext
     * secret; the relay keeps a hash and can never reproduce it.
     *
     * @return array{0: self, 1: string}
     */
    public static function register(User $user, string $name): array
    {
        $secret = SecretHasher::generateSecret();

        $device = self::create([
            'user_id' => $user->id,
            'name' => $name,
            'secret_hash' => app(SecretHasher::class)->hash($secret),
        ]);

        return [$device, $secret];
    }

    /**
     * Phase 2 (tunnel auth) resolves a connecting device through here, so the
     * revocation check lives with the lookup and cannot be forgotten.
     */
    public static function findActiveBySecret(#[\SensitiveParameter] string $secret): ?self
    {
        return self::query()
            ->whereNull('revoked_at')
            ->where('secret_hash', app(SecretHasher::class)->hash($secret))
            ->first();
    }
}
