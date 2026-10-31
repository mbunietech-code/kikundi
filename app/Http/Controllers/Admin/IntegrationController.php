<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Integrations\Beem\BeemSmsGateway;
use App\Integrations\ClickPesa\ClickPesaClient;
use App\Support\Phone;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Beem SMS and ClickPesa keys, kept by the system administrator. Secret
 * keys are stored encrypted and never shown back: an empty field keeps the
 * saved key.
 */
class IntegrationController extends Controller
{
    public function edit(BeemSmsGateway $beem, ClickPesaClient $clickpesa): View
    {
        if (blank(Settings::get('clickpesa_webhook_token'))) {
            Settings::set(['clickpesa_webhook_token' => Str::random(40)]);
        }

        $settings = Settings::all();

        // Balances are read live; a wrong key shows as a message, not an error page.
        $beemBalance = $beemError = $clickpesaBalance = $clickpesaError = null;

        if (BeemSmsGateway::configured()) {
            try {
                $beemBalance = $beem->balance();
            } catch (Throwable $e) {
                $beemError = $e->getMessage();
            }
        }

        if (ClickPesaClient::configured()) {
            try {
                $clickpesaBalance = $clickpesa->balance();
            } catch (Throwable $e) {
                $clickpesaError = $e->getMessage();
            }
        }

        return view('admin.integrations', [
            'settings' => array_diff_key($settings, array_flip(Settings::SECRETS)),
            'saved' => collect(Settings::SECRETS)->mapWithKeys(fn ($key) => [$key => filled($settings[$key])])->all(),
            'smsDriver' => $settings['sms_driver'] ?: config('beem.sms.driver'),
            'gateway' => $settings['payment_gateway'] ?: config('services.payments.driver'),
            'beemConfigured' => BeemSmsGateway::configured(),
            'beemBalance' => $beemBalance,
            'beemError' => $beemError,
            'clickpesaBalance' => $clickpesaBalance,
            'clickpesaError' => $clickpesaError,
            'webhookUrl' => route('clickpesa.webhook', $settings['clickpesa_webhook_token']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sms_driver' => ['required', Rule::in(['beem', 'log'])],
            'beem_api_key' => ['nullable', 'string', 'max:200'],
            'beem_secret_key' => ['nullable', 'string', 'max:300'],
            'beem_sender_id' => ['nullable', 'string', 'max:11'],
            'payment_gateway' => ['required', Rule::in(['manual', 'clickpesa', 'azampay'])],
            'clickpesa_client_id' => ['nullable', 'string', 'max:200'],
            'clickpesa_api_key' => ['nullable', 'string', 'max:300'],
            'clickpesa_checksum_key' => ['nullable', 'string', 'max:300'],
        ], [], ['beem_sender_id' => __('jina la mtumaji')]);

        // An empty secret field means "keep the saved key".
        foreach (Settings::SECRETS as $key) {
            if (blank($data[$key] ?? null)) {
                unset($data[$key]);
            }
        }

        foreach ($request->input('clear', []) as $key) {
            if (in_array($key, Settings::SECRETS, true)) {
                $data[$key] = '';
            }
        }

        Settings::set($data, $request->user()->id);

        return back()->with('status', __('Mipangilio ya SMS na malipo imehifadhiwa.'));
    }

    /** Send one real SMS to check the Beem keys and sender name. */
    public function testSms(Request $request, BeemSmsGateway $beem): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = Phone::normalise($data['phone']);

        if (! preg_match('/^255[67]\d{8}$/', $phone)) {
            return back()->withErrors(['phone' => __('Weka namba sahihi ya simu, mfano 0712345678.')]);
        }

        try {
            $beem->send($phone, __('Jaribio la SMS kutoka :system. Beem imeunganishwa.', ['system' => Settings::systemName()]));
        } catch (Throwable $e) {
            return back()->with('error', __('SMS haikutumwa: :reason', ['reason' => mb_substr($e->getMessage(), 0, 200)]));
        }

        return back()->with('status', __('SMS ya jaribio imetumwa kwa :phone.', ['phone' => '0'.substr($phone, 3)]));
    }
}
