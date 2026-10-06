<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthenticationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_registration_is_absent_for_guests_and_members(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('register'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('register.store'));
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['email' => 'ada@example.com', 'password' => 'SecureRide123'])->assertNotFound();
        $this->getJson('/api/register')->assertNotFound();
        $this->postJson('/api/v1/register', ['email' => 'ada@example.com'])->assertNotFound();
        $this->assertDatabaseEmpty('users');
        $user = User::factory()->create();
        $this->actingAs($user)->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_guest_pages_use_english_by_default(): void
    {
        $this->withoutVite()
            ->get(route('login'))
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Access is by invitation.')
            ->assertDontSee('/register');
    }

    public function test_user_signs_in_and_out_with_a_regenerated_session(): void
    {
        $user = User::factory()->create(['password' => 'SecureRide123']);
        $originalSessionId = session()->getId();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'SecureRide123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($originalSessionId, session()->getId());

        $this->post(route('logout'))->assertRedirect(route('dashboard'));
        $this->assertGuest();
    }

    public function test_invalid_credentials_do_not_authenticate_the_user(): void
    {
        $user = User::factory()->create(['password' => 'SecureRide123']);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_cannot_open_guest_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('login'))->assertRedirect(route('dashboard'));
    }
}
