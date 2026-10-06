<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use App\Domain\Accounts\Enums\UserStatus;

class CreateOwnerToken extends Command
{
    protected $signature = 'app:create-owner-token {email} {--token-name=chatgpt-analysis}';

    protected $description = 'Create an API token for an existing active account';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($user === null || $user->status !== UserStatus::Active) {
            $this->error('An existing active account is required. Create an administrator or invite the user first.');
            return self::FAILURE;
        }
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
