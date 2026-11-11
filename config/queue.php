<?php

declare(strict_types=1);

return [
    'default' => env('QUEUE_CONNECTION', 'redis'),
    'connections' => [
        'sync' => ['driver' => 'sync'],
        'redis' => [
            'driver' => 'redis', 'connection' => 'default', 'queue' => 'generations',
            'retry_after' => 120, 'block_for' => 5, 'after_commit' => true,
        ],
    ],
    'failed' => ['driver' => 'database-uuids', 'database' => 'mysql', 'table' => 'failed_jobs'],
];
