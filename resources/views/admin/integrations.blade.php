@extends('layouts.app')
@section('title', 'Integrations')
@section('content')
@php
    $tab = request('tab', 'sms');
    $secretInput = function ($key, $placeholder = '') use ($saved) {
        return '<input type="password" name="' . $key . '" class="form-control" autocomplete="new-password" placeholder="'
            . ($saved[$key] ? '•••••••• saved - leave blank to keep' : e($placeholder)) . '">';
    };
@endphp

<div class="row g-3 mb-3">
    @foreach([
        ['sms', 'chat-dots', 'SMS (Advanta)', $effective['sms']['enabled'] && $effective['sms']['ready'], $effective['sms']['enabled'] ? ($effective['sms']['ready'] ? 'Sending' : 'On, but keys missing') : 'Log only'],
        ['mail', 'envelope', 'Email', $effective['mail']['mailer'] === 'smtp' && $effective['mail']['host'], $effective['mail']['mailer'] === 'smtp' ? ($effective['mail']['host'] ?: 'SMTP not set') : 'Log only'],
        ['mpesa', 'phone', 'M-Pesa (Daraja)', $effective['mpesa']['ready'], $effective['mpesa']['ready'] ? ucfirst($effective['mpesa']['env']) : 'Not configured'],
        ['whatsapp', 'whatsapp', 'WhatsApp', $effective['whatsapp']['ready'], $effective['whatsapp']['ready'] ? 'Configured' : 'Not configured'],
        ['crb', 'clipboard-data', 'CRB (Metropol)', $effective['crb']['ready'], $effective['crb']['ready'] ? 'Configured' : 'Manual entry only'],
        ['identity', 'person-bounding-box', 'ID check (Smile ID)', $effective['identity']['ready'], $effective['identity']['ready'] ? ucfirst($effective['identity']['env']) : 'Manual review only'],
    ] as [$key, $icon, $name, $ok, $status])
        <div class="col-6 col-md-4 col-xl-2"><a href="?tab={{ $key }}" class="text-decoration-none"><div class="card h-100 {{ $tab === $key ? 'border border-success' : '' }}"><div class="card-body d-flex align-items-center gap-3">
            <i class="bi bi-{{ $icon }} fs-3 text-{{ $ok ? 'success' : 'secondary' }}"></i>
            <div><div class="fw-semibold text-dark">{{ $name }}</div><small class="text-{{ $ok ? 'success' : 'muted' }}"><i class="bi bi-circle-fill" style="font-size:.5rem"></i> {{ $status }}</small></div>
        </div></div></a></div>
    @endforeach
</div>

<div class="row g-3">
<div class="col-lg-8">

{{-- ================= SMS ================= --}}
@if($tab === 'sms')
<div class="card"><div class="card-body">
    <h5 class="mb-1">SMS – Advanta</h5>
    <p class="small text-muted">Used for deposit confirmations, loan approvals, reminders, OTP codes and bulk messages. Get these from your Advanta account (quicksms.advantasms.com → API).</p>
    <form method="POST" action="{{ route('admin.integrations.update', 'sms') }}">@csrf @method('PUT')
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="sms_enabled" value="1" id="smsOn" @checked($effective['sms']['enabled'])>
            <label class="form-check-label" for="smsOn">Send SMS for real <span class="text-muted small">(off = messages are only written to the log, for testing)</span></label>
        </div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Partner ID</label><input name="advanta_partner_id" value="{{ old('advanta_partner_id', $values['advanta_partner_id']) }}" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Sender ID / shortcode</label><input name="advanta_shortcode" value="{{ old('advanta_shortcode', $values['advanta_shortcode']) }}" class="form-control" placeholder="e.g. JOOTRH-SACCO"></div>
            <div class="col-12"><label class="form-label">API key</label>{!! $secretInput('advanta_api_key') !!}</div>
            <div class="col-12"><label class="form-label">API URL</label><input name="advanta_url" value="{{ old('advanta_url', $values['advanta_url']) }}" class="form-control" placeholder="{{ config('sacco.sms.url') }}">
                <div class="form-text">Leave blank to use the default Advanta endpoint.</div></div>
        </div>
        <button class="btn btn-success mt-3"><i class="bi bi-save"></i> Save SMS settings</button>
    </form>
    <hr>
    <form method="POST" action="{{ route('admin.integrations.test.sms') }}" class="d-flex gap-2 align-items-end">@csrf
        <div class="flex-grow-1"><label class="form-label small">Send a test SMS to</label><input name="phone" value="{{ old('phone', auth()->user()->phone) }}" class="form-control" placeholder="07XXXXXXXX" required></div>
        <button class="btn btn-outline-success"><i class="bi bi-send"></i> Send test</button>
    </form>
</div></div>
@endif

