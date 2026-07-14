<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MintApiToken extends Command
{
    /**
     * @var string
     */
    protected $signature = 'planstack:token {user : User id or e-mail} {--name=planstack : Label for the token}';

    /**
     * @var string
     */
    protected $description = 'Mint a personal access token for a user and print it (for API access)';

    public function handle(): int
    {
        $needle = $this->argument('user');

        $user = is_numeric($needle)
            ? User::find((int) $needle)
            : User::where('email', $needle)->first();

        if (! $user) {
            $this->error("No user found for \"{$needle}\".");

            return self::FAILURE;
        }

        $token = $user->createToken($this->option('name'));

        $this->info("Token für {$user->name} <{$user->email}> (#{$user->id}):");
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
