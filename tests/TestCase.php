<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        if ($app['config']->get('database.connections.mysql.database') !== 'content_api_test') {
            throw new RuntimeException('Tests must use the dedicated content_api_test database.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    /** @return array{product_name: string, features: list<string>, language: string, tone: string} */
    protected function payload(): array
    {
        return ['product_name' => 'Travel mug', 'features' => ['Steel', '350 ml'], 'language' => 'en', 'tone' => 'neutral'];
    }
}