{{-- ================= EMAIL ================= --}}
@if($tab === 'mail')
<div class="card"><div class="card-body">
    <h5 class="mb-1">Email – SMTP</h5>
    <p class="small text-muted">Used for statements, ticket replies, OTP codes and bulk email. Works with Gmail/Google Workspace, Microsoft 365, cPanel/DirectAdmin mail, or any SMTP server.</p>
    <form method="POST" action="{{ route('admin.integrations.update', 'mail') }}">@csrf @method('PUT')
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Mailer</label>
                <select name="mail_mailer" class="form-select">
                    <option value="smtp" @selected(($values['mail_mailer'] ?? $effective['mail']['mailer']) === 'smtp')>SMTP (send)</option>
                    <option value="log" @selected(($values['mail_mailer'] ?? $effective['mail']['mailer']) === 'log')>Log only (testing)</option>
                </select></div>
            <div class="col-md-5"><label class="form-label">SMTP host</label><input name="mail_host" value="{{ old('mail_host', $values['mail_host']) }}" class="form-control" placeholder="smtp.gmail.com / mail.jootrh.go.ke"></div>
            <div class="col-md-3"><label class="form-label">Port</label><input name="mail_port" value="{{ old('mail_port', $values['mail_port']) }}" class="form-control" placeholder="587"></div>
            <div class="col-md-4"><label class="form-label">Encryption</label>
                <select name="mail_encryption" class="form-select">
                    @foreach(['tls' => 'TLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None (port 25)'] as $k => $v)
                        <option value="{{ $k }}" @selected(($values['mail_encryption'] ?? 'tls') === $k)>{{ $v }}</option>
                    @endforeach
                </select></div>
            <div class="col-md-4"><label class="form-label">Username</label><input name="mail_username" value="{{ old('mail_username', $values['mail_username']) }}" class="form-control" autocomplete="off"></div>
            <div class="col-md-4"><label class="form-label">Password</label>{!! $secretInput('mail_password') !!}</div>
            <div class="col-md-6"><label class="form-label">From address</label><input name="mail_from_address" value="{{ old('mail_from_address', $values['mail_from_address']) }}" class="form-control" placeholder="sacco@jootrh.go.ke"></div>
            <div class="col-md-6"><label class="form-label">From name</label><input name="mail_from_name" value="{{ old('mail_from_name', $values['mail_from_name']) }}" class="form-control" placeholder="{{ sacco_name() }}"></div>
        </div>
        <div class="form-text mt-2">Gmail: turn on 2-step verification and use an <strong>App password</strong>, not your normal password.</div>
        <button class="btn btn-success mt-3"><i class="bi bi-save"></i> Save email settings</button>
    </form>
    <hr>
    <form method="POST" action="{{ route('admin.integrations.test.email') }}" class="d-flex gap-2 align-items-end">@csrf
        <div class="flex-grow-1"><label class="form-label small">Send a test email to</label><input type="email" name="to" value="{{ old('to', str_ends_with(auth()->user()->email, '.local') ? '' : auth()->user()->email) }}" class="form-control" required></div>
        <button class="btn btn-outline-success"><i class="bi bi-send"></i> Send test</button>
    </form>
</div></div>
@endif

{{-- ================= M-PESA ================= --}}
@if($tab === 'mpesa')
<div class="card mb-3"><div class="card-body">
    <h5 class="mb-1">M-Pesa – Safaricom Daraja</h5>
    <p class="small text-muted">Paybill payments post automatically to the right member account or loan. Create an app on <strong>developer.safaricom.co.ke</strong>, then use the sandbox keys to test and the Go-Live keys for production.</p>
    <form method="POST" action="{{ route('admin.integrations.update', 'mpesa') }}">@csrf @method('PUT')
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Environment</label>
                <select name="mpesa_env" class="form-select">
                    <option value="sandbox" @selected(($values['mpesa_env'] ?? $effective['mpesa']['env']) === 'sandbox')>Sandbox (testing)</option>
                    <option value="production" @selected(($values['mpesa_env'] ?? $effective['mpesa']['env']) === 'production')>Production (live)</option>
                </select></div>
            <div class="col-md-4"><label class="form-label">Paybill / till number</label><input name="mpesa_paybill" value="{{ old('mpesa_paybill', $values['mpesa_paybill']) }}" class="form-control" placeholder="600000 (sandbox)"></div>
            <div class="col-md-4"><label class="form-label">STK Push shortcode</label><input name="mpesa_stk_shortcode" value="{{ old('mpesa_stk_shortcode', $values['mpesa_stk_shortcode']) }}" class="form-control" placeholder="174379 (sandbox)">
                <div class="form-text">Blank = same as paybill.</div></div>
            <div class="col-md-6"><label class="form-label">Consumer key</label>{!! $secretInput('mpesa_consumer_key') !!}</div>
            <div class="col-md-6"><label class="form-label">Consumer secret</label>{!! $secretInput('mpesa_consumer_secret') !!}</div>
            <div class="col-12"><label class="form-label">Lipa na M-Pesa Online passkey</label>{!! $secretInput('mpesa_passkey', 'Needed only for STK Push') !!}</div>
            <div class="col-12"><label class="form-label">Public HTTPS base URL</label>
                <input name="public_url" value="{{ old('public_url', $values['public_url']) }}" class="form-control" placeholder="https://sacco.jootrh.go.ke   or   https://xxxx.ngrok-free.app">
                <div class="form-text">The address Safaricom calls back. On XAMPP use an ngrok tunnel for testing; on the server use your live domain. Blank = APP_URL ({{ config('app.url') }}).</div></div>
            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="regenerate_token" value="1" id="regen">
                <label class="form-check-label small" for="regen">Generate a new callback security token (you must then click "Register C2B URLs" again)</label></div></div>
        </div>
        <button class="btn btn-success mt-3"><i class="bi bi-save"></i> Save M-Pesa settings</button>
    </form>
