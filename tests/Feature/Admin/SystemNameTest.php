<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemNameTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'system_name' => 'Umoja Welfare',
            'support_contact' => '0700000000',
            'ussd_min_amount' => 500,
            'ussd_max_amount' => 5000000,
        ], $overrides);
    }

    public function test_system_admin_can_rename_the_system(): void
    {
        $admin = User::factory()->create(['role' => 'system_admin']);

        $this->actingAs($admin)->put('/admin/settings', $this->payload())->assertRedirect();

        $this->assertSame('Umoja Welfare', Settings::systemName());
        $this->actingAs($admin)->get('/admin/settings')->assertSee('Umoja Welfare');
    }

    public function test_other_users_cannot_rename_the_system(): void
    {
        $treasurer = User::factory()->create(['role' => 'treasurer']);

        $this->actingAs($treasurer)->put('/admin/settings', $this->payload())->assertForbidden();
        $this->assertSame('Mfumo wa Kikundi', Settings::systemName());
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin/settings')->assertRedirect('/login');
    }

    public function test_name_is_required_and_short(): void
    {
        $admin = User::factory()->create(['role' => 'system_admin']);

        $this->actingAs($admin)->put('/admin/settings', $this->payload(['system_name' => str_repeat('x', 41)]))
            ->assertSessionHasErrors('system_name');
    }
}
