<?php

namespace App\Domain\Privacy\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteAccount
{
    public function __construct(private readonly PrivateSourceFiles $files, private readonly AccountQueueData $queues) {}

    public function handle(User $user): void
    {
        // Fail before touching files if the configured queue cannot be cleaned safely.
        $this->queues->tables();
        DB::transaction(function () use ($user): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }
            // All import/file writers lock this same row. Delete files before losing references.
            $this->files->remove($locked);
            $this->queues->remove($locked);
            foreach (DB::table('user_oauth_states')->where('user_id', $locked->id)->get() as $state) {
                \Illuminate\Support\Facades\Cache::forget(\Illuminate\Support\Facades\Crypt::decryptString($state->cache_key));
            }
            $locked->tokens()->delete();
            DB::table('sessions')->where('user_id', $locked->id)->delete();
            DB::table('password_reset_tokens')->where('email', $locked->email)->delete();
            DB::table('account_audit_events')->where('actor_user_id', $locked->id)->orWhere('subject_user_id', $locked->id)->delete();
            // All profiles, zones, invitations, connections, rides, streams, events and analyses cascade.
            $locked->delete();
        });
    }
}
