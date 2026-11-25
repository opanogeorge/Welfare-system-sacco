<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    /** Login with email, phone number or member number. Staff get an OTP when two-factor is on. */
    public function login(Request $request)
    {
        $data = $request->validate(['login' => 'required|string', 'password' => 'required|string']);
        $login = trim($data['login']);

        $user = User::where('email', $login)->orWhere('phone', $login)
            ->orWhereHas('member', fn ($q) => $q->where('member_no', strtoupper($login)))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            AuditLog::create(['user_id' => $user?->id, 'action' => 'login_failed', 'data' => ['login' => $login], 'ip' => $request->ip()]);
            return back()->withErrors(['login' => 'Invalid credentials.'])->onlyInput('login');
        }
        if (! $user->is_active) {
            return back()->withErrors(['login' => 'Your account is disabled. Contact the SACCO office.']);
        }

        $twoFactor = $user->isStaff() ? Setting::get('staff_2fa', 0) : Setting::get('member_2fa', 0);
        if ($twoFactor) {
            $code = (string) random_int(100000, 999999);
            $user->update(['otp_code' => Hash::make($code), 'otp_expires_at' => now()->addMinutes(5)]);
            $msg = "Your " . sacco_name() . " login code is $code. It expires in 5 minutes. Do not share it.";
            if ($user->phone) app(SmsService::class)->send($user->phone, $msg);
            if ($user->email && ! str_ends_with($user->email, '.local')) app(NotificationService::class)->email($user->email, 'Login code', $msg);
            $request->session()->put('2fa', ['id' => $user->id, 'remember' => $request->boolean('remember')]);
            return redirect()->route('login.otp');
        }

        return $this->complete($request, $user, $request->boolean('remember'));
    }

    public function otpForm(Request $request)
    {
        abort_unless($request->session()->has('2fa'), 403);
        return view('auth.otp');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);
        $pending = $request->session()->get('2fa');
        $user = $pending ? User::find($pending['id']) : null;
        if (! $user || ! $user->otp_code || $user->otp_expires_at?->isPast() || ! Hash::check($request->code, $user->otp_code)) {
            AuditLog::create(['user_id' => $user?->id, 'action' => 'otp_failed', 'ip' => $request->ip()]);
            return back()->withErrors(['code' => 'Invalid or expired code.']);
        }
        $user->update(['otp_code' => null, 'otp_expires_at' => null]);
        $request->session()->forget('2fa');
        return $this->complete($request, $user, $pending['remember'] ?? false);
    }

    private function complete(Request $request, User $user, bool $remember)
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);
        AuditLog::record('login', $user, ['agent' => substr((string) $request->userAgent(), 0, 150)]);
        return redirect()->intended($user->role === 'member' ? route('portal.home') : route('dashboard'));
    }

    public function logout(Request $request)
    {
        AuditLog::record('logout', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
