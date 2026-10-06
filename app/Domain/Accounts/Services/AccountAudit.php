<?php

namespace App\Domain\Accounts\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AccountAudit
{
    public static function record(string $event, User $subject, ?User $actor = null): void
    {
        DB::table('account_audit_events')->insert([
            'event' => $event,
            'subject_user_id' => $subject->id,
            'actor_user_id' => $actor?->id,
            'created_at' => now(),
        ]);
    }
}
