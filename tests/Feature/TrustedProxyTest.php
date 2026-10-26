<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    private const HEADERS = ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'abc.ngrok-free.app', 'X-Forwarded-For' => '41.59.0.10'];

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_proxy-probe', fn (Request $request) => ['css' => asset('css/app.css'), 'ip' => $request->ip()]);
    }

    public function test_tunnel_on_this_machine_gets_https_links_and_the_real_client_ip(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->get('/_proxy-probe', self::HEADERS)
            ->assertJson(['css' => 'https://abc.ngrok-free.app/css/app.css', 'ip' => '41.59.0.10']);
    }

    public function test_other_clients_cannot_fake_forwarded_headers(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.3.20'])->get('/_proxy-probe', self::HEADERS)
            ->assertJsonPath('ip', '192.168.3.20')
            ->assertJsonMissing(['css' => 'https://abc.ngrok-free.app/css/app.css']);
    }
}
