<?php

namespace App\Console\Commands;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Enums\UserStatus;
use App\Domain\Accounts\Services\AccountAudit;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {email} {--name=} {--locale=en}';

    protected $description = 'Promote an existing active user or interactively create an administrator';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $locale = $this->option('locale');
        if (Validator::make(['email' => $email, 'locale' => $locale], [
            'email' => ['required', 'email', 'max:255'], 'locale' => ['required', Rule::in(['de', 'en'])],
        ])->fails()) {
            $this->error('Provide a valid email address and locale (de or en).');
            return self::FAILURE;
        }

        $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing !== null) {
            return DB::transaction(function () use ($existing): int {
                $user = User::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
                if ($user->status !== UserStatus::Active) {
                    $this->error('Pending users must activate their account before promotion.');
                    return self::FAILURE;
                }
                if ($user->role !== UserRole::Admin) {
                    $user->role = UserRole::Admin;
                    $user->save();
                    AccountAudit::record('admin.promoted', $user);
                }
                $this->info('Administrator access granted; existing credentials are unchanged.');
                return self::SUCCESS;
            });
        }

        if (! $this->input->isInteractive()) {
            $this->error('Creating an administrator requires interactive password prompts.');
            return self::FAILURE;
        }

        $name = $this->option('name') ?? $this->ask('Name');
        $password = $this->secret('Password (at least 12 characters)');
        $confirmation = $this->secret('Confirm password');
        if (Validator::make(['name' => $name, 'password' => $password, 'password_confirmation' => $confirmation], [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::min(12), 'max:255'],
        ])->fails()) {
            $this->error('Provide a name and matching passwords of at least 12 characters.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $locale, $password): void {
            $user = new User(['name' => $name, 'email' => $email, 'locale' => $locale, 'password' => $password]);
            $user->role = UserRole::Admin;
            $user->status = UserStatus::Active;
            $user->save();
            AccountAudit::record('admin.created', $user);
        });
        $this->info('Administrator created. Sign in with the chosen password.');

        return self::SUCCESS;
    }
}
