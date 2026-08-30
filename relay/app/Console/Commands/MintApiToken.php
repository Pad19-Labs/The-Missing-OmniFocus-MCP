<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Console\Command;

class MintApiToken extends Command
{
    protected $signature = 'relay:token {email} {--name= : A label to tell this token apart later}';

    protected $description = 'Mint a bearer token for an approved user';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("No approved user for {$email}. Run relay:approve first.");

            return self::FAILURE;
        }

        $token = ApiToken::mintFor($user, $this->option('name'));

        $this->info("Bearer token for {$email}: {$token}");
        $this->line('Stored hashed. This is the only time it is shown.');

        return self::SUCCESS;
    }
}
