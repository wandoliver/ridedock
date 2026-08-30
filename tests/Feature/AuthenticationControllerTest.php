<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_registers_and_is_signed_in(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Ada Rider',
            'email' => 'ada@example.com',
            'password' => 'SecureRide123',
            'password_confirmation' => 'SecureRide123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
        $this->assertTrue(Hash::check('SecureRide123', User::firstOrFail()->password));
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
        $this->actingAs($user)->get(route('register'))->assertRedirect(route('dashboard'));
    }
}
