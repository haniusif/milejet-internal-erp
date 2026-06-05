<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OdooService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const UID   = 77;
    private const EMAIL = 'driver.boss@milejet.space';
    private const PASS  = 'correct-horse';

    /** Mock the Odoo XML-RPC boundary for a user holding the given groups. */
    private function mockOdoo(array $groupFullNames): void
    {
        $groupIds = range(101, 100 + count($groupFullNames));
        $groups = [];
        foreach (array_values($groupFullNames) as $i => $full) {
            [$cat, $name] = explode(' / ', $full, 2);
            $groups[] = ['name' => $name, 'category_id' => [9, $cat]];
        }

        $this->mock(OdooService::class, function ($mock) use ($groupIds, $groups) {
            $mock->shouldReceive('tryAuthenticate')
                ->with(self::EMAIL, self::PASS)->andReturn(self::UID);
            $mock->shouldReceive('tryAuthenticate')->andReturn(false);
            $mock->shouldReceive('setCredentials')->andReturnSelf();
            $mock->shouldReceive('read')
                ->with('res.users', [self::UID], ['name', 'groups_id'])
                ->andReturn([['name' => 'Fleet Boss', 'groups_id' => $groupIds]]);
            $mock->shouldReceive('read')
                ->with('res.groups', $groupIds, ['name', 'category_id'])
                ->andReturn($groups);
        });
    }

    public function test_valid_odoo_credentials_log_in_and_map_roles(): void
    {
        $this->mockOdoo(['Fleet / Administrator', 'User types / Internal User']);

        $res = $this->post('/login', ['email' => self::EMAIL, 'password' => self::PASS]);

        $res->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');
        $this->assertAuthenticated();

        $user = User::where('odoo_uid', self::UID)->first();
        $this->assertNotNull($user);
        $this->assertSame('Fleet Boss', $user->name);
        $this->assertSame(self::EMAIL, $user->email);
        // Fleet / Administrator -> fleet_manager (implies fleet_officer) + employee
        $this->assertEqualsCanonicalizing(
            ['fleet_manager', 'fleet_officer', 'employee'],
            $user->roles
        );
        $this->assertTrue($user->can('fleet.view'));
        $this->assertTrue($user->can('fleet.write'));
        $this->assertFalse($user->can('payslips.view'));
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->mockOdoo(['User types / Internal User']);

        $res = $this->from('/login')->post('/login', [
            'email' => self::EMAIL, 'password' => 'wrong-password',
        ]);

        $res->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_redirects_to_intended_module_page(): void
    {
        $this->mockOdoo(['Fleet / Administrator', 'User types / Internal User']);

        // guest hits a protected fleet page -> bounced to login
        $this->get('https://fleet.milejet.space/fleet')->assertRedirect();

        // logging in lands back on the page they wanted
        $this->post('https://fleet.milejet.space/login', [
            'email' => self::EMAIL, 'password' => self::PASS,
        ])->assertRedirect('https://fleet.milejet.space/fleet');
    }

    public function test_repeat_login_updates_existing_user_instead_of_duplicating(): void
    {
        $this->mockOdoo(['Employees / Administrator', 'User types / Internal User']);

        $this->post('/login', ['email' => self::EMAIL, 'password' => self::PASS]);
        $this->post('/logout');
        $this->post('/login', ['email' => self::EMAIL, 'password' => self::PASS]);

        $this->assertDatabaseCount('users', 1);
        // roles re-synced on every login: now HR, no fleet
        $user = User::where('odoo_uid', self::UID)->first();
        $this->assertContains('hr_manager', $user->roles);
        $this->assertNotContains('fleet_manager', $user->roles);
    }

    public function test_logout_kills_the_session(): void
    {
        $this->mockOdoo(['User types / Internal User']);
        $this->post('/login', ['email' => self::EMAIL, 'password' => self::PASS]);
        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_validation_rejects_garbage_before_hitting_odoo(): void
    {
        $this->mock(OdooService::class, function ($mock) {
            $mock->shouldNotReceive('tryAuthenticate');
        });

        $this->from('/login')->post('/login', ['email' => 'not-an-email', 'password' => ''])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }
}
