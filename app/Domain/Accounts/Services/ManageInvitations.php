<?php

namespace App\Domain\Accounts\Services;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Enums\UserStatus;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ManageInvitations
{
    /** @param array{name: string, email: string, locale: string} $attributes
     * @return array{UserInvitation, string}
     */
    public function create(User $actor, array $attributes): array
    {
        Gate::forUser($actor)->authorize('manage-users');

        return DB::transaction(function () use ($actor, $attributes): array {
            if (User::query()->whereRaw('LOWER(email) = ?', [$attributes['email']])->exists()) {
                throw ValidationException::withMessages(['email' => __('accounts.email_taken')]);
            }

            $user = new User($attributes + ['password' => Str::password(64)]);
            $user->role = UserRole::Member;
            $user->status = UserStatus::Pending;
            $user->save();
            AccountAudit::record('user.created', $user, $actor);

            return $this->issue($actor, $user);
        });
    }

    /** @return array{UserInvitation, string} */
    public function reissue(User $actor, User $user): array
    {
        Gate::forUser($actor)->authorize('manage-users');

        return DB::transaction(function () use ($actor, $user): array {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === UserStatus::Pending, 409);

            return $this->issue($actor, $locked);
        });
    }

    public function revoke(User $actor, User $user): void
    {
        Gate::forUser($actor)->authorize('manage-users');

        DB::transaction(function () use ($actor, $user): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === UserStatus::Pending, 409);
            $this->revokeOutstanding($locked);
            AccountAudit::record('invitation.revoked', $locked, $actor);
        });
    }

    public function find(string $token): UserInvitation
    {
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/', $token) === 1, 404);
        $invitation = UserInvitation::query()->with('user')->where('token_hash', hash('sha256', $token))->firstOrFail();
        $this->assertUsable($invitation, $invitation->user);

        return $invitation;
    }

    public function accept(string $token, string $password): User
    {
        $invitation = $this->find($token);

        return DB::transaction(function () use ($invitation, $password): User {
            // Every lifecycle action locks the user first, then the invitation.
            $user = User::query()->whereKey($invitation->user_id)->lockForUpdate()->firstOrFail();
            $locked = UserInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            $this->assertUsable($locked, $user);

            $user->password = $password;
            $user->status = UserStatus::Active;
            $user->last_active_at = now();
            if ($locked->emailed_at !== null) {
                $user->email_verified_at = now();
            }
            $user->save();
            $locked->accepted_at = now();
            $locked->save();
            $this->revokeOutstanding($user);
            AccountAudit::record('invitation.accepted', $user, $user);

            return $user;
        });
    }

    /** @return array{UserInvitation, string} */
    private function issue(User $actor, User $user): array
    {
        $this->revokeOutstanding($user);
        $token = bin2hex(random_bytes(32));
        $invitation = new UserInvitation();
        $invitation->user_id = $user->id;
        $invitation->invited_by = $actor->id;
        $invitation->email = $user->email;
        $invitation->token_hash = hash('sha256', $token);
        $invitation->expires_at = now()->addDays(7);
        $invitation->save();
        AccountAudit::record('invitation.created', $user, $actor);

        return [$invitation, $token];
    }

    private function revokeOutstanding(User $user): void
    {
        UserInvitation::query()->where('user_id', $user->id)
            ->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    private function assertUsable(UserInvitation $invitation, User $user): void
    {
        abort_unless($invitation->isUsable() && $user->status === UserStatus::Pending
            && $user->email === $invitation->email && $user->role === UserRole::Member, 404);
    }
}
