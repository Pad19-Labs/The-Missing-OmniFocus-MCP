<?php

namespace App\Models;

use App\Support\SecretHasher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PairingCode extends Model
{
    /**
     * Crockford-style alphabet with the characters people misread over a phone
     * or between a screen and a keyboard removed: 0/O, 1/I/L, U/V.
     */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTWXYZ23456789';

    public const LENGTH = 10;

    public const TTL_MINUTES = 15;

    protected $guarded = [];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'redeemed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRedeemable(): bool
    {
        return $this->redeemed_at === null && $this->expires_at->isFuture();
    }

    /**
     * Returns the one and only copy of the plaintext code; only its hash is
     * persisted.
     */
    public static function mintFor(User $user): string
    {
        $code = self::generateCode();

        self::create([
            'user_id' => $user->id,
            'code_hash' => app(SecretHasher::class)->hash($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        return $code;
    }

    public static function generateCode(): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    public static function findRedeemable(#[\SensitiveParameter] string $code): ?self
    {
        return self::query()
            ->whereNull('redeemed_at')
            ->where('expires_at', '>', now())
            ->where('code_hash', app(SecretHasher::class)->hash($code))
            ->first();
    }
}
