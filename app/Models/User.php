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

    /** Extra roles held on top of the main role (users.role). */
    public function extraRoles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function roleModel()
    {
        return $this->belongsTo(Role::class, 'role', 'name');
    }

    /** All role names this user holds (main + extra). */
    public function roleNames(): array
    {
        if ($this->rolesCache === null) {
            $extra = [];
            try { $extra = $this->relationLoaded('extraRoles') ? $this->extraRoles->pluck('name')->all() : $this->extraRoles()->pluck('name')->all(); }
            catch (\Throwable $e) { /* role tables not migrated yet */ }
            $this->rolesCache = array_values(array_unique(array_merge([$this->role], $extra)));
        }
        return $this->rolesCache;
    }
    private ?array $rolesCache = null;
    private ?array $permsCache = null;

    public function getRoleLabelAttribute(): string
    {
        try { return $this->roleModel?->label ?? (self::ROLES[$this->role] ?? ucfirst(str_replace('_', ' ', (string) $this->role))); }
        catch (\Throwable $e) { return self::ROLES[$this->role] ?? (string) $this->role; }
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Role check (main or extra role). Administrator passes every check. Use hasPermission() for feature access. */
    public function hasRole(string ...$roles): bool
    {
        return $this->isAdmin() || (bool) array_intersect($this->roleNames(), $roles);
    }

    /** @return string[] every permission from all the user's roles */
    public function permissionNames(): array
    {
        if ($this->permsCache === null) {
            $map = \App\Support\Permissions::map();
            $this->permsCache = array_values(array_unique(array_merge(...array_map(fn ($r) => $map[$r] ?? [], $this->roleNames()))));
        }
        return $this->permsCache;
    }

    /** True if the user has ANY of the given permissions. Administrator has all. */
    public function hasPermission(string ...$perms): bool
    {
        if ($this->isAdmin()) return true;
        if (! $this->is_active || $this->role === 'member') return false;
        return (bool) array_intersect($perms, $this->permissionNames());
    }

    /** Can this user open the given route (per the route → permission map)? */
    public function canRoute(string $route): bool
    {
        if (! $this->isStaff() && ! str_starts_with($route, 'portal.')) return false;
        $p = \App\Support\Permissions::forRoute($route);
        return $p === null || $this->hasPermission($p);
    }

    public function isStaff(): bool
    {
        return $this->role !== 'member';
    }
}
