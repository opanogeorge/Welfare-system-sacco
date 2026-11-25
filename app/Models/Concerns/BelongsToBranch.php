<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;

/**
 * Stamps branch_id on create (from the member, else the logged-in user's branch) and gives
 * a ->visibleTo($user) scope so branch staff see only their branch in lists and reports.
 * Members can still transact at any branch - lookups are not restricted.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::creating(function ($model) {
            if ($model->branch_id) return;
            if ($model->getAttribute('member_id') && method_exists($model, 'member')) {
                $model->branch_id = \App\Models\Member::whereKey($model->member_id)->value('branch_id');
            }
            if (! $model->branch_id) {
                $model->branch_id = auth()->user()?->branch_id;
            }
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /** Branch staff: own branch only. HQ staff (no branch): everything, optionally filtered by ?branch=. */
    public function scopeVisibleTo(Builder $q, $user, $branchFilter = null): Builder
    {
        $col = $this->getTable() . '.branch_id';
        if ($user && $user->branch_id && ! $user->hasRole('admin')) return $q->where($col, $user->branch_id);
        return $branchFilter ? $q->where($col, $branchFilter) : $q;
    }
}
