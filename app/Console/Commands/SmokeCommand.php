<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class SmokeCommand extends Command
{
    protected $signature = 'demo:smoke {--url=http://app:8080 : Running local API base URL}';

    protected $description = 'Exercise authentication, HTTP API, MySQL and the real Redis worker end to end';

    public function handle(): int
    {
        if (! app()->environment('local') || config('ai.driver') !== 'fake') {
            $this->error('The smoke demo requires APP_ENV=local and AI_DRIVER=fake.');

            return self::FAILURE;
        }
        $user = User::query()->firstOrCreate(['email' => 'smoke@example.test'], ['name' => 'Smoke demo', 'password' => Str::random(64)]);
        $token = $user->createToken('smoke', ['generations:read', 'generations:write'], now()->addMinutes(5));
        try {
            $client = Http::baseUrl(rtrim((string) $this->option('url'), '/').'/api/v1')->acceptJson()->withToken($token->plainTextToken)->timeout(5);
            $body = ['product_name' => 'Travel mug', 'features' => ['Steel', '350 ml'], 'language' => 'en', 'tone' => 'neutral'];
            $key = (string) Str::uuid();
            $created = $client->withHeader('Idempotency-Key', $key)->post('/generations', $body)->throw();
            if ($created->status() !== 202) {
                throw new RuntimeException('Creation did not return 202.');
            }
            $id = $created->json('data.id');
            $replayed = $client->post('/generations', $body)->throw();
            if ($replayed->status() !== 200 || $replayed->json('data.id') !== $id) {
                throw new RuntimeException('Idempotency replay failed.');
            }
            $conflict = $client->post('/generations', [...$body, 'tone' => 'friendly']);
            if ($conflict->status() !== 409) {
                throw new RuntimeException('Conflicting input was not rejected.');
            }
            for ($i = 0; $i < 20; $i++) {
                $result = $client->get('/generations/'.$id)->throw();
                if ($result->json('data.status') === 'succeeded') {
                    $client->get('/usage')->throw();
                    $this->info('PASS: authenticated API, idempotency, conflict, MySQL and Redis worker.');
                    $this->line('Generation: '.$id);
                    $this->line((string) $result->json('data.result'));

                    return self::SUCCESS;
                }
                if ($result->json('data.status') === 'failed') {
                    throw new RuntimeException('Demo generation failed.');
                }
                sleep(1);
            }
            throw new RuntimeException('Worker did not complete the generation within 20 seconds.');
        } finally {
            $token->accessToken->delete();
        }
    }
}
