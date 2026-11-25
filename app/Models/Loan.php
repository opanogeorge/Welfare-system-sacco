<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use BelongsToBranch;

    protected $guarded = ['id'];

    public const STATUSES = [
        'pending'            => 'Pending',
        'guarantors_pending' => 'Awaiting Guarantors',
        'appraised'          => 'Appraised',
        'approved'           => 'Approved',
        'rejected'           => 'Rejected',
        'disbursed'          => 'Running',
        'closed'             => 'Cleared',
        'written_off'        => 'Written Off',
    ];

    protected $casts = [
        'applied_on'    => 'date',
        'disbursed_on'  => 'date',
        'first_due_date'=> 'date',
        'maturity_date' => 'date',
        'closed_on'     => 'date',
        'appraised_at'  => 'datetime',
        'approved_at'   => 'datetime',
        'rescheduled_at'=> 'datetime',
        'risk_features' => 'array',
        'written_off_on'=> 'date',
        'is_refinance'  => 'boolean',
        'pd'            => 'float',
    ];

    public function member()     { return $this->belongsTo(Member::class); }
    public function product()    { return $this->belongsTo(LoanProduct::class, 'loan_product_id'); }
    public function guarantors() { return $this->hasMany(LoanGuarantor::class); }
    public function schedules()  { return $this->hasMany(LoanSchedule::class)->orderBy('installment_no'); }
    public function repayments() { return $this->hasMany(LoanRepayment::class)->latest('paid_on'); }
    public function approver()   { return $this->belongsTo(User::class, 'approved_by'); }
    public function approvals()  { return $this->hasMany(LoanApproval::class)->orderBy('level'); }
    public function collaterals(){ return $this->hasMany(LoanCollateral::class); }
    public function topUpOf()    { return $this->belongsTo(Loan::class, 'top_up_of'); }
    public function charges()    { return $this->hasMany(LoanCharge::class)->latest(); }
    public function recoveryActions() { return $this->hasMany(LoanRecoveryAction::class)->latest(); }

    public const METHODS = [
        'reducing'        => 'Reducing balance – equal installments (amortised)',
        'equal_principal' => 'Reducing balance – equal principal (installments fall over time)',
        'flat'            => 'Flat rate – interest on the original amount',
    ];

    public function creditGrade(): ?string
    {
        if ($this->credit_score === null) return null;
        return match (true) {
            $this->credit_score >= 80 => 'A', $this->credit_score >= 65 => 'B',
            $this->credit_score >= 50 => 'C', $this->credit_score >= 35 => 'D', default => 'E',
        };
    }

    public function getOutstandingAttribute(): float
    {
        return round((float) $this->principal_balance + (float) $this->interest_balance + (float) $this->penalty_balance, 2);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function guaranteedAmount(): float
    {
        return (float) $this->guarantors->where('status', 'accepted')->sum('amount');
    }

    /** Amount in arrears = due-to-date not yet paid. */
    public function arrears(): float
    {
        return (float) $this->schedules()
            ->where('due_date', '<', today())
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->get()
            ->sum(fn ($s) => $s->balance());
    }

    public function daysInArrears(): int
    {
        $oldest = $this->schedules()
            ->where('due_date', '<', today())
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->min('due_date');

        return $oldest ? (int) \Carbon\Carbon::parse($oldest)->diffInDays(today()) : 0;
    }

    /** SASRA prudential classification by days in arrears. */
    public function classification(): string
    {
        $d = $this->daysInArrears();
        return match (true) {
            $d === 0  => 'Performing',
            $d <= 30  => 'Watch',
            $d <= 180 => 'Substandard',
            $d <= 360 => 'Doubtful',
            default   => 'Loss',
        };
    }

    public function badge(): string
    {
        return match ($this->status) {
            'disbursed' => 'primary', 'closed' => 'success', 'approved' => 'info',
            'rejected', 'written_off' => 'danger', default => 'warning',
        };
    }
}
