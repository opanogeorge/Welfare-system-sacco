<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class ApiClient extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['key_hash', 'webhook_secret'];

    protected $casts = ['scopes' => 'array', 'events' => 'array', 'is_active' => 'boolean', 'last_used_at' => 'datetime'];

    public const SCOPES = [
        'members.read'      => 'Look up / validate a member (name, status)',
        'balances.read'     => 'Read member account and loan balances',
        'loans.read'        => 'Read loan status and next installment',
        'payments.write'    => 'Post payments (bank / agent collections)',
        'members.write'     => 'Submit new member applications (HR / onboarding systems)',
        'transactions.read' => 'Export transactions (data warehouse / BI)',
    ];

    public const EVENTS = [
        'transaction.posted' => 'Any deposit, withdrawal, fee or transfer',
        'loan.disbursed'     => 'Loan paid out',
        'loan.repaid'        => 'Loan repayment received',
        'member.created'     => 'New member registered',
        'member.approved'    => 'Membership approved',
    ];

    public function logs()       { return $this->hasMany(ApiLog::class); }
    public function deliveries() { return $this->hasMany(WebhookDelivery::class); }

    /** Create a key; the plain key is returned once and only its hash is stored. */
    public static function generateKey(): array
    {
        $plain = 'sk_' . Str::random(40);
        return ['plain' => $plain, 'prefix' => substr($plain, 0, 10), 'hash' => hash('sha256', $plain)];
    }

    public static function findByKey(?string $plain): ?self
    {
        if (! $plain || ! str_starts_with($plain, 'sk_')) return null;
        return static::where('key_hash', hash('sha256', $plain))->where('is_active', true)->first();
    }

    public function can(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    public function secret(): ?string
    {
        try { return $this->webhook_secret ? Crypt::decryptString($this->webhook_secret) : null; } catch (\Throwable) { return null; }
    }

    public function ipAllowed(string $ip): bool
    {
        $list = array_filter(array_map('trim', explode(',', (string) $this->allowed_ips)));
        return ! $list || \Symfony\Component\HttpFoundation\IpUtils::checkIp($ip, $list);
    }
}
