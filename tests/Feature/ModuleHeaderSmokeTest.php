<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleHeaderSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name'     => 'Smoke Admin',
            'email'    => 'smoke@example.com',
            'password' => bcrypt('secret'),
            'roles'    => ['admin'],
        ]);
    }

    public function test_hr_pages_show_hr_nav(): void
    {
        $res = $this->actingAs($this->admin())->get('https://portal.milejet.space/dashboard');
        $res->assertOk()
            ->assertSee('<title>Dashboard</title>', false)
            ->assertSee('Employees')
            ->assertSee('Payslips')
            ->assertDontSee(route('fleet.services'));
    }

    public function test_crm_pages_show_crm_nav(): void
    {
        $res = $this->actingAs($this->admin())->get('https://crm.milejet.space/crm');
        $res->assertOk()
            ->assertSee('Pipeline')
            ->assertSee(route('crm.customers'))
            ->assertDontSee(route('payslips.index'))
            ->assertDontSee(route('employees.index'));
    }

    public function test_fleet_pages_show_fleet_nav_on_any_host(): void
    {
        // module routes are domain-agnostic: portal host must still get fleet nav
        $res = $this->actingAs($this->admin())->get('https://portal.milejet.space/fleet');
        $res->assertOk()
            ->assertSee(route('fleet.services'))
            ->assertDontSee(route('employees.index'));
    }

    public function test_login_brands_by_host(): void
    {
        $this->get('https://fleet.milejet.space/login')
            ->assertOk()->assertSee('Fleet');
        $this->get('https://hr.milejet.space/login')
            ->assertOk()->assertDontSee('Fleet');
    }
}
