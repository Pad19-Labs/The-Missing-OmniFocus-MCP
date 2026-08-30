<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;

class RevokeDevice extends Command
{
    protected $signature = 'relay:revoke-device {device : The device UUID}';

    protected $description = 'Revoke a paired device so it can no longer authenticate';

    public function handle(): int
    {
        $device = Device::find($this->argument('device'));

        if ($device === null) {
            $this->error('No such device.');

            return self::FAILURE;
        }

        if ($device->isActive()) {
            $device->update(['revoked_at' => now()]);
        }

        // Phase 2 also drops any live tunnel held for this device; the
        // revoked_at check already gates every reconnect.
        $this->info("Revoked device {$device->id} ({$device->name}).");

        return self::SUCCESS;
    }
}