</div></div>

<div class="card"><div class="card-body">
    <h6>Callback URLs</h6>
    <p class="small text-muted mb-2">These are registered with Safaricom by the button below. The token stops anyone else from posting fake payments.</p>
    @foreach(['confirmation' => 'C2B Confirmation', 'validation' => 'C2B Validation', 'stk' => 'STK Push callback'] as $k => $label)
        <label class="small text-muted mb-0">{{ $label }}</label>
        <div class="input-group input-group-sm mb-2">
            <input class="form-control font-monospace" value="{{ $urls[$k] }}" readonly id="u{{ $k }}">
            <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('u{{ $k }}').value); this.innerHTML='<i class=\'bi bi-check2\'></i>'"><i class="bi bi-clipboard"></i></button>
        </div>
    @endforeach
    @unless(str_starts_with($urls['confirmation'], 'https://'))
        <div class="alert alert-warning small py-2">These aren't HTTPS public addresses yet, so Safaricom can't reach them. Set the Public HTTPS base URL above.</div>
    @endunless

    <div class="d-flex flex-wrap gap-2 mt-3">
        <form method="POST" action="{{ route('admin.integrations.test.mpesa') }}">@csrf<button class="btn btn-outline-success"><i class="bi bi-plug"></i> Test connection</button></form>
        <form method="POST" action="{{ route('admin.integrations.mpesa.register') }}" data-confirm="Register these URLs with Safaricom for paybill {{ config('sacco.mpesa.paybill') }}?">@csrf<button class="btn btn-success"><i class="bi bi-cloud-upload"></i> Register C2B URLs</button></form>
    </div>
    <hr>
    <form method="POST" action="{{ route('admin.integrations.test.stk') }}" class="row g-2 align-items-end">@csrf
        <div class="col-md-6"><label class="form-label small">Test STK Push to phone</label><input name="phone" value="{{ old('phone', auth()->user()->phone) }}" class="form-control" placeholder="07XXXXXXXX" required></div>
        <div class="col-md-3"><label class="form-label small">Amount (KES)</label><input type="number" name="amount" value="1" min="1" max="10" class="form-control"></div>
        <div class="col-md-3"><button class="btn btn-outline-success w-100"><i class="bi bi-phone-vibrate"></i> Send prompt</button></div>
    </form>
</div></div>
@endif

{{-- ================= CRB ================= --}}
@if($tab === 'crb')
<div class="card"><div class="card-body">
    <h5 class="mb-1">Credit Reference Bureau – Metropol</h5>
    <p class="small text-muted">Lets loan officers pull a member's credit score and listing status from the loan screen (with the member's consent). Metropol issues the public and private keys and the API document when you sign up. Without keys you can still record results from any bureau's web portal by hand.</p>
    <form method="POST" action="{{ route('admin.integrations.update', 'crb') }}">@csrf @method('PUT')
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Public API key</label>{!! $secretInput('metropol_public_key') !!}</div>
            <div class="col-md-6"><label class="form-label">Private key</label>{!! $secretInput('metropol_private_key') !!}</div>
            <div class="col-12"><label class="form-label">API base URL</label><input name="metropol_base_url" value="{{ old('metropol_base_url', $values['metropol_base_url']) }}" class="form-control" placeholder="https://api.metropol.co.ke:5555/v2_1">
                <div class="form-text">Use the testbed URL Metropol gives you while testing.</div></div>
            @foreach(['metropol_path_score' => '/score/consumer', 'metropol_path_delinquency' => '/deliquency/status', 'metropol_path_identity' => '/identity/verify', 'metropol_path_report' => '/report/json'] as $k => $ph)
                <div class="col-md-6"><label class="form-label small">{{ \App\Support\Integrations::FIELDS[$k][0] }}</label><input name="{{ $k }}" value="{{ old($k, $values[$k]) }}" class="form-control form-control-sm font-monospace" placeholder="{{ $ph }}"></div>
            @endforeach
        </div>
        <div class="form-text mt-2">Paths differ between Metropol API versions. Check them against the document Metropol sends you; blank uses the defaults shown.</div>
        <button class="btn btn-success mt-3"><i class="bi bi-save"></i> Save CRB settings</button>
    </form>
