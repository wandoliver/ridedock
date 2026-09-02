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
                && str_contains($payload['instructions'], 'ausgewiesene Durchschnittstempo')
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

    public function test_it_extends_a_short_php_execution_limit_to_cover_the_provider_timeout(): void
    {
        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response([
                'id' => 'response-timeout-test',
                'model' => 'test-model',
                'output' => [[
                    'type' => 'message',
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
            ]),
        ]);
        $advisor = new OpenAiTrainingAdvisor(
            http: app(Factory::class),
            apiKey: 'secret-key',
            model: 'test-model',
            baseUrl: 'https://api.openai.test/v1',
            timeoutSeconds: 60,
        );
        $originalLimit = (int) ini_get('max_execution_time');

        try {
            set_time_limit(30);
            $advisor->analyzeActivity([]);

            $this->assertSame(70, (int) ini_get('max_execution_time'));
        } finally {
            set_time_limit($originalLimit);
        }
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

    public function test_it_requests_and_validates_a_goal_roadmap(): void
    {
        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response([
                'id' => 'response-goal-123',
                'model' => 'test-model',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'goal_summary' => 'Sicher zur langen Tour',
                            'overview' => 'Ein schrittweiser Aufbau.',
                            'plan_start' => '2026-09-01',
                            'plan_end' => '2026-12-31',
                            'phases' => [[
                                'name' => 'Grundlage',
                                'start_date' => '2026-09-01',
                                'end_date' => '2026-10-31',
                                'focus' => 'Ausdauer entwickeln.',
                                'success_marker' => 'Lange Fahrten bleiben locker.',
                            ], [
                                'name' => 'Spezifischer Aufbau',
                                'start_date' => '2026-11-01',
                                'end_date' => '2026-12-31',
                                'focus' => 'Zielspezifische Belastung.',
                                'success_marker' => 'Zielumfang wird gut vertragen.',
                            ]],
                            'principles' => ['Erholung priorisieren.'],
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

        $advice = $advisor->generateGoalPlan([
            'plan_start' => '2026-09-01',
            'plan_end' => '2026-12-31',
        ]);

        $this->assertCount(2, $advice->output['phases']);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $payload['text']['format']['name'] === 'goal_training_plan'
                && $payload['text']['format']['schema']['properties']['phases']['maxItems'] === 12
                && str_contains($payload['instructions'], 'gesamten Zeitraum lückenlos');
        });
    }

    public function test_it_requests_and_validates_a_four_week_training_block(): void
    {
        $blockWeeks = array_map(
            fn (int $index): array => [
                'week' => $index + 1,
                'start' => '2026-09-'.str_pad((string) ($index * 7 + 1), 2, '0', STR_PAD_LEFT),
                'end' => '2026-09-'.str_pad((string) ($index * 7 + 7), 2, '0', STR_PAD_LEFT),
            ],
            range(0, 3),
        );
        $outputWeeks = array_map(
            fn (array $week): array => [
                'week' => $week['week'],
                'week_start' => $week['start'],
                'week_end' => $week['end'],
                'focus' => 'Ausdauer',
                'load_level' => $week['week'] === 4 ? 'recovery' : 'build',
                'target_duration_minutes' => 360,
                'key_sessions' => ['Lange Ausfahrt'],
            ],
            $blockWeeks,
        );
        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response([
                'id' => 'response-block-123',
                'model' => 'test-model',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'overview' => 'Drei Aufbauwochen und eine Entlastung.',
                            'block_goal' => 'Grundlage ausbauen',
                            'phase_name' => 'Grundlage',
                            'block_start' => '2026-09-01',
                            'block_end' => '2026-09-28',
                            'weeks' => $outputWeeks,
                            'adaptation_rules' => ['Bei Müdigkeit reduzieren.'],
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

        $advice = $advisor->generateTrainingBlock([
            'block_start' => '2026-09-01',
            'block_end' => '2026-09-28',
            'block_weeks' => $blockWeeks,
            'athlete_profile' => ['weekly_hours' => 7],
        ]);

        $this->assertCount(4, $advice->output['weeks']);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $payload['text']['format']['name'] === 'four_week_training_block'
                && $payload['text']['format']['schema']['properties']['weeks']['minItems'] === 4
                && str_contains($payload['instructions'], 'Noch keine einzelnen Kalendertage');
        });
    }
}
