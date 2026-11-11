<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\AI\FakeTextGenerator;
use App\Data\GenerationData;
use PHPUnit\Framework\TestCase;

final class GenerationDataTest extends TestCase
{
    public function test_fingerprint_is_stable_and_changes_with_meaningful_input(): void
    {
        $data = new GenerationData('Кружка', ['Сталь', '350 мл'], 'ru', 'neutral');
        $same = new GenerationData('Кружка', ['Сталь', '350 мл'], 'ru', 'neutral');
        $different = new GenerationData('Кружка', ['Сталь', '350 мл'], 'ru', 'friendly');

        $this->assertSame($data->fingerprint(), $same->fingerprint());
        $this->assertNotSame($data->fingerprint(), $different->fingerprint());
    }

    public function test_demo_provider_is_deterministic_and_does_not_report_fake_billable_usage(): void
    {
        $provider = new FakeTextGenerator;
        $data = new GenerationData('Кружка', ['Сталь'], 'ru', 'neutral');
        $first = $provider->generate($data);

        $this->assertEquals($first, $provider->generate($data));
        $this->assertStringContainsString('Кружка', $first->text);
        $this->assertSame(0, $first->inputTokens);
        $this->assertSame(0, $first->outputTokens);
    }
}
