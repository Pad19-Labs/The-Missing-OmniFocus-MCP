<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use Illuminate\Console\Command;

class RevokeApiToken extends Command
{
    protected $signature = 'relay:revoke-token {token : The token id, not the token itself}';

    protected $description = 'Revoke a bearer token so it can no longer authenticate';

    public function handle(): int
    {
        $token = ApiToken::find($this->argument('token'));

        if ($token === null) {
            $this->error('No such token.');

            return self::FAILURE;
        }

        if ($token->isActive()) {
            $token->update(['revoked_at' => now()]);
        }

        $this->info("Revoked token {$token->id}.");

        return self::SUCCESS;
    }
}
