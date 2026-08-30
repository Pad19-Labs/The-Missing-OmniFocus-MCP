<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * No password column: the relay has no interactive login in Phase 1. Users are
 * created only by `relay:approve`, and every credential lives in a hashed
 * device secret or API token instead.
 */
#[Fillable(['name', 'email'])]
class User extends Model
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /** @return HasMany<Device, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /** @return HasMany<ApiToken, $this> */
    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    /** @return HasMany<PairingCode, $this> */
    public function pairingCodes(): HasMany
    {
        return $this->hasMany(PairingCode::class);
    }
}
