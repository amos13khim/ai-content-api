<?php

declare(strict_types=1);

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;

return [
    'default' => env('LOG_CHANNEL', 'stderr'),
    'deprecations' => ['channel' => 'null', 'trace' => false],
    'channels' => [
        'stderr' => [
            'driver' => 'monolog', 'level' => env('LOG_LEVEL', 'info'),
            'handler' => StreamHandler::class,
            'formatter' => JsonFormatter::class,
            'with' => ['stream' => 'php://stderr'],
        ],
        'null' => ['driver' => 'monolog', 'handler' => NullHandler::class],
    ],
];
