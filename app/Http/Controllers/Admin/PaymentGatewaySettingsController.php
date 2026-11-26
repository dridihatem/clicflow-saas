<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentGatewaySettingsController extends Controller
{
    public function edit(): View
    {
        $row = PlatformSetting::query()->first() ?? new PlatformSetting;
        $payment = is_array($row->payment_credentials) ? $row->payment_credentials : [];

        return view('admin.payment-gateways', compact('payment'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'stripe_public_key' => ['nullable', 'string', 'max:255'],
            'stripe_secret_key' => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
            'paypal_client_id' => ['nullable', 'string', 'max:255'],
            'paypal_secret' => ['nullable', 'string', 'max:255'],
            'paypal_mode' => ['nullable', 'string', 'in:sandbox,live'],
            'flouci_public_key' => ['nullable', 'string', 'max:255'],
            'flouci_secret_key' => ['nullable', 'string', 'max:255'],
            'flouci_api_base' => ['nullable', 'string', 'max:500'],
            'bank_account_holder' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_rib' => ['nullable', 'string', 'max:64'],
            'bank_bic' => ['nullable', 'string', 'max:32'],
            'bank_instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        $row = PlatformSetting::query()->first() ?? new PlatformSetting;
        $existing = is_array($row->payment_credentials) ? $row->payment_credentials : [];

        // Non-secret fields: empty string clears the stored value.
        $clearable = [
            'stripe_public_key',
            'stripe_webhook_secret',
            'paypal_client_id',
            'paypal_mode',
            'flouci_public_key',
            'flouci_api_base',
            'bank_account_holder',
            'bank_name',
            'bank_rib',
            'bank_bic',
            'bank_instructions',
        ];
        $payment = $existing;
        foreach ($clearable as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = trim((string) ($data[$key] ?? ''));
            if ($value === '') {
                unset($payment[$key]);
            } else {
                $payment[$key] = $value;
            }
        }

        // Secrets: blank means "keep existing"; only overwrite when a new value is posted.
        foreach (['stripe_secret_key', 'paypal_secret', 'flouci_secret_key'] as $secretKey) {
            $value = trim((string) ($data[$secretKey] ?? ''));
            if ($value !== '') {
                $payment[$secretKey] = $value;
            } elseif (isset($existing[$secretKey])) {
                $payment[$secretKey] = $existing[$secretKey];
            }
        }

        if (! $row->exists) {
            $row->theme_defaults = null;
        }
        $row->payment_credentials = $payment;
        $row->save();

        return redirect()->route('admin.payment-gateways.edit')->with('status', __('Platform payment gateways saved. All workspaces use these keys for customer invoice payments.'));
    }
}
