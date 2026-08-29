<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeletionToken extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'descendants' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function isValidFor(string $type, string $itemId): bool
    {
        return $this->used_at === null
            && $this->expires_at->isFuture()
            && $this->type === $type
            && $this->item_id === $itemId;
    }
}
