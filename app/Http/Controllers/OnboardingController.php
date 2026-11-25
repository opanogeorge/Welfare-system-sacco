<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employer;
use App\Models\Member;
use App\Models\User;
use App\Services\AccountService;
use App\Services\IdentityVerificationService;
use App\Services\NumberGenerator;
use App\Services\SignatureService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

/**
 * Public digital onboarding (/join): applicant fills in details, verifies their phone by OTP,
 * uploads ID + takes a live selfie, names a nominee, accepts the by-laws and data consent,
 * and signs electronically. The application lands as "pending" for staff KYC and approval.
 */
class OnboardingController extends Controller
{
    public function form()
    {
        abort_unless(setting('online_onboarding', 1), 404);
        return view('onboarding.form', ['employers' => Employer::where('is_active', true)->orderBy('name')->get()]);
    }

    public function sendOtp(Request $request, SmsService $sms)
    {
        $request->validate(['phone' => 'required|string|min:9|max:15']);
        $phone = $sms->normalize($request->phone);
        $key = 'join-otp:' . $phone . ':' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['message' => 'Too many codes requested. Try again in ' . ceil(RateLimiter::availableIn($key) / 60) . ' minutes.'], 429);
        }
        RateLimiter::hit($key, 600);
        $code = (string) random_int(100000, 999999);
        Cache::put("join_otp_$phone", Hash::make($code), now()->addMinutes(10));
        $sms->send($phone, "Your " . sacco_name() . " membership application code is $code. It expires in 10 minutes.");
        return response()->json(['message' => 'Code sent by SMS to ' . substr($phone, 0, 6) . '****' . substr($phone, -2) . '.']);
    }

    public function submit(Request $request, AccountService $accounts, SignatureService $signatures, SmsService $sms)
    {
        abort_if($request->filled('website'), 422); // honeypot
        $d = $request->validate([
            'first_name' => 'required|string|max:60', 'middle_name' => 'nullable|string|max:60', 'last_name' => 'required|string|max:60',
            'national_id' => 'required|string|max:20|unique:members,national_id', 'kra_pin' => 'nullable|string|max:20',
            'date_of_birth' => 'required|date|before:-18 years', 'gender' => 'required|in:male,female,other',
            'phone' => 'required|string|max:20', 'otp' => 'required|digits:6', 'email' => 'nullable|email|max:120',
            'address' => 'nullable|string|max:255', 'occupation' => 'nullable|string|max:120', 'source_of_funds' => 'nullable|string|max:120',
            'employer_id' => 'nullable|exists:employers,id', 'employer' => 'nullable|string|max:120', 'payroll_no' => 'nullable|string|max:30',
            'monthly_contribution' => 'required|numeric|min:' . (float) setting('min_monthly_contribution', 500),
            'nominee_name' => 'required|string|max:150', 'nominee_relationship' => 'required|string|max:50', 'nominee_phone' => 'nullable|string|max:20', 'nominee_id' => 'nullable|string|max:20',
            'id_front' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', 'id_back' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'payslip' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', 'selfie' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'accept_bylaws' => 'accepted', 'accept_data' => 'accepted', 'accept_crb' => 'nullable|boolean',
            'signature' => 'required|string',
        ], ['selfie.required' => 'Please take a live selfie.', 'signature.required' => 'Please sign the application.']);

        $phone = $sms->normalize($d['phone']);
        $hash = Cache::get("join_otp_$phone");
        if (! $hash || ! Hash::check($d['otp'], $hash)) {
            return back()->withInput($request->except(['password', 'password_confirmation', 'selfie', 'signature']))->withErrors(['otp' => 'The phone verification code is wrong or has expired.']);
        }
        if (! preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#', $d['selfie'], $sm)) {
            return back()->withInput($request->except(['password', 'password_confirmation', 'selfie', 'signature']))->withErrors(['selfie' => 'Please take the selfie again.']);
        }
        if (User::where('phone', $phone)->orWhere('phone', $d['phone'])->exists()) {
            return back()->withErrors(['phone' => 'This phone number is already registered. Please visit the office or sign in.']);
        }

        $member = DB::transaction(function () use ($d, $request, $phone, $sm, $accounts, $signatures) {
            $employer = isset($d['employer_id']) ? Employer::find($d['employer_id']) : null;
            $member = Member::create([
                'member_no' => NumberGenerator::memberNo(), 'first_name' => $d['first_name'], 'middle_name' => $d['middle_name'] ?? null,
                'last_name' => $d['last_name'], 'national_id' => $d['national_id'], 'kra_pin' => $d['kra_pin'] ?? null,
                'date_of_birth' => $d['date_of_birth'], 'gender' => $d['gender'], 'phone' => $phone, 'email' => $d['email'] ?? null,
                'address' => $d['address'] ?? null, 'occupation' => $d['occupation'] ?? null, 'source_of_funds' => $d['source_of_funds'] ?? null,
                'employer_id' => $employer?->id, 'employer' => $employer?->name ?? ($d['employer'] ?? null), 'payroll_no' => $d['payroll_no'] ?? null,
                'monthly_contribution' => $d['monthly_contribution'], 'joined_on' => today(), 'status' => 'pending',
                'onboarding_channel' => 'online', 'data_consent_at' => now(), 'crb_consent' => (bool) ($d['accept_crb'] ?? false),
            ]);
            $accounts->openDefaultAccounts($member);
            $member->nominees()->create(['name' => $d['nominee_name'], 'relationship' => $d['nominee_relationship'],
                'phone' => $d['nominee_phone'] ?? null, 'national_id' => $d['nominee_id'] ?? null, 'percentage' => 100]);

            $dir = "members/{$member->id}";
            foreach (['id_front' => ['national_id', 'National ID (front)'], 'id_back' => ['national_id_back', 'National ID (back)'], 'payslip' => ['payslip', 'Payslip']] as $f => [$type, $title]) {
                if ($request->hasFile($f)) $member->documents()->create(['type' => $type, 'title' => $title, 'path' => $request->file($f)->store($dir, 'local')]);
            }
            $selfiePath = "$dir/selfie-" . now()->format('YmdHis') . '.jpg';
            Storage::disk('local')->put($selfiePath, base64_decode($sm[1]));
            $member->documents()->create(['type' => 'selfie', 'title' => 'Live selfie (online application)', 'path' => $selfiePath]);
            $member->update(['photo' => $selfiePath]);

            $user = User::create(['name' => $member->full_name, 'email' => $member->email && ! User::where('email', $member->email)->exists() ? $member->email : strtolower($member->member_no) . '@members.local',
                'phone' => $phone, 'role' => 'member', 'is_active' => false, 'password' => Hash::make($d['password']), 'password_changed_at' => now()]);
            $member->update(['user_id' => $user->id]);

            $signatures->sign($member, 'membership_application', $d['signature'], $member->full_name, 'applicant', $member, true);
            AuditLog::record('online_application', $member, ['ip' => $request->ip()]);
            return $member;
        });
        Cache::forget("join_otp_$phone");

        if (setting('auto_iprs_check', 1) && app(IdentityVerificationService::class)->configured()) {
            try { app(IdentityVerificationService::class)->verifyIprs($member); } catch (\Throwable $e) { report($e); }
        }
        $sms->send($phone, "Dear {$member->first_name}, your application to " . sacco_name() . " has been received. Ref {$member->member_no}. "
            . (setting('registration_fee') ? 'Pay the registration fee of KES ' . number_format((float) setting('registration_fee')) . ' to Paybill ' . config('sacco.mpesa.paybill') . ", account {$member->member_no}. " : '')
            . 'We will SMS you once approved.', $member->id);

        return redirect()->route('join.done')->with('application', ['member_no' => $member->member_no, 'name' => $member->first_name]);
    }

    public function done()
    {
        abort_unless(session('application'), 404);
        return view('onboarding.done', ['app' => session('application')]);
    }
}
