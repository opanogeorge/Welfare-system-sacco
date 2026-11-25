<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WelfareEvent extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['event_date' => 'date', 'closes_on' => 'date', 'paid_on' => 'date', 'approved_at' => 'datetime'];

    public const TYPES = [
        'bereavement' => 'Bereavement', 'wedding' => 'Wedding', 'birth' => 'New baby', 'sickness' => 'Sickness / hospital',
        'graduation' => 'Graduation', 'retirement' => 'Retirement / send-off', 'disaster' => 'Fire / disaster', 'other' => 'Other',
    ];
    public const RELATIONSHIPS = ['self' => 'Member', 'spouse' => 'Spouse', 'child' => 'Child', 'parent' => 'Parent', 'parent_in_law' => 'Parent-in-law', 'sibling' => 'Sibling', 'other' => 'Other'];
    public const STATUSES = ['requested' => 'Requested by member', 'draft' => 'Draft', 'open' => 'Open for contributions', 'closed' => 'Closed', 'paid' => 'Paid out', 'rejected' => 'Rejected'];

    public function member() { return $this->belongsTo(Member::class); }
    public function contributions() { return $this->hasMany(WelfareContribution::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }

    public function getTypeLabelAttribute(): string { return self::TYPES[$this->type] ?? ucfirst($this->type); }
    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status] ?? ucfirst($this->status); }

    public function badge(): string
    {
        return ['requested' => 'warning', 'draft' => 'secondary', 'open' => 'success', 'closed' => 'info', 'paid' => 'primary', 'rejected' => 'danger'][$this->status] ?? 'secondary';
    }

    public function icon(): string
    {
        return ['bereavement' => 'flower1', 'wedding' => 'heart', 'birth' => 'balloon-heart', 'sickness' => 'hospital', 'graduation' => 'mortarboard', 'retirement' => 'gift', 'disaster' => 'fire'][$this->type] ?? 'hand-thumbs-up';
    }

    public function dueAmount(): float
    {
        return (float) $this->contributions()->where('status', 'due')->sum('amount');
    }

    /** What the beneficiary should receive: everything raised plus the fund's benefit, less what's been paid. */
    public function payable(): float
    {
        return max(0, round((float) $this->raised + (float) $this->fund_contribution - (float) $this->paid_out, 2));
    }
}
