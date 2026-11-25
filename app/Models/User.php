<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLES = [
        'admin'        => 'Administrator',
        'manager'      => 'Manager / Credit Committee',
        'loan_officer' => 'Loan Officer',
        'teller'       => 'Teller / Cashier',
        'accountant'   => 'Accountant',
        'member'       => 'Member',
    ];

    protected $fillable = ['name', 'email', 'password', 'role', 'phone', 'is_active', 'last_login_at', 'branch_id', 'otp_code', 'otp_expires_at', 'password_changed_at'];

    protected $hidden = ['password', 'remember_token', 'otp_code'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'otp_expires_at'    => 'datetime',
            'password_changed_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function member()
    {
        return $this->hasOne(Member::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /** Head-office staff (no branch) see and manage all branches. */
    public function isHq(): bool
    {
        return $this->role === 'admin' || ! $this->branch_id;
    }

    public function hasRole(string ...$roles): bool
    {
        return $this->role === 'admin' || in_array($this->role, $roles, true);
    }

    public function isStaff(): bool
    {
        return $this->role !== 'member';
    }
}
