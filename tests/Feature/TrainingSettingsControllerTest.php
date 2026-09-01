<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TrainingSettingsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('settings.training'))->assertRedirect(route('login'));
    }

    public function test_user_sees_the_training_profile_and_ai_status(): void
    {
        config()->set('ai.providers.openai.api_key');
        config()->set('ai.providers.openai.model');
        $user = User::factory()->create();

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('settings.training'))
            ->assertOk()
            ->assertSee('Training &amp; KI', false)
            ->assertSee('Trainingsprofil')
            ->assertSee('FTP')
            ->assertSee('(optional)')
            ->assertSee('API-Zugang fehlt')
            ->assertSee('GPS-Koordinaten, FIT-Dateien, Providerdaten und Aktivitätsnotizen bleiben in RideDock.');
    }

    public function test_user_can_save_their_training_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('settings.training.update'), [
            'goal' => '160 Kilometer im Juni',
            'goal_date' => '2027-06-15',
            'experience_level' => 'intermediate',
            'weekly_hours' => 8.5,
            'available_days' => ['tuesday', 'thursday', 'sunday'],
            'ftp_watts' => 248,
            'max_heart_rate' => 187,
            'constraints' => 'Werktags maximal 75 Minuten.',
        ]);

        $response->assertRedirect(route('settings.training'))
            ->assertSessionHas('status', 'Trainingsprofil gespeichert.');

        $profile = $user->athleteProfile()->firstOrFail();
        $this->assertSame('160 Kilometer im Juni', $profile->goal);
        $this->assertSame(['tuesday', 'thursday', 'sunday'], $profile->available_days);
        $this->assertSame(248, $profile->ftp_watts);
    }

    public function test_invalid_profile_is_rejected_without_persisting_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('settings.training'))
            ->patch(route('settings.training.update'), [
                'weekly_hours' => 100,
                'available_days' => ['holiday'],
                'ftp_watts' => 900,
                'max_heart_rate' => 20,
            ])
            ->assertRedirect(route('settings.training'))
            ->assertSessionHasErrors(['weekly_hours', 'available_days.0', 'ftp_watts', 'max_heart_rate']);

        $this->assertDatabaseEmpty('athlete_profiles');
    }

    public function test_user_can_switch_their_profile_to_english(): void
    {
        config()->set('ai.providers.openai.api_key');
        config()->set('ai.providers.openai.model');
        $user = User::factory()->create(['locale' => 'de']);

        $this->actingAs($user)->patch(route('settings.training.update'), [
            'locale' => 'en',
            'available_days' => [],
        ])->assertRedirect(route('settings.training'))
            ->assertSessionHas('status', 'Training profile saved.');

        $this->assertSame('en', $user->refresh()->locale);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('settings.training'))
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Display language')
            ->assertSee('Training profile')
            ->assertSee('API access missing');
    }
}
