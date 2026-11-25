<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MessageLog;
use App\Services\MpesaStkService;
use App\Services\NotificationService;
use App\Services\SmsService;
use App\Support\Integrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Admin > Integrations: SMS, email, M-Pesa and WhatsApp credentials with live tests. */
class IntegrationController extends Controller
{
    public function edit()
    {
        $values = [];
        foreach (Integrations::FIELDS as $key => [$label, $group, $secret]) {
            // Secrets are never sent back to the browser - only whether one is saved.
            $values[$key] = $secret ? null : Integrations::get($key);
        }
        $saved = collect(Integrations::FIELDS)->keys()->mapWithKeys(fn ($k) => [$k => Integrations::has($k)]);

        // What is actually in effect right now (admin value, else .env)
        $effective = [
            'sms'   => ['enabled' => (bool) config('sacco.sms.enabled'), 'ready' => (bool) (config('sacco.sms.api_key') && config('sacco.sms.partner_id') && config('sacco.sms.shortcode'))],
            'mail'  => ['mailer' => config('mail.default'), 'from' => config('mail.from.address'), 'host' => config('mail.mailers.smtp.host')],
            'mpesa' => ['env' => config('sacco.mpesa.env'), 'ready' => (bool) (config('sacco.mpesa.consumer_key') && config('sacco.mpesa.consumer_secret'))],
            'whatsapp' => ['ready' => (bool) config('sacco.whatsapp.token')],
            'crb' => ['ready' => Integrations::has('metropol_public_key') && Integrations::has('metropol_private_key')],
            'identity' => ['ready' => Integrations::has('smileid_partner_id') && Integrations::has('smileid_api_key'), 'env' => Integrations::get('smileid_env') ?? 'sandbox'],
        ];
        $urls = Integrations::callbackUrls();
        $recent = MessageLog::latest()->take(8)->get();
        return view('admin.integrations', compact('values', 'saved', 'effective', 'urls', 'recent'));
    }

    public function update(Request $request, string $group)
    {
        abort_unless(in_array($group, ['sms', 'mail', 'mpesa', 'whatsapp', 'crb', 'identity'], true), 404);
        $rules = match ($group) {
            'sms'      => ['advanta_url' => 'nullable|url', 'advanta_partner_id' => 'nullable|string|max:20', 'advanta_shortcode' => 'nullable|string|max:20', 'advanta_api_key' => 'nullable|string|max:200'],
            'mail'     => ['mail_mailer' => 'required|in:smtp,log', 'mail_host' => 'nullable|string|max:150', 'mail_port' => 'nullable|integer|min:1|max:65535',
                           'mail_encryption' => 'nullable|in:tls,ssl,none', 'mail_username' => 'nullable|string|max:150', 'mail_password' => 'nullable|string|max:200',
                           'mail_from_address' => 'nullable|email', 'mail_from_name' => 'nullable|string|max:100'],
            'mpesa'    => ['mpesa_env' => 'required|in:sandbox,production', 'mpesa_paybill' => 'nullable|digits_between:5,8', 'mpesa_stk_shortcode' => 'nullable|digits_between:5,8',
                           'mpesa_consumer_key' => 'nullable|string|max:200', 'mpesa_consumer_secret' => 'nullable|string|max:200', 'mpesa_passkey' => 'nullable|string|max:200',
                           'public_url' => 'nullable|url'],
            'whatsapp' => ['whatsapp_token' => 'nullable|string|max:1000', 'whatsapp_phone_id' => 'nullable|string|max:40'],
            'crb'      => ['metropol_public_key' => 'nullable|string|max:300', 'metropol_private_key' => 'nullable|string|max:300', 'metropol_base_url' => 'nullable|url',
                           'metropol_path_score' => 'nullable|string|max:100', 'metropol_path_delinquency' => 'nullable|string|max:100',
                           'metropol_path_identity' => 'nullable|string|max:100', 'metropol_path_report' => 'nullable|string|max:100'],
            'identity' => ['smileid_env' => 'required|in:sandbox,production', 'smileid_partner_id' => 'nullable|string|max:20', 'smileid_api_key' => 'nullable|string|max:300'],
        };
        $data = $request->validate($rules);

        foreach (Integrations::FIELDS as $key => [$label, $g, $secret]) {
            if ($g !== $group || $key === 'mpesa_callback_token' || $key === 'sms_enabled') continue;
            if ($secret && ($data[$key] ?? '') === '' && ! $request->boolean("clear_$key")) continue; // blank = keep existing
            Integrations::put($key, $data[$key] ?? null);
        }
        if ($group === 'sms') Integrations::put('sms_enabled', $request->boolean('sms_enabled') ? '1' : '0');
        if ($group === 'mpesa') {
            if (! Integrations::has('mpesa_callback_token') || $request->boolean('regenerate_token')) {
                Integrations::put('mpesa_callback_token', Str::random(40));
            }
            Cache::forget('mpesa_token');
        }
        AuditLog::record('integration_updated', null, ['group' => $group]);
        return redirect()->route('admin.integrations', ['tab' => $group])->with('success', strtoupper($group) . ' settings saved.');
    }

