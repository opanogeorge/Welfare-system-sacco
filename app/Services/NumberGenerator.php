<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Setting;
use Illuminate\Support\Str;

class NumberGenerator
{
    public static function memberNo(): string
    {
        $prefix = Setting::get('member_prefix', 'MBR');
        $last = Member::withTrashed()->where('member_no', 'like', "$prefix%")
            ->orderByDesc('id')->value('member_no');
        $next = $last ? ((int) preg_replace('/\D/', '', substr($last, strlen($prefix)))) + 1 : 1;
        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public static function loanNo(): string
    {
        $year = now()->format('Y');
        $count = Loan::whereYear('created_at', $year)->count() + 1;
        do {
            $no = "LN{$year}" . str_pad((string) $count++, 5, '0', STR_PAD_LEFT);
        } while (Loan::where('loan_no', $no)->exists());
        return $no;
    }

    public static function accountNo(Member $member, string $code): string
    {
        return strtoupper($code) . '-' . $member->member_no;
    }

    public static function reference(string $prefix = 'TX'): string
    {
        return $prefix . now()->format('ymdHis') . strtoupper(Str::random(4));
    }
}
