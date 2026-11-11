<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\AI\OpenAITextGenerator;
use App\Data\GenerationData;
use App\Exceptions\ProviderException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class OpenAITextGeneratorTest extends TestCase
{
    public function test_reads_text_after_non_message_output_and_sends_a_bounded_non_stored_request(): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'id' => 'resp_test', 'status' => 'completed',
            'output' => [
                ['type' => 'reasoning'],
                ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'A useful mug.']]],
            ],
            'usage' => ['input_tokens' => 42, 'output_tokens' => 8],
        ], 200, ['x-request-id' => 'req_test'])]);

        $result = $this->provider()->generate($this->data());

        $this->assertSame('A useful mug.', $result->text);
        $this->assertSame(42, $result->inputTokens);
        $this->assertSame('req_test', $result->requestId);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses'
            && $request['store'] === false && $request['max_output_tokens'] === 800
            && json_decode($request['input'], true)['product_name'] === 'Travel mug');
        Http::assertSentCount(1);
    }

    public function test_rate_limit_is_retryable_and_respects_retry_after(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 'rate_limit_exceeded']], 429, ['Retry-After' => '123'])]);
        try {
            $this->provider()->generate($this->data());
            $this->fail('Expected a classified provider error.');
        } catch (ProviderException $error) {
            $this->assertSame('rate_limited', $error->errorCode);
            $this->assertTrue($error->retryable);
            $this->assertSame(123, $error->retryAfter);
        }
        Http::assertSentCount(1);
    }

    #[DataProvider('terminalErrors')]
    public function test_terminal_errors_are_sanitized_and_not_retried(int $status, string $providerCode, string $expectedCode): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => $providerCode, 'message' => 'private prompt and secret']], $status)]);
        try {
            $this->provider()->generate($this->data());
            $this->fail('Expected an error.');
        } catch (ProviderException $error) {
            $this->assertSame($expectedCode, $error->errorCode);
            $this->assertFalse($error->retryable);
            $this->assertStringNotContainsString('private', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
        Http::assertSentCount(1);
    }

    public static function terminalErrors(): array
    {
        return [
            'quota' => [429, 'insufficient_quota', 'quota_exceeded'],
            'credentials' => [401, 'invalid_api_key', 'provider_rejected'],
            'bad input' => [400, 'invalid_request', 'provider_rejected'],
            'ambiguous server failure' => [503, 'server_error', 'outcome_unknown'],
        ];
    }

    public function test_connection_failure_has_unknown_outcome_and_is_not_retried(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        try {
            $this->provider()->generate($this->data());
            $this->fail('Expected an error.');
        } catch (ProviderException $error) {
            $this->assertSame('outcome_unknown', $error->errorCode);
            $this->assertFalse($error->retryable);
        }
    }

    public function test_incomplete_result_preserves_reported_usage(): void
    {
        Http::fake(['*' => Http::response(['status' => 'incomplete', 'usage' => ['input_tokens' => 10, 'output_tokens' => 800]])]);
        try {
            $this->provider()->generate($this->data());
            $this->fail('Expected an error.');
        } catch (ProviderException $error) {
            $this->assertSame('incomplete_response', $error->errorCode);
            $this->assertSame(800, $error->outputTokens);
        }
    }

    public function test_empty_success_response_is_not_accepted(): void
    {
        Http::fake(['*' => Http::response(['id' => 'resp_test', 'status' => 'completed', 'output' => []])]);
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('invalid_provider_response');
        $this->provider()->generate($this->data());
    }

    public function test_missing_configuration_does_not_make_a_request(): void
    {
        try {
            (new OpenAITextGenerator(Http::getFacadeRoot(), '', ''))->generate($this->data());
            $this->fail('Expected a configuration error.');
        } catch (ProviderException $error) {
            $this->assertSame('provider_not_configured', $error->errorCode);
        }
        Http::assertNothingSent();
    }

    private function provider(): OpenAITextGenerator
    {
        return new OpenAITextGenerator(Http::getFacadeRoot(), 'test-key', 'test-model');
    }

    private function data(): GenerationData
    {
        return new GenerationData('Travel mug', ['Steel'], 'en', 'neutral');
    }
}
