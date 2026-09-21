<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_redirects_to_the_landing_page(): void
    {
        // Login lives on the landing page (LoginPane), so /login is only a
        // redirect kept for the named route and any bookmarked links.
        $response = $this->get('/login');

        $response->assertRedirect('/');
    }

    public function test_a_manager_can_authenticate_with_their_username(): void
    {
        $user = User::factory()->create([
            'role' => 'manager',
            'username' => 'budi',
        ]);

        $response = $this->post('/login', [
            'username' => 'budi',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_the_email_address_is_not_accepted_as_a_username(): void
    {
        $user = User::factory()->create([
            'role' => 'manager',
            'username' => 'budi',
            'email' => 'budi@example.com',
        ]);

        $this->post('/login', [
            'username' => 'budi@example.com',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        User::factory()->create(['role' => 'manager', 'username' => 'budi']);

        $this->post('/login', [
            'username' => 'budi',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_a_username_is_required(): void
    {
        $this->post('/login', ['password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_a_barista_cannot_sign_in(): void
    {
        User::factory()->create(['role' => 'barista', 'username' => 'siti']);

        $this->post('/login', ['username' => 'siti', 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_an_account_with_no_role_is_told_so_rather_than_failing_silently(): void
    {
        // Accounts carried over from another application start with a null
        // role; that must not read as a wrong password.
        User::factory()->create(['role' => null, 'username' => 'lama']);

        $response = $this->post('/login', ['username' => 'lama', 'password' => 'password']);

        $this->assertGuest();
        $response->assertSessionHasErrors('username');
        $this->assertStringContainsString(
            'no role set',
            session('errors')->first('username'),
        );
    }

    public function test_a_rejected_role_message_survives_to_the_landing_page(): void
    {
        // Regression: this redirected via route('login'), which is itself a
        // redirect to '/'. The extra hop consumed the flashed errors, so the
        // rejection reached the user as a silent failure.
        User::factory()->create(['role' => 'barista', 'username' => 'siti']);

        $this->post('/login', ['username' => 'siti', 'password' => 'password'])
            ->assertRedirect('/');

        $this->followingRedirects()
            ->post('/login', ['username' => 'siti', 'password' => 'password'])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('LandingPage/Pages/LandingPage')
                ->where('errors.username', 'Access denied. This account\'s role is "barista"; only managers can sign in.'));
    }

    public function test_an_unreadable_password_hash_does_not_crash_the_login_form(): void
    {
        // Regression: the hasher throws on a hash it cannot read (a legacy
        // MD5 from another app, or one truncated by a narrow column), which
        // surfaced as a 500 on the login form instead of a message.
        $user = User::factory()->create(['role' => 'manager', 'username' => 'lama']);
        DB::table('users')->where('id', $user->id)->update(['password' => md5('rahasia')]);

        $response = $this->post('/login', ['username' => 'lama', 'password' => 'rahasia']);

        $this->assertGuest();
        $response->assertSessionHasErrors('username');
        $this->assertStringContainsString(
            'cannot read',
            session('errors')->first('username'),
        );
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create(['role' => 'manager']);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
