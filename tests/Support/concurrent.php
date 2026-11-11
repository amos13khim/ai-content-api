<?php

declare(strict_types=1);

use App\Contracts\TextGenerator;
use App\Data\GenerationData;
use App\Data\GenerationResult;
use App\Services\GenerationProcessor;
use App\Services\GenerationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Queue;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.mysql.database') !== 'content_api_test') {
    throw new RuntimeException('Concurrent tests require the isolated test database.');
}
[$script, $mode, $argument, $directory, $worker] = $argv;
touch($directory.'/ready-'.$worker);
$deadline = microtime(true) + 10;
while (! is_file($directory.'/start')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Concurrent test barrier timed out.');
    }
    usleep(10000);
}
if ($mode === 'create') {
    Queue::fake();
    $generation = $app->make(GenerationService::class)->create((int) $argument, 'concurrent-key', new GenerationData('Mug', ['Steel'], 'en', 'neutral'));
    echo json_encode(['id' => $generation->id, 'created' => $generation->wasRecentlyCreated], JSON_THROW_ON_ERROR);
} elseif ($mode === 'process') {
    $app->instance(TextGenerator::class, new class($directory) implements TextGenerator
    {
        public function __construct(private string $directory) {}

        public function name(): string
        {
            return 'fake';
        }

        public function model(): string
        {
            return 'demo-v1';
        }

        public function generate(GenerationData $data): GenerationResult
        {
            file_put_contents($this->directory.'/calls', "call\n", FILE_APPEND | LOCK_EX);
            usleep(250000);

            return new GenerationResult('One result', 'concurrent-response');
        }
    });
    $app->make(GenerationProcessor::class)->process($argument);
    echo json_encode(['done' => true], JSON_THROW_ON_ERROR);
} else {
    throw new RuntimeException('Unknown test mode.');
}
