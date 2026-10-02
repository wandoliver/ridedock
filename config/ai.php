<?php

return [
    'queue_connection' => env('AI_QUEUE_CONNECTION', 'ai'),

    'provider' => env('AI_PROVIDER', 'openai'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 60),
        ],
    ],
];
