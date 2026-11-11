<?php

declare(strict_types=1);

namespace Tests\Support;

trait ProcessEnvironment
{
    /** @return array<string, string> */
    private function processEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => 'content_api_test', 'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'REDIS_CLIENT' => 'predis', 'REDIS_HOST' => (string) config('database.redis.default.host'),
            'REDIS_PORT' => (string) config('database.redis.default.port'),
            'REDIS_PASSWORD' => (string) (config('database.redis.default.password') ?? 'null'),
            'REDIS_DB' => (string) config('database.redis.default.database'),
            'REDIS_CACHE_DB' => (string) config('database.redis.cache.database'),
            'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'redis', 'AI_DRIVER' => 'fake', 'LOG_CHANNEL' => 'null',
        ];
    }
}
