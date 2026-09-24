<?php

namespace Tests\Feature;

use App\CityAi\CityAiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CityAiServiceTest extends TestCase
{
    private const DISTRICTS = ['12 мкр' => 105, '27 мкр' => 207];

    private const METRICS = ['traffic' => 'Загрузка дорог', 'heat' => 'Индекс жары', 'air' => 'Качество воздуха'];

    private function enableOpenAi(): void
    {
        config(['services.openai.key' => 'test-key', 'services.openai.model' => 'test-model', 'services.openai.timeout' => 8]);
    }

    private function fakeChat(array $content): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode($content, JSON_UNESCAPED_UNICODE)]]],
        ])]);
    }

    public function test_parse_uses_structured_output(): void
    {
        $this->enableOpenAi();
        $this->fakeChat([
            'district_name' => '27 мкр',
            'goals' => [['metric' => 'heat', 'direction' => 'decrease', 'weight' => 1]],
            'budget_tenge' => 50_000_000,
        ]);

        $intent = app(CityAiService::class)->parse('Жара в 27-м', self::DISTRICTS, self::METRICS, 100_000_000);

        $this->assertSame(207, $intent->districtId);
        $this->assertSame('heat', $intent->goals[0]->metric);
        $this->assertSame(50_000_000, $intent->budget);
        $this->assertFalse($intent->fromFallback);
        Http::assertSent(fn ($request) => $request['response_format']['type'] === 'json_schema'
            && $request['model'] === 'test-model'
            && $request->hasHeader('Authorization', 'Bearer test-key'));
    }

    public function test_untrusted_fields_are_sanitized(): void
    {
        $this->enableOpenAi();
        $this->fakeChat([
            'district_name' => '12 мкр',
            'goals' => [['metric' => 'traffic', 'direction' => 'decrease', 'weight' => 1]],
            'budget_tenge' => -5,
        ]);

        $intent = app(CityAiService::class)->parse('Пробки 12 мкр', self::DISTRICTS, self::METRICS, 100_000_000);

        $this->assertSame(100_000_000, $intent->budget);
    }

    public function test_unknown_district_from_model_falls_back_to_keywords(): void
    {
        $this->enableOpenAi();
        $this->fakeChat(['district_name' => 'unknown', 'goals' => [], 'budget_tenge' => null]);

        $intent = app(CityAiService::class)->parse('Пробки в 12 мкр', self::DISTRICTS, self::METRICS, 100_000_000);

        $this->assertSame(105, $intent->districtId);
        $this->assertTrue($intent->fromFallback);
    }

    public function test_api_error_falls_back(): void
    {
        $this->enableOpenAi();
        Http::fake(['api.openai.com/*' => Http::response('boom', 500)]);

        $intent = app(CityAiService::class)->parse('Пробки в 12 мкр', self::DISTRICTS, self::METRICS, 100_000_000);

        $this->assertTrue($intent->fromFallback);
    }

    public function test_disabled_openai_never_calls_http(): void
    {
        config(['services.openai.key' => null]);
        Http::fake();

        $intent = app(CityAiService::class)->parse('Пробки в 12 мкр', self::DISTRICTS, self::METRICS, 100_000_000);

        $this->assertTrue($intent->fromFallback);
        Http::assertNothingSent();
    }

    public function test_parse_is_cached_per_prompt(): void
    {
        $this->enableOpenAi();
        $this->fakeChat([
            'district_name' => '12 мкр',
            'goals' => [['metric' => 'traffic', 'direction' => 'decrease', 'weight' => 1]],
            'budget_tenge' => null,
        ]);

        app(CityAiService::class)->parse('Пробки в 12 мкр', self::DISTRICTS, self::METRICS, 100_000_000);
        app(CityAiService::class)->parse('Пробки в 12 мкр', self::DISTRICTS, self::METRICS, 100_000_000);

        Http::assertSentCount(1);
    }

    public function test_explain_falls_back_to_template(): void
    {
        config(['services.openai.key' => null]);
        $summary = ['scope' => 'district', 'scenarios' => [
            ['label' => 'A', 'name' => 'Маршрут Б + Умные светофоры', 'cost' => 52_000_000, 'deltas' => ['Загрузка дорог' => -17.0]],
        ]];

        $text = app(CityAiService::class)->explain($summary);

        $this->assertStringContainsString('A «Маршрут Б + Умные светофоры»', $text);
        $this->assertStringContainsString('Загрузка дорог −17', $text);
        $this->assertStringContainsString('52 млн ₸', $text);
    }

    public function test_fallback_tells_lettered_districts_apart(): void
    {
        config(['services.openai.key' => null]);
        $districts = ['12 мкр' => 105, '12А мкр' => 106];

        $plain = app(CityAiService::class)->parse('Уменьши пробки в 12 мкр', $districts, self::METRICS, 100_000_000);
        $lettered = app(CityAiService::class)->parse('Уменьши пробки в 12а мкр', $districts, self::METRICS, 100_000_000);

        $this->assertSame(105, $plain->districtId);
        $this->assertSame(106, $lettered->districtId);
    }

    public function test_fallback_accepts_latin_letters_and_hyphen(): void
    {
        config(['services.openai.key' => null]);
        $districts = ['12 мкр' => 105, '12А мкр' => 106];

        foreach (['Пробки в 12a мкр', 'Пробки в 12-а мкр'] as $prompt) {
            $this->assertSame(106, app(CityAiService::class)->parse($prompt, $districts, self::METRICS, 100_000_000)?->districtId, $prompt);
        }
    }

    public function test_openai_goal_without_district_leaves_district_open(): void
    {
        $this->enableOpenAi();
        $this->fakeChat(['district_name' => 'unknown', 'goals' => [['metric' => 'heat', 'direction' => 'decrease', 'weight' => 1]], 'budget_tenge' => null]);

        $intent = app(CityAiService::class)->parse('Меньше жары', self::DISTRICTS, self::METRICS, 100_000_000);

        $this->assertNull($intent->districtId);
        $this->assertSame('heat', $intent->goals[0]->metric);
    }
}
