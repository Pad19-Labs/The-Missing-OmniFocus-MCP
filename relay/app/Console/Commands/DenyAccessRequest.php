<?php

namespace App\Console\Commands;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use Illuminate\Console\Command;

class DenyAccessRequest extends Command
{
    protected $signature = 'relay:deny {email}';

    protected $description = 'Deny an access request';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $request = AccessRequest::where('email', $email)->first();

        if ($request === null) {
            $this->error("No access request for {$email}.");

            return self::FAILURE;
        }

        $request->update([
            'status' => AccessRequestStatus::Denied,
            'reviewed_at' => now(),
        ]);

        $this->info("Denied {$email}.");

        return self::SUCCESS;
    }
}
