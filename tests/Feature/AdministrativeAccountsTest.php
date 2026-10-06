<?php

namespace Tests\Feature;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Enums\UserStatus;
use App\Domain\Accounts\Services\ManageInvitations;
use App\Mail\UserInvitationMail;
use App\Models\Activity;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class AdministrativeAccountsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_routes_reject_guests_and_members_on_every_action(): void
    {
        $pending = User::factory()->create(['status' => UserStatus::Pending]);
        $routes = [
            ['get', '/admin/users'], ['post', '/admin/users'],
            ['post', '/admin/users/'.$pending->id.'/invitation'],
            ['delete', '/admin/users/'.$pending->id.'/invitation'],
        ];
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }
        $member = User::factory()->create();
        $this->actingAs($member);
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
        $this->get('/settings/training')->assertOk()->assertDontSee('/admin/users');
        $this->assertDatabaseEmpty('user_invitations');
    }

    public function test_admin_creates_pending_member_with_one_time_link_without_persisting_token(): void
    {
        [$admin, $invitation, $token] = $this->createManual();
        $user = $invitation->user;
        $this->assertSame(UserRole::Member, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertSame('ada@example.com', $user->email);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertTrue($invitation->expires_at->equalTo($invitation->created_at->addDays(7)));
        $this->assertNull($user->email_verified_at);
        $this->assertStringNotContainsString($token, json_encode(session()->all()));
        $this->assertStringNotContainsString($token, json_encode(DB::table('account_audit_events')->get()));
        $this->assertStringNotContainsString($token, json_encode($invitation));
        $this->assertDatabaseHas('account_audit_events', ['event' => 'user.created', 'actor_user_id' => $admin->id, 'subject_user_id' => $user->id]);
        $this->get('/admin/users')->assertOk()->assertDontSee($token)->assertSee('Benutzerverwaltung')->assertSee('/admin/users');
    }

    public function test_link_get_does_not_consume_and_post_activates_only_bound_account_once(): void
    {
        [, $invitation, $token] = $this->createManual();
        $this->beGuest();
        $this->get('/invitations/'.$token)->assertOk()->assertSee('Konto aktivieren')
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertNull($invitation->refresh()->accepted_at);
        $this->post('/invitations/'.$token, [
            'password' => 'SyntheticPass123', 'password_confirmation' => 'SyntheticPass123',
            'role' => 'admin', 'status' => 'pending', 'email' => 'other@example.com', 'name' => 'Other',
        ])->assertRedirect(route('login'));
        $user = $invitation->user->refresh();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame(UserRole::Member, $user->role);
        $this->assertSame('ada@example.com', $user->email);
        $this->assertTrue(Hash::check('SyntheticPass123', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($invitation->refresh()->accepted_at);
        $this->assertGuest();
        $this->get('/invitations/'.$token)->assertNotFound();
        $this->post('/invitations/'.$token, $this->passwords())->assertNotFound();
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('account_audit_events', ['event' => 'invitation.accepted', 'subject_user_id' => $user->id]);
        $this->post('/login', ['email' => $user->email, 'password' => 'SyntheticPass123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_pending_accounts_cannot_login_use_browser_session_or_api_token(): void
    {
        $pending = User::factory()->create(['status' => UserStatus::Pending, 'password' => 'SyntheticPass123']);
        $this->post('/login', ['email' => $pending->email, 'password' => 'SyntheticPass123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($pending)->get('/')->assertForbidden();
        $this->assertGuest();
        $token = $pending->createToken('synthetic', ['activities:read'])->plainTextToken;
        $this->getJson('/api/v1/activities', ['Authorization' => 'Bearer '.$token])->assertForbidden();
        $this->assertDatabaseCount('user_invitations', 0);
    }

    public function test_invalid_expired_and_revoked_tokens_cannot_activate_accounts(): void
    {
        [$admin, $invitation, $token] = $this->createManual();
        $this->beGuest();
        foreach (['guess', str_repeat('a', 64)] as $guess) {
            $this->get('/invitations/'.$guess)->assertNotFound()->assertHeader('Referrer-Policy', 'no-referrer');
            $this->post('/invitations/'.$guess, $this->passwords())->assertNotFound();
        }
        $this->travel(7)->days();
        $this->get('/invitations/'.$token)->assertNotFound();
        $this->post('/invitations/'.$token, $this->passwords())->assertNotFound();
        $this->travelBack();
        $this->actingAs($admin)->delete('/admin/users/'.$invitation->user_id.'/invitation')->assertRedirect('/admin/users');
        $this->beGuest();
        $this->post('/invitations/'.$token, $this->passwords())->assertNotFound();
        $this->assertSame(UserStatus::Pending, $invitation->user->refresh()->status);
        $this->assertDatabaseHas('account_audit_events', ['event' => 'invitation.revoked']);
    }

    public function test_renewal_invalidates_old_link_and_active_account_cannot_be_reinvited(): void
    {
        [$admin, $invitation, $token] = $this->createManual();
        $response = $this->post('/admin/users/'.$invitation->user_id.'/invitation', ['delivery' => 'manual'])->assertOk();
        $newToken = basename($response->viewData('activationUrl'));
        $this->assertNotSame($token, $newToken);
        $this->assertNotNull($invitation->refresh()->revoked_at);
        $this->beGuest();
        $this->get('/invitations/'.$token)->assertNotFound();
        $this->post('/invitations/'.$newToken, $this->passwords())->assertRedirect('/login');
        $this->actingAs($admin)->post('/admin/users/'.$invitation->user_id.'/invitation', ['delivery' => 'manual'])->assertStatus(409);
        $this->delete('/admin/users/'.$invitation->user_id.'/invitation')->assertStatus(409);
    }

    public function test_password_rules_and_changed_email_prevent_activation(): void
    {
        [, $invitation, $token] = $this->createManual();
        $this->beGuest();
        $this->post('/invitations/'.$token, ['password' => 'short', 'password_confirmation' => 'different'])
            ->assertSessionHasErrors('password');
        $this->assertSame(UserStatus::Pending, $invitation->user->refresh()->status);
        $this->assertNull($invitation->refresh()->accepted_at);
        $invitation->user->update(['email' => 'changed@example.com']);
        $this->post('/invitations/'.$token, $this->passwords())->assertNotFound();
    }

    public function test_admin_creation_validates_duplicates_and_ignores_role_injection(): void
    {
        $this->createManual();
        $this->post('/admin/users', $this->attributes())->assertSessionHasErrors('email');
        $this->post('/admin/users', array_replace($this->attributes(), ['locale' => 'invalid']))->assertSessionHasErrors('locale');
        $this->post('/admin/users', array_replace($this->attributes(), ['email' => 'other@example.com', 'delivery' => 'invalid']))->assertSessionHasErrors('delivery');
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('user_invitations', 1);
    }

    public function test_email_invitation_is_localized_synchronous_and_uses_trusted_app_url(): void
    {
        Mail::fake();
        Queue::fake();
        config()->set('app.url', 'https://ridedock.example');
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/users', $this->attributes('email'))->assertRedirect('/admin/users')->assertSessionHas('status');
        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail): bool {
            return $mail->hasTo('ada@example.com') && $mail->locale === 'de'
                && str_starts_with($mail->activationUrl, 'https://ridedock.example/invitations/')
                && str_contains($mail->render(), 'Deine Einladung');
        });
        Queue::assertNothingPushed();
        $invitation = UserInvitation::query()->firstOrFail();
        $this->assertNotNull($invitation->emailed_at);
        $token = null;
        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use (&$token): bool {
            $token = basename($mail->activationUrl);
            return true;
        });
        $this->beGuest();
        $this->post('/invitations/'.$token, $this->passwords())->assertRedirect('/login');
        $this->assertNotNull($invitation->user->refresh()->email_verified_at);
    }

    public function test_log_mailer_is_rejected_before_creating_an_account(): void
    {
        config()->set('mail.default', 'log');
        Mail::fake();
        $this->actingAs($this->admin())->post('/admin/users', $this->attributes('email'))->assertSessionHasErrors('delivery');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseEmpty('user_invitations');
        Mail::assertNothingSent();
    }

    public function test_mail_failure_keeps_pending_account_without_exposing_transport_details(): void
    {
        Log::spy();
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SYNTHETIC_PRIVATE_MESSAGE'));
        $this->actingAs($this->admin())->post('/admin/users', $this->attributes('email'))->assertRedirect('/admin/users')->assertSessionHas('error');
        $this->assertSame(UserStatus::Pending, UserInvitation::query()->firstOrFail()->user->status);
        $this->assertStringNotContainsString('SYNTHETIC_PRIVATE_MESSAGE', json_encode(session()->all()));
        $this->assertDatabaseHas('account_audit_events', ['event' => 'invitation.email_failed']);
        Log::shouldNotHaveReceived('error');
    }

    public function test_admin_role_does_not_grant_access_to_other_users_rides(): void
    {
        $activity = Activity::factory()->create();
        $this->actingAs($this->admin())->get('/activities/'.$activity->id)->assertNotFound();
        $this->patch('/activities/'.$activity->id.'/feedback', ['rpe' => 5])->assertNotFound();
    }

    public function test_role_and_status_are_not_mass_assignable(): void
    {
        $user = new User(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'SyntheticPass123', 'role' => 'admin', 'status' => 'pending']);
        $this->assertSame(UserRole::Member, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
    }

    public function test_cli_promotes_only_selected_active_user_and_never_changes_credentials(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $hash = $user->password;
        $this->artisan('app:create-admin', ['email' => $user->email])->assertSuccessful();
        $this->assertSame(UserRole::Admin, $user->refresh()->role);
        $this->assertSame($hash, $user->password);
        $this->assertSame(UserRole::Member, $other->refresh()->role);
        $this->assertDatabaseHas('account_audit_events', ['event' => 'admin.promoted', 'subject_user_id' => $user->id]);
        $pending = User::factory()->create(['status' => UserStatus::Pending]);
        $this->artisan('app:create-admin', ['email' => $pending->email])->assertFailed();
        $this->artisan('app:create-admin', ['email' => 'invalid'])->assertFailed();
    }

    public function test_cli_creates_first_admin_with_interactive_password_and_no_default_credentials(): void
    {
        $this->artisan('app:create-admin', ['email' => 'admin@example.com', '--name' => 'Instance Admin', '--locale' => 'de'])
            ->expectsQuestion('Password (at least 12 characters)', 'SyntheticPass123')
            ->expectsQuestion('Confirm password', 'SyntheticPass123')->assertSuccessful();
        $user = User::query()->firstOrFail();
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertTrue(Hash::check('SyntheticPass123', $user->password));
        $this->assertDatabaseHas('account_audit_events', ['event' => 'admin.created', 'subject_user_id' => $user->id]);
    }

    public function test_token_command_requires_existing_active_account(): void
    {
        $this->artisan('app:create-owner-token', ['email' => 'missing@example.com'])->assertFailed();
        $this->assertDatabaseEmpty('users');
        $pending = User::factory()->create(['status' => UserStatus::Pending]);
        $this->artisan('app:create-owner-token', ['email' => $pending->email])->assertFailed();
        $this->assertDatabaseEmpty('personal_access_tokens');
        $active = User::factory()->create();
        $this->artisan('app:create-owner-token', ['email' => $active->email])->assertSuccessful();
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $active->id]);
        $this->assertSame(UserRole::Member, $active->refresh()->role);
    }

    public function test_invitation_routes_limit_guesses(): void
    {
        $this->beGuest();
        for ($i = 0; $i < 20; $i++) {
            $this->get('/invitations/'.str_repeat('b', 64))->assertNotFound();
        }
        $this->get('/invitations/'.str_repeat('b', 64))->assertStatus(429)->assertHeader('Referrer-Policy', 'no-referrer');
    }

    /** @return array{User, UserInvitation, string} */
    private function createManual(): array
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin)->post('/admin/users', $this->attributes() + ['role' => 'admin', 'status' => 'active'])->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $invitation = UserInvitation::query()->latest('id')->firstOrFail();

        return [$admin, $invitation, basename($response->viewData('activationUrl'))];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    /** @return array<string, string> */
    private function attributes(string $delivery = 'manual'): array
    {
        return ['name' => 'Ada Rider', 'email' => ' ADA@example.com ', 'locale' => 'de', 'delivery' => $delivery];
    }

    /** @return array<string, string> */
    private function passwords(): array
    {
        return ['password' => 'SyntheticPass123', 'password_confirmation' => 'SyntheticPass123'];
    }

    private function beGuest(): void
    {
        auth()->forgetGuards();
        $this->app['session']->flush();
    }
}
