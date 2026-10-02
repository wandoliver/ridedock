<?php

return [
    'title' => 'Training monotony and load balance',
    'period' => 'Complete calendar days through :date; today is excluded.',
    'monotony' => 'Monotony · 7 days', 'load_balance' => 'Load balance · 42 / 7 days',
    'monotony_hint' => 'Mean daily load / standard deviation of the seven daily values.',
    'load_balance_hint' => '42-day mean minus 7-day mean, in RPE-minutes per day. Positive: the last week is below the longer mean.',
    'strain' => 'Load × monotony', 'details' => 'Daily values, coverage and calculation',
    'coverage' => ':days days: :rated of :total rides have duration and RPE; :missing unknown loads.',
    'formula' => 'Daily load = sum of ride minutes × RPE. Monotony uses population standard deviation including days with no recorded ride. Balance compares simple daily means; no exponential fitness modelling.',
    'insufficient_history' => 'Recording history does not reach the beginning of the required period yet.',
    'incomplete_load' => 'At least one ride lacks duration or valid RPE; its load remains unknown.',
    'no_load' => 'No positive load recorded in the required period.',
    'no_variation' => 'Identical daily loads: zero standard deviation, monotony undefined.',
    'recording_limit' => 'Days with no recorded ride count as 0 within available history. Missing imports and other sports are not detected; these values describe only your recorded cycling.',
    'freshness_limit' => 'No freshness or recovery assessment: direct recovery data is missing. Monotony and balance predict neither training readiness nor injury risk.',
];
