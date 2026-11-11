<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TextGenerator;
use App\Exceptions\ProviderException;
use App\Repositories\GenerationRepository;
use Illuminate\Support\Facades\Log;

final readonly class GenerationProcessor
{
    public function __construct(private GenerationRepository $generations, private TextGenerator $generator, private GenerationDispatcher $dispatcher) {}

    public function process(string $id): void
    {
        $maxAttempts = (int) config('ai.max_attempts');
        $claim = $this->generations->claim($id, $maxAttempts);
        if ($claim === null) {
            return;
        }
        [$generation, $attempt] = $claim;
        $start = hrtime(true);
        try {
            if ($generation->provider !== $this->generator->name() || $generation->model !== $this->generator->model()) {
                throw new ProviderException('provider_configuration_changed');
            }
            $result = $this->generator->generate($generation->data());
        } catch (ProviderException $error) {
            $retryAt = $error->retryable && $attempt->number < $maxAttempts
                ? now()->addSeconds(max($error->retryAfter, 10 * (2 ** ($attempt->number - 1)))) : null;
            $saved = $this->generations->recordFailure($attempt, $error, $this->elapsed($start), $retryAt);
            if ($saved && $retryAt !== null) {
                $this->dispatcher->dispatch($id, $retryAt);
            }
            Log::notice('generation.provider_failed', ['generation_id' => $id, 'attempt' => $attempt->number, 'code' => $error->errorCode, 'retry_scheduled' => $saved && $retryAt !== null]);

            return;
        }
        $this->generations->complete($attempt, $result, $this->elapsed($start));
        Log::info('generation.completed', ['generation_id' => $id, 'attempt' => $attempt->number]);
    }

    private function elapsed(int $start): int
    {
        return (int) ((hrtime(true) - $start) / 1_000_000);
    }
}
