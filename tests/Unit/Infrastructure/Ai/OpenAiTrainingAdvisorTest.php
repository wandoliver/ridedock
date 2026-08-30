<?php

namespace Tests\Unit\Infrastructure\Ai;

use App\Infrastructure\Ai\OpenAiTrainingAdvisor;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiTrainingAdvisorTest extends TestCase
{
    public function test_it_requests_non_stored_structured_output(): void
    {
        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response([
                'id' => 'response-123',
                'model' => 'test-model-2026-08-30',
                'output' => [[
                    'type' => 'reasoning',
                    'summary' => [],
                ], [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'summary' => 'Solide Fahrt.',
                            'training_effect' => 'Aerober Reiz.',
                            'observations' => [],
                            'next_session' => [
                                'title' => 'Locker rollen',
                                'duration_minutes' => 45,
                                'intensity' => 'Leicht',
                                'details' => 'Ruhig fahren.',
                                'rationale' => 'Erholung.',
                            ],
                            'data_gaps' => [],
                            'cautions' => [],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            ]),
        ]);
        $advisor = new OpenAiTrainingAdvisor(
            http: app(Factory::class),
            apiKey: 'secret-key',
            model: 'test-model',
            baseUrl: 'https://api.openai.test/v1',
            timeoutSeconds: 10,
        );

        $advice = $advisor->analyzeActivity([
            'schema_version' => 1,
            'privacy' => ['gps_coordinates_included' => false],
        ]);

        $this->assertSame('Solide Fahrt.', $advice->output['summary']);
        $this->assertSame('test-model-2026-08-30', $advice->model);
        $this->assertSame(100, $advice->inputTokens);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.openai.test/v1/responses'
                && $payload['store'] === false
                && $payload['text']['format']['type'] === 'json_schema'
                && $payload['text']['format']['strict'] === true
                && str_contains($payload['instructions'], 'optionale Zusatzsignale')
                && str_contains($payload['instructions'], 'Nenne ihr Fehlen niemals als Datenlücke')
                && str_contains($payload['input'][0]['content'][0]['text'], 'gps_coordinates_included');
        });
    }

    public function test_it_is_disabled_without_key_or_model(): void
    {
        $advisor = new OpenAiTrainingAdvisor(
            http: app(Factory::class),
            apiKey: '',
            model: '',
            baseUrl: 'https://api.openai.test/v1',
            timeoutSeconds: 10,
        );

        $this->assertFalse($advisor->isConfigured());
    }

    public function test_it_requests_a_strict_seven_day_plan_without_requiring_sensor_data(): void
    {
        $days = array_map(
            fn (int $offset): array => [
                'date' => '2026-09-'.str_pad((string) ($offset + 1), 2, '0', STR_PAD_LEFT),
                'title' => $offset % 2 === 0 ? 'Lockere Ausfahrt' : 'Ruhetag',
                'type' => $offset % 2 === 0 ? 'ride' : 'rest',
                'duration_minutes' => $offset % 2 === 0 ? 60 : null,
                'intensity' => $offset % 2 === 0 ? 'Leicht, RPE 3' : 'Keine',
                'details' => 'Nach Tagesform gestalten.',
                'rationale' => 'Belastung und Erholung ausgleichen.',
            ],
            range(0, 6),
        );
        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response([
                'id' => 'response-weekly-123',
                'model' => 'test-model-2026-08-30',
                'output' => [[
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'overview' => 'Eine ausgewogene Woche.',
                            'weekly_goal' => 'Ausdauer festigen',
                            'total_duration_minutes' => 240,
                            'days' => $days,
                            'data_gaps' => [],
                            'cautions' => [],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
                'usage' => ['input_tokens' => 200, 'output_tokens' => 300],
            ]),
        ]);
        $advisor = new OpenAiTrainingAdvisor(
            http: app(Factory::class),
            apiKey: 'secret-key',
            model: 'test-model',
            baseUrl: 'https://api.openai.test/v1',
            timeoutSeconds: 10,
        );

        $advice = $advisor->generateWeeklyPlan([
            'schema_version' => 1,
            'plan_start' => '2026-09-01',
            'athlete_profile' => [
                'weekly_hours' => null,
                'optional_signals' => ['ftp_watts' => null],
            ],
            'plan_days' => array_map(
                fn (array $day): array => [
                    'date' => $day['date'],
                    'available_for_training' => $day['type'] === 'ride',
                ],
                $days,
            ),
        ]);

        $this->assertSame('Ausdauer festigen', $advice->output['weekly_goal']);
        $this->assertCount(7, $advice->output['days']);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $payload['text']['format']['name'] === 'seven_day_training_plan'
                && $payload['text']['format']['schema']['properties']['days']['minItems'] === 7
                && $payload['text']['format']['schema']['properties']['days']['maxItems'] === 7
                && $payload['text']['format']['schema']['properties']['weekly_goal']['maxLength'] === 100
                && str_contains($payload['instructions'], 'exakt sieben')
                && str_contains($payload['instructions'], 'höchstens acht Wörtern')
                && str_contains($payload['instructions'], 'Andernfalls leite einen konservativen Umfang')
                && str_contains($payload['instructions'], 'Nenne ihr Fehlen niemals als Datenlücke');
        });
    }
}
