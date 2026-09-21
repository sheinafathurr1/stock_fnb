<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserRoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_mixed_case_manager_role_already_signs_in(): void
    {
        // Roles carried over from another app are often capitalised; the role
        // check is case-insensitive, so those need no migration step at all.
        $user = User::factory()->create(['role' => 'Manager', 'username' => 'budi']);

        $this->post('/login', ['username' => 'budi', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_grant_manager_promotes_named_accounts(): void
    {
        User::factory()->create(['role' => 'barista', 'username' => 'budi']);
        User::factory()->create(['role' => 'barista', 'username' => 'sari']);

        $this->artisan('users:grant-manager budi sari')
            ->expectsConfirmation('Apply this change?', 'yes')
            ->assertSuccessful();

        $this->assertSame('manager', User::where('username', 'budi')->value('role'));
        $this->assertSame('manager', User::where('username', 'sari')->value('role'));
    }

    public function test_grant_manager_can_promote_a_whole_role_at_once(): void
    {
        User::factory()->count(3)->create(['role' => 'Supervisor']);
        User::factory()->create(['role' => 'barista', 'username' => 'dewi']);

        $this->artisan('users:grant-manager --from-role=supervisor')
            ->expectsConfirmation('Apply this change?', 'yes')
            ->assertSuccessful();

        $this->assertSame(3, User::whereRaw('LOWER(role) = ?', ['manager'])->count());
        $this->assertSame('barista', User::where('username', 'dewi')->value('role'));
    }

    public function test_declining_changes_nothing(): void
    {
        User::factory()->create(['role' => 'barista', 'username' => 'budi']);

        $this->artisan('users:grant-manager budi')
            ->expectsConfirmation('Apply this change?', 'no')
            ->assertSuccessful();

        $this->assertSame('barista', User::where('username', 'budi')->value('role'));
    }

    public function test_the_manager_role_can_be_revoked(): void
    {
        User::factory()->create(['role' => 'manager', 'username' => 'budi']);

        $this->artisan('users:grant-manager budi --revoke')
            ->expectsConfirmation('Apply this change?', 'yes')
            ->assertSuccessful();

        $this->assertSame('barista', User::where('username', 'budi')->value('role'));
    }

    public function test_grant_manager_needs_something_to_act_on(): void
    {
        $this->artisan('users:grant-manager')->assertFailed();
    }

    public function test_audit_reports_what_blocks_sign_in(): void
    {
        User::factory()->create(['role' => 'Manager', 'username' => 'budi']);
        User::factory()->create(['role' => 'barista', 'username' => '']);
        User::factory()->create(['role' => null, 'username' => 'lama']);

        // A legacy hash the app cannot verify. Written raw: the model's
        // `hashed` cast would otherwise bcrypt the MD5 string.
        $eko = User::factory()->create(['role' => 'barista', 'username' => 'eko']);
        DB::table('users')->where('id', $eko->id)->update(['password' => md5('secret')]);

        $this->artisan('users:audit')
            ->expectsOutputToContain('can sign in')
            ->expectsOutputToContain('have none and cannot sign in')
            ->expectsOutputToContain('cannot verify')
            ->assertSuccessful();
    }
}
