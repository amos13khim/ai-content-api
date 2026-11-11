<?php

declare(strict_types=1);

namespace App\Providers;

use App\AI\FakeTextGenerator;
use App\AI\OpenAITextGenerator;
use App\Contracts\TextGenerator;
use App\Models\Generation;
use App\Policies\GenerationPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TextGenerator::class, fn (): TextGenerator => match (config('ai.driver')) {
            'fake' => new FakeTextGenerator,
            'openai' => new OpenAITextGenerator($this->app->make(Factory::class), (string) config('ai.openai.key'), (string) config('ai.openai.model')),
            default => throw new InvalidArgumentException('Unsupported AI_DRIVER.'),
        });
    }

    public function boot(): void
    {
        Model::preventLazyLoading();
        Gate::policy(Generation::class, GenerationPolicy::class);
        RateLimiter::for('api-read', fn (Request $request): Limit => Limit::perMinute(120)->by('read:'.$request->user()?->getAuthIdentifier()));
        RateLimiter::for('api-write', fn (Request $request): Limit => Limit::perMinute(10)->by('write:'.$request->user()?->getAuthIdentifier()));
    }
}
