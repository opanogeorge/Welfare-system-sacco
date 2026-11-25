<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanGuarantor extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['responded_at' => 'datetime', 'expires_at' => 'datetime', 'released_at' => 'datetime', 'release_requested_at' => 'datetime'];

    public const STATUSES = ['pending' => 'Awaiting consent', 'accepted' => 'Active', 'declined' => 'Declined', 'expired' => 'Request expired',
        'released' => 'Released', 'withdrawn' => 'Withdrawn'];

    public function loan()   { return $this->belongsTo(Loan::class); }
    public function member() { return $this->belongsTo(Member::class); }
    public function releaser() { return $this->belongsTo(User::class, 'released_by'); }
    public function replacement() { return $this->belongsTo(self::class, 'replaced_by_id'); }

    public function badge(): string
    {
        return ['accepted' => 'success', 'pending' => 'warning', 'declined' => 'danger', 'expired' => 'secondary', 'released' => 'info', 'withdrawn' => 'secondary'][$this->status] ?? 'secondary';
    }

    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status] ?? ucfirst($this->status); }

    /** Share of the original guarantee already released by the borrower's repayments (0-100). */
    public function reducedPercent(): float
    {
        return (float) $this->amount > 0 ? round(100 - $this->exposure() / (float) $this->amount * 100, 1) : 0;
    }

    /** Exposure reduces automatically as the borrower repays principal. */
    public function exposure(): float
    {
        if ($this->status !== 'accepted') return 0;
        $loan = $this->loan;
        if ($loan->status !== 'disbursed' || ! (float) $loan->amount_approved) {
            return in_array($loan->status, ['closed', 'rejected', 'written_off']) ? 0 : (float) $this->amount;
        }
        return round((float) $this->amount * min(1, (float) $loan->principal_balance / (float) $loan->amount_approved), 2);
    }
}
