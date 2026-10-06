<?php

return [
    'hosted' => (bool) env('HOSTED_SERVICE', false),
    'cache_store' => env('OPERATIONS_CACHE_STORE'),
    'lock_seconds' => 360,
    'sync_slice_seconds' => 180,
    'event_days' => 30,
    'usage_days' => 400,
    'stale_minutes' => 15,
    'scheduled_monitor' => (bool) env('OPERATIONS_MONITOR_SCHEDULED', false),
    'ai' => [
        'monthly_activity_quota' => (int) env('AI_MONTHLY_ACTIVITY_QUOTA', 100),
        'monthly_plan_quota' => (int) env('AI_MONTHLY_PLAN_QUOTA', 30),
        'user_pending_limit' => (int) env('AI_USER_PENDING_LIMIT', 2),
        'pending_limit' => (int) env('AI_PENDING_LIMIT', 100),
        'concurrency' => (int) env('AI_CONCURRENCY', 2),
        'requests_per_minute' => (int) env('AI_REQUESTS_PER_MINUTE', 20),
        'monthly_budget_usd' => (float) env('AI_MONTHLY_BUDGET_USD', 0),
        'budget_warning_percent' => (int) env('AI_BUDGET_WARNING_PERCENT', 80),
        // Operator-supplied rates for the exact configured model; no bundled price assumptions.
        'pricing_model' => env('AI_PRICING_MODEL'),
        'input_usd_per_million' => env('AI_INPUT_USD_PER_MILLION'),
        'cached_input_usd_per_million' => env('AI_CACHED_INPUT_USD_PER_MILLION'),
        'output_usd_per_million' => env('AI_OUTPUT_USD_PER_MILLION'),
        'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 12000),
    ],
];
