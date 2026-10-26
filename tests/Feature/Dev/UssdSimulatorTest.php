<?php

namespace Tests\Feature\Dev;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UssdSimulatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['beem.ussd_group_id' => 1]);
        $this->seed(DatabaseSeeder::class);
    }

    private function send(string $command, string $response = '0')
    {
        return $this->postJson('/ussd-simulator', [
            'command' => $command,
            'msisdn' => '255700000001',
            'session_id' => 'SIM-1',
            'response' => $response,
        ]);
    }

    public function test_page_lists_members(): void
    {
        $this->get('/ussd-simulator')->assertOk()->assertSee('Amina Juma')->assertSee('255700000001');
    }

    public function test_simulated_session_reaches_main_menu(): void
    {
        $this->send('initiate')->assertOk()->assertJsonPath('command', 'continue');

        $menu = $this->send('continue', '1234')->assertOk()->json('payload.request');

        $this->assertStringContainsString('Akiba yangu', $menu);
    }

    public function test_terminate_ends_session(): void
    {
        $this->send('initiate');

        $this->send('terminate')->assertOk()->assertJsonPath('command', 'terminate');
    }
}
