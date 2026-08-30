<?php

namespace App\Console\Commands;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\User;
use Illuminate\Console\Command;

class ApproveAccessRequest extends Command
{
    protected $signature = 'relay:approve {email} {--force : Approve even a previously denied request}';

    protected $description = 'Approve an access request and create the relay user';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $request = AccessRequest::where('email', $email)->first();

        if ($request === null) {
            $this->error("No access request for {$email}.");

            return self::FAILURE;
        }

        if ($request->status === AccessRequestStatus::Denied && ! $this->option('force')) {
            $this->error("The request for {$email} was denied. Re-run with --force to override.");

            return self::FAILURE;
        }

        $request->update([
            'status' => AccessRequestStatus::Approved,
            'reviewed_at' => now(),
        ]);

        User::firstOrCreate(
            ['email' => $email],
            ['name' => $request->name ?? $email],
        );

        $this->info("Approved {$email}. Mint a pairing code with: relay:pair {$email}");

        return self::SUCCESS;
    }
}
