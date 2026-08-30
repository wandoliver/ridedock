<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateOwnerToken extends Command
{
    protected $signature = 'app:create-owner-token {email} {--name=Owner} {--token-name=chatgpt-analysis}';

    protected $description = 'Create the local owner account and a one-time API token';

    public function handle(): int
    {
        $user = User::query()->firstOrCreate(
            ['email' => $this->argument('email')],
            ['name' => $this->option('name'), 'password' => Str::password(40)],
        );
        $token = $user->createToken($this->option('token-name'), [
            'activities:read',
            'activities:write',
            'connections:write',
        ])->plainTextToken;

        $this->warn('Copy this token now; it will not be shown again:');
        $this->line($token);

        return self::SUCCESS;
    }
}
