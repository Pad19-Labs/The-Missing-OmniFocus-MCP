<?php

namespace App\Models;

use App\Enums\AccessRequestStatus;
use Illuminate\Database\Eloquent\Model;

class AccessRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => AccessRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === AccessRequestStatus::Pending;
    }
}