</div></div>
@endif

{{-- ================= IDENTITY ================= --}}
@if($tab === 'identity')
<div class="card"><div class="card-body">
    <h5 class="mb-1">Identity verification – Smile ID</h5>
    <p class="small text-muted">Checks a member's ID number, names and date of birth against the national registry (IPRS) when they apply online or from the member's Identity page. The live-selfie vs ID-photo match is reviewed by staff side by side.</p>
    <form method="POST" action="{{ route('admin.integrations.update', 'identity') }}">@csrf @method('PUT')
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Environment</label>
                <select name="smileid_env" class="form-select"><option value="sandbox" @selected(($values['smileid_env'] ?? 'sandbox') === 'sandbox')>Sandbox</option><option value="production" @selected(($values['smileid_env'] ?? '') === 'production')>Production</option></select></div>
            <div class="col-md-8"><label class="form-label">Partner ID</label><input name="smileid_partner_id" value="{{ old('smileid_partner_id', $values['smileid_partner_id']) }}" class="form-control"></div>
            <div class="col-12"><label class="form-label">API key</label>{!! $secretInput('smileid_api_key') !!}</div>
        </div>
        <button class="btn btn-success mt-3"><i class="bi bi-save"></i> Save identity settings</button>
    </form>
</div></div>
@endif

{{-- ================= WHATSAPP ================= --}}
@if($tab === 'whatsapp')
<div class="card"><div class="card-body">
    <h5 class="mb-1">WhatsApp – Meta Cloud API <span class="badge text-bg-light">optional</span></h5>
    <p class="small text-muted">Lets bulk messages also go out on WhatsApp. Set it up in Meta Business Manager → WhatsApp → API Setup. Messages you start (not replies) need an approved template unless the member messaged you in the last 24 hours.</p>
    <form method="POST" action="{{ route('admin.integrations.update', 'whatsapp') }}">@csrf @method('PUT')
        <div class="row g-3">
            <div class="col-md-5"><label class="form-label">Phone number ID</label><input name="whatsapp_phone_id" value="{{ old('whatsapp_phone_id', $values['whatsapp_phone_id']) }}" class="form-control"></div>
            <div class="col-md-7"><label class="form-label">Permanent access token</label>{!! $secretInput('whatsapp_token') !!}</div>
        </div>
        <button class="btn btn-success mt-3"><i class="bi bi-save"></i> Save WhatsApp settings</button>
    </form>
    <hr>
    <form method="POST" action="{{ route('admin.integrations.test.whatsapp') }}" class="d-flex gap-2 align-items-end">@csrf
        <div class="flex-grow-1"><label class="form-label small">Send a test message to</label><input name="phone" class="form-control" placeholder="07XXXXXXXX" required></div>
        <button class="btn btn-outline-success"><i class="bi bi-send"></i> Send test</button>
    </form>
</div></div>
@endif
</div>

<div class="col-lg-4">
    <div class="card mb-3"><div class="card-body small">
        <h6>How this works</h6>
        <ul class="ps-3 mb-0">
            <li>Values saved here <strong>override .env</strong>. Leave a field blank to fall back to .env.</li>
            <li>Keys and passwords are stored <strong>encrypted</strong> with your APP_KEY and are never shown again. Leave them blank when saving to keep the current value.</li>
            <li>Back up your <code>.env</code> (APP_KEY). If APP_KEY changes, re-enter the secrets.</li>
            <li>Every change is recorded in the audit trail.</li>
        </ul>
    </div></div>
    <div class="card"><div class="card-body small">
        <div class="d-flex"><h6>Recent messages</h6>@if(\Illuminate\Support\Facades\Route::has('messaging.index'))<a href="{{ route('messaging.index') }}" class="ms-auto">All</a>@endif</div>
        @forelse($recent as $m)
            <div class="border-bottom py-1"><span class="badge text-bg-{{ ['sent' => 'success', 'failed' => 'danger', 'logged' => 'secondary'][$m->status] }}">{{ $m->status }}</span>
                {{ $m->channel }} → {{ $m->recipient }} <span class="text-muted">{{ $m->created_at->diffForHumans() }}</span><br>
                <span class="text-muted">{{ \Illuminate\Support\Str::limit($m->message, 70) }}</span></div>
        @empty <span class="text-muted">No messages yet.</span> @endforelse
    </div></div>
</div>
</div>
@endsection
