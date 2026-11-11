<?php

declare(strict_types=1);

return [
    'driver' => env('AI_DRIVER', 'fake'),
    'openai' => ['key' => env('OPENAI_API_KEY', ''), 'model' => env('OPENAI_MODEL', '')],
    'max_attempts' => 3,
    'processing_timeout_seconds' => 300,
    'usage_cache_seconds' => 60,
];
