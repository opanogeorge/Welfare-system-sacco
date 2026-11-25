<?php

namespace App\Services;

use App\Support\Integrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Safaricom Daraja: OAuth, STK Push (Lipa na M-Pesa Online) and C2B URL registration.
 * STK results arrive at /api/payments/stk/callback and are posted like C2B payments using the
 * account reference (member no., member no.-SAV, or loan no.).
 */
class MpesaStkService
{
    public function base(): string
    {
        return config('sacco.mpesa.env') === 'production' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
    }

    public function token(bool $fresh = false): string
    {
        if (! config('sacco.mpesa.consumer_key') || ! config('sacco.mpesa.consumer_secret')) {
            throw new RuntimeException('Enter the Daraja consumer key and secret first.');
        }
        if ($fresh) Cache::forget('mpesa_token');
        return Cache::remember('mpesa_token', 3000, function () {
            $res = Http::withBasicAuth(config('sacco.mpesa.consumer_key'), config('sacco.mpesa.consumer_secret'))
                ->timeout(20)->get($this->base() . '/oauth/v1/generate?grant_type=client_credentials');
            if (! $res->successful() || ! $res->json('access_token')) {
                throw new RuntimeException('Daraja authentication failed (' . $res->status() . '). Check the consumer key/secret and the environment (sandbox vs production).');
            }
            return $res->json('access_token');
        });
    }

    public function push(string $phone, float $amount, string $accountRef, string $description = 'SACCO payment'): array
    {
        $shortcode = config('sacco.mpesa.stk_shortcode') ?: config('sacco.mpesa.paybill');
        if (! $shortcode || ! config('sacco.mpesa.passkey')) throw new RuntimeException('Enter the STK shortcode and passkey first.');
        $ts = now()->format('YmdHis');
        $phone = app(SmsService::class)->normalize($phone);

        $res = Http::withToken($this->token())->timeout(30)->post($this->base() . '/mpesa/stkpush/v1/processrequest', [
            'BusinessShortCode' => $shortcode,
            'Password'          => base64_encode($shortcode . config('sacco.mpesa.passkey') . $ts),
            'Timestamp'         => $ts,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => (int) ceil($amount),
            'PartyA'            => $phone,
            'PartyB'            => $shortcode,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => Integrations::callbackUrls()['stk'],
            'AccountReference'  => substr($accountRef, 0, 12),
            'TransactionDesc'   => substr($description, 0, 13),
        ]);
        if (! $res->successful() || $res->json('ResponseCode') !== '0') {
            throw new RuntimeException('STK push failed: ' . ($res->json('errorMessage') ?? $res->json('ResponseDescription') ?? $res->body()));
        }
        Cache::put('stk_' . $res->json('CheckoutRequestID'), ['ref' => $accountRef, 'phone' => $phone], now()->addHours(2));
        return $res->json();
    }

    /** Tell Safaricom where to send paybill payments. */
    public function registerC2bUrls(): array
    {
        $shortcode = config('sacco.mpesa.paybill');
        if (! $shortcode) throw new RuntimeException('Enter the paybill / till number first.');
        $urls = Integrations::callbackUrls();
        if (! str_starts_with($urls['confirmation'], 'https://')) {
            throw new RuntimeException('Safaricom needs a public HTTPS address. Set the "Public HTTPS base URL" (your live domain or an ngrok URL).');
        }
        $res = Http::withToken($this->token())->timeout(30)->post($this->base() . '/mpesa/c2b/v2/registerurl', [
            'ShortCode'       => $shortcode,
            'ResponseType'    => 'Completed',
            'ConfirmationURL' => $urls['confirmation'],
            'ValidationURL'   => $urls['validation'],
        ]);
        if (! $res->successful() || ! in_array((string) $res->json('ResponseCode'), ['0', '00000000'], true)) {
            throw new RuntimeException('Registration failed: ' . ($res->json('errorMessage') ?? $res->json('ResponseDescription') ?? $res->body()));
        }
        return $res->json();
    }
}
