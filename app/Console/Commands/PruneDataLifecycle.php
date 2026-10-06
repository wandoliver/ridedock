<?php

namespace App\Console\Commands;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Enums\UserStatus;
use App\Domain\Privacy\Services\AccountQueueData;
use App\Domain\Privacy\Services\DeleteAccount;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class PruneDataLifecycle extends Command
{
    protected $signature = 'app:prune-data {--execute : Apply the displayed retention policy}';
    protected $description = 'Preview or apply account, audit, failed-job, local-log and managed-backup retention';

    public function handle(DeleteAccount $delete, AccountQueueData $queues): int
    {
        foreach (['inactive_account_days', 'pending_account_days', 'failed_job_days', 'audit_days', 'log_days', 'backup_days'] as $key) {
            if ((int) config('data-lifecycle.'.$key) < 1) {
                $this->error('Retention windows must be positive whole days.');
                return self::FAILURE;
            }
        }
        $queues->tables();
        $execute = (bool) $this->option('execute');
        $expired = fn () => User::query()->where('role', UserRole::Member)->where(function ($query): void {
            $query->where(function ($query): void {
                $query->where('status', UserStatus::Active)->whereRaw('COALESCE(last_active_at, created_at) < ?', [now()->subDays(config('data-lifecycle.inactive_account_days'))]);
            })->orWhere(function ($query): void {
                $query->where('status', UserStatus::Pending)->where('created_at', '<', now()->subDays(config('data-lifecycle.pending_account_days')));
            });
        });
        $this->line('Expired member accounts: '.$expired()->count().' (administrators are exempt)');
        if ($execute) {
            foreach ($expired()->orderBy('id')->cursor() as $user) {
                // Recheck after taking the same lock used by authenticated activity tracking.
                DB::transaction(function () use ($user, $expired, $delete): void {
                    User::query()->whereKey($user->id)->lockForUpdate()->first();
                    if ($expired()->whereKey($user->id)->exists()) {
                        $delete->handle($user);
                    }
                });
            }
        }
        $audit = DB::table('account_audit_events')->where('created_at', '<', now()->subDays(config('data-lifecycle.audit_days')));
        $this->line('Expired audit events: '.$audit->count());
        if ($execute) {
            $audit->delete();
            DB::table('user_oauth_states')->where('expires_at', '<', now())->delete();
        }
        if (config('queue.failed.driver') === 'database-uuids') {
            $failed = DB::connection(config('queue.failed.database'))->table(config('queue.failed.table'))
                ->where('failed_at', '<', now()->subDays(config('data-lifecycle.failed_job_days')));
            $this->line('Expired failed jobs: '.$failed->count());
            if ($execute) {
                $failed->delete();
            }
        }
        foreach (glob(config('data-lifecycle.log_directory').'/laravel*.log') ?: [] as $file) {
            if (! is_link($file) && filemtime($file) < now()->subDays(config('data-lifecycle.log_days'))->timestamp) {
                $this->line('Expired application log file');
                if ($execute && ! unlink($file)) {
                    throw new RuntimeException('Unable to remove expired log.');
                }
            }
        }
        $name = config('data-lifecycle.backup_disk');
        if (config("filesystems.disks.$name.visibility") === 'public' || config("filesystems.disks.$name.serve")) {
            throw new RuntimeException('Managed backups require a private non-serving disk.');
        }
        $disk = Storage::disk($name);
        foreach ($disk->allFiles() as $file) {
            if ($disk->lastModified($file) < now()->subDays(config('data-lifecycle.backup_days'))->timestamp) {
                $this->line('Expired managed backup file');
                if ($execute && ! $disk->delete($file)) {
                    throw new RuntimeException('Unable to remove expired managed backup.');
                }
            }
        }
        $this->info($execute ? 'Retention applied.' : 'Preview only. Use --execute to apply.');
        return self::SUCCESS;
    }
}
