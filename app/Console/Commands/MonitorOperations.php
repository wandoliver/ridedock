<?php

namespace App\Console\Commands;

use App\Domain\Operations\Services\OperationalEvents;
use App\Domain\Operations\Services\OperationsOverview;
use App\Domain\Operations\Services\ProviderOperations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class MonitorOperations extends Command
{
    protected $signature = 'app:monitor-operations {--json : Output a machine-readable snapshot} {--record : Record warnings and expire old operational events}';
    protected $description = 'Report provider failures, AI capacity and monthly budget warnings';

    public function handle(OperationsOverview $overview, OperationalEvents $events, ProviderOperations $providers): int
    {
        $snapshot = $overview->snapshot();
        $warnings = [];
        if ($snapshot['usage']['budget_warning']) {
            $warnings[] = 'ai_budget_warning';
        }
        if ($snapshot['oldest_pending_seconds'] > config('operations.stale_minutes') * 60) {
            $warnings[] = 'ai_queue_stalled';
        }
        if ($snapshot['provider_errors'] > 0 || $snapshot['provider_stale'] > 0) {
            $warnings[] = 'provider_attention';
        }
        if ($snapshot['failed_last_day'] > 0) {
            $warnings[] = 'ai_failures';
        }
        if ($snapshot['usage']['unknown_costs'] > 0) {
            $warnings[] = 'ai_cost_incomplete';
        }
        $snapshot['warnings'] = $warnings;
        if ($this->option('record')) {
            foreach ($warnings as $code) {
                $period = $code === 'ai_budget_warning' ? now()->format('Y-m') : now()->format('Y-m-d-H');
                if ($providers->cache()->add('operations-warning:'.$code.':'.$period, true, $code === 'ai_budget_warning' ? 2678400 : 3600)) {
                    $events->record('monitor', $code);
                }
            }
            DB::table('operation_events')->where('created_at', '<', now()->subDays(config('operations.event_days')))->delete();
        }
        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR));
        } else {
            $this->line('AI pending: '.$snapshot['pending'].'; running: '.$snapshot['running'].'; oldest pending seconds: '.$snapshot['oldest_pending_seconds']);
            $this->line('Monthly estimated USD: '.number_format($snapshot['usage']['cost_usd'], 4, '.', '').'; unknown-cost attempts: '.$snapshot['usage']['unknown_costs']);
            $this->line('Provider errors: '.$snapshot['provider_errors'].'; waiting: '.$snapshot['provider_waiting'].'; stale: '.$snapshot['provider_stale']);
            foreach ($warnings as $warning) {
                $this->warn($warning);
            }
        }
        return $warnings === [] ? self::SUCCESS : self::FAILURE;
    }
}
