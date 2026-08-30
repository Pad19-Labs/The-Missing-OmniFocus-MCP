<?php

namespace App\Console\Commands;

use App\Models\PairingCode;
use App\Models\User;
use Illuminate\Console\Command;

class MintPairingCode extends Command
{
    protected $signature = 'relay:pair {email}';

    protected $description = 'Mint a single-use pairing code so an approved user can link a Mac';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("No approved user for {$email}. Run relay:approve first.");

            return self::FAILURE;
        }

        $code = PairingCode::mintFor($user);

        $this->info("Pairing code for {$email}: {$code}");
        $this->line(sprintf(
            'Single use, expires in %d minutes. This is the only time it is shown.',
            PairingCode::TTL_MINUTES,
        ));

        return self::SUCCESS;
    }
}