    public function testSms(Request $request, SmsService $sms)
    {
        $request->validate(['phone' => 'required|string|max:20']);
        if (! config('sacco.sms.enabled')) {
            return back()->with('warning', 'SMS sending is switched off, so the test message was only written to the log. Tick "Send SMS" and save to send for real.')->withInput();
        }
        $ok = $sms->send($request->phone, 'Test SMS from ' . sacco_name() . ' at ' . now()->format('H:i') . '. Your SMS integration works.');
        return redirect()->route('admin.integrations', ['tab' => 'sms'])->with($ok ? 'success' : 'error',
            $ok ? 'Test SMS sent to ' . $request->phone . '. Check the phone.' : 'Advanta rejected the message. Check the API key, partner ID, sender ID and your SMS balance; details are in storage/logs/laravel.log.');
    }

    public function testEmail(Request $request)
    {
        $request->validate(['to' => 'required|email']);
        try {
            Mail::raw("This is a test email from " . sacco_name() . ".\n\nIf you can read this, your email settings work.", fn ($m) => $m->to($request->to)->subject('Test email - ' . sacco_name()));
        } catch (\Throwable $e) {
            return redirect()->route('admin.integrations', ['tab' => 'mail'])->with('error', 'Email failed: ' . Str::limit($e->getMessage(), 300));
        }
        $where = config('mail.default') === 'log' ? ' (mailer is "log", so it was written to storage/logs/laravel.log)' : '';
        return redirect()->route('admin.integrations', ['tab' => 'mail'])->with('success', "Test email sent to {$request->to}$where.");
    }

    public function testMpesa(MpesaStkService $mpesa)
    {
        try {
            $mpesa->token(true);
        } catch (\Throwable $e) {
            return redirect()->route('admin.integrations', ['tab' => 'mpesa'])->with('error', $e->getMessage());
        }
        return redirect()->route('admin.integrations', ['tab' => 'mpesa'])->with('success', 'Connected to Daraja (' . config('sacco.mpesa.env') . '). Credentials are valid.');
    }

    public function registerC2b(MpesaStkService $mpesa)
    {
        try {
            $res = $mpesa->registerC2bUrls();
        } catch (\Throwable $e) {
            return redirect()->route('admin.integrations', ['tab' => 'mpesa'])->with('error', $e->getMessage());
        }
        AuditLog::record('mpesa_c2b_registered', null, ['shortcode' => config('sacco.mpesa.paybill')]);
        return redirect()->route('admin.integrations', ['tab' => 'mpesa'])->with('success', 'C2B URLs registered with Safaricom: ' . ($res['ResponseDescription'] ?? 'OK'));
    }

    public function testStk(Request $request, MpesaStkService $mpesa)
    {
        $request->validate(['phone' => 'required|string|max:20', 'amount' => 'required|integer|min:1|max:10']);
        try {
            $mpesa->push($request->phone, (float) $request->amount, 'TEST', 'Test');
        } catch (\Throwable $e) {
            return redirect()->route('admin.integrations', ['tab' => 'mpesa'])->with('error', $e->getMessage());
        }
        return redirect()->route('admin.integrations', ['tab' => 'mpesa'])->with('success', 'STK prompt sent to ' . $request->phone . '. After paying, the payment appears under M-Pesa Payments as "unmatched" (account TEST).');
    }

    public function testWhatsapp(Request $request, NotificationService $notify)
    {
        $request->validate(['phone' => 'required|string|max:20']);
        $notify->whatsapp($request->phone, 'Test WhatsApp message from ' . sacco_name() . '.');
        $last = MessageLog::where('channel', 'whatsapp')->latest()->first();
        return redirect()->route('admin.integrations', ['tab' => 'whatsapp'])->with($last?->status === 'sent' ? 'success' : 'error',
            $last?->status === 'sent' ? 'WhatsApp test sent.' : 'WhatsApp send failed or is not configured. Business-initiated messages need an approved template unless the member messaged you in the last 24 hours.');
    }
}
