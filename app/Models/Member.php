<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use SoftDeletes, BelongsToBranch;

    protected $guarded = ['id'];

    protected $casts = [
        'date_of_birth' => 'date',
        'joined_on'     => 'date',
        'exited_on'     => 'date',
        'approved_at'   => 'datetime',
        'data_consent_at' => 'datetime',
        'guarantor_registered_at' => 'datetime',
        'max_guarantee_amount' => 'decimal:2',
        'is_pep'        => 'boolean',
        'monthly_contribution' => 'decimal:2',
    ];

    public function category()      { return $this->belongsTo(MemberCategory::class, 'member_category_id'); }
    public function employerRecord(){ return $this->belongsTo(Employer::class, 'employer_id'); }
    public function documents()     { return $this->hasMany(MemberDocument::class); }
    public function fixedDeposits() { return $this->hasMany(FixedDeposit::class); }
    public function standingOrders(){ return $this->hasMany(StandingOrder::class); }
    public function withdrawalRequests() { return $this->hasMany(WithdrawalRequest::class); }
    public function tickets()       { return $this->hasMany(Ticket::class); }
    public function dividendPayouts(){ return $this->hasMany(DividendPayout::class); }
    public function identityVerifications() { return $this->hasMany(IdentityVerification::class); }
    public function crbChecks()     { return $this->hasMany(CrbCheck::class); }
    public function signatures()    { return $this->hasMany(Signature::class); }
    public function fraudAlerts()   { return $this->hasMany(FraudAlert::class); }

    public function user()        { return $this->belongsTo(User::class); }
    public function nominees()    { return $this->hasMany(MemberNominee::class); }
    public function accounts()    { return $this->hasMany(MemberAccount::class); }
    public function transactions(){ return $this->hasMany(Transaction::class); }
    public function loans()       { return $this->hasMany(Loan::class); }
    public function guarantees()  { return $this->hasMany(LoanGuarantor::class); }

    public function setMonthlyContributionAttribute($value): void
    {
        $this->attributes['monthly_contribution'] = ($value === null || $value === '') ? 0 : $value;
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function account(string $code): ?MemberAccount
    {
        return $this->accounts->first(fn ($a) => $a->type->code === $code)
            ?? $this->accounts()->whereHas('type', fn ($q) => $q->where('code', $code))->first();
    }

    public function balance(string $code): float
    {
        return (float) ($this->account($code)?->balance ?? 0);
    }

    /** Sum of balances in accounts that count towards loan eligibility (usually deposits/BOSA). */
    public function loanableDeposits(): float
    {
        return (float) $this->accounts()
            ->whereHas('type', fn ($q) => $q->where('counts_for_loan', true))
            ->sum('balance');
    }

    /**
     * Current guarantee exposure. On running loans the exposure falls in proportion to the
     * principal repaid (amount x principal_balance / amount_approved); before disbursement it is the full amount.
     */
    public function committedGuarantees(): float
    {
        return round((float) $this->guarantees()
            ->where('status', 'accepted')
            ->with('loan')
            ->whereHas('loan', fn ($q) => $q->whereIn('status', ['approved', 'disbursed', 'appraised', 'guarantors_pending', 'pending']))
            ->get()
            ->sum(fn ($g) => $g->exposure()), 2);
    }

    /** Most this member may guarantee in total (Settings → guarantor rules, or a per-member limit). */
    public function guaranteeLimit(): float
    {
        return app(\App\Services\GuarantorService::class)->limit($this);
    }

    public function freeGuaranteeCapacity(): float
    {
        return max(0, round($this->guaranteeLimit() - $this->committedGuarantees(), 2));
    }

    public function outstandingLoans(): float
    {
        return (float) $this->loans()->where('status', 'disbursed')
            ->selectRaw('COALESCE(SUM(principal_balance + interest_balance + penalty_balance),0) as t')
            ->value('t');
    }

    public function monthsOfMembership(): int
    {
        return (int) $this->joined_on->diffInMonths(now());
    }

    public function scopeActive($q) { return $q->where('status', 'active'); }

    public function scopeSearch($q, ?string $term)
    {
        if (! $term) return $q;
        return $q->where(function ($q) use ($term) {
            $q->where('member_no', 'like', "%$term%")
              ->orWhere('first_name', 'like', "%$term%")
              ->orWhere('last_name', 'like', "%$term%")
              ->orWhere('national_id', 'like', "%$term%")
              ->orWhere('phone', 'like', "%$term%");
        });
    }
}
