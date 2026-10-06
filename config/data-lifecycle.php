<?php

return [
    // Scheduled destructive maintenance is explicitly enabled by the operator.
    'scheduled' => env('DATA_LIFECYCLE_SCHEDULED', false),
    'inactive_account_days' => (int) env('INACTIVE_ACCOUNT_DAYS', 730),
    'pending_account_days' => (int) env('PENDING_ACCOUNT_DAYS', 30),
    'failed_job_days' => (int) env('FAILED_JOB_DAYS', 7),
    'audit_days' => (int) env('ACCOUNT_AUDIT_DAYS', 90),
    'log_directory' => storage_path('logs'),
    'log_days' => (int) env('LOG_DAILY_DAYS', 14),
    'backup_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    // Only this dedicated disk is managed; external backups require an operator policy.
    'backup_disk' => 'lifecycle_backups',
];
