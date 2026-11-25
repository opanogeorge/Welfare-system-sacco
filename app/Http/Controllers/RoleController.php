<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    /** Permission matrix: every role (column) × every permission (row). */
    public function index()
    {
        Permissions::sync(); // picks up permissions added in config/permissions.php
        $roles = Role::with('permissions:id,name')->withCount('users', 'extraUsers')->where('name', '!=', 'member')
            ->orderByRaw("FIELD(name, 'admin', 'manager', 'loan_officer', 'accountant', 'teller') = 0, FIELD(name, 'admin', 'manager', 'loan_officer', 'accountant', 'teller')")->orderBy('label')->get();
        return view('admin.roles.index', ['roles' => $roles, 'catalogue' => Permissions::catalogue(), 'mine' => auth()->user()->permissionNames()]);
    }

    public function store(Request $request)
    {
        $d = $request->validate(['label' => 'required|string|max:80', 'description' => 'nullable|string|max:255', 'copy_from' => 'nullable|exists:roles,id']);
        $name = Str::slug($d['label'], '_');
        if (Role::where('name', $name)->exists()) $name .= '_' . Str::lower(Str::random(4));
        $role = Role::create(['name' => $name, 'label' => $d['label'], 'description' => $d['description'] ?? null, 'is_system' => false]);
        if (! empty($d['copy_from'])) {
            $ids = Role::find($d['copy_from'])->permissions()->pluck('permissions.id')->all();
            $role->permissions()->sync($this->allowedIds($request->user(), $ids));
        }
        Permissions::flush();
        AuditLog::record('role_created', $role, ['copy_from' => $d['copy_from'] ?? null]);
        return redirect()->route('admin.roles.edit', $role)->with('success', "Role “{$role->label}” created. Tick what it can do, then save.");
    }

    public function edit(Role $role)
    {
        abort_if($role->name === 'member', 404);
        $role->load('permissions:id,name');
        $users = User::where('role', $role->name)->orWhereHas('extraRoles', fn ($q) => $q->where('roles.id', $role->id))->orderBy('name')->get();
        return view('admin.roles.edit', ['role' => $role, 'catalogue' => Permissions::catalogue(), 'users' => $users,
            'has' => $role->permissions->pluck('name')->all(), 'mine' => auth()->user()->permissionNames()]);
    }

    public function update(Request $request, Role $role)
    {
        abort_if($role->isLocked(), 403, 'The Administrator and Member roles cannot be changed.');
        $d = $request->validate(['label' => 'required|string|max:80', 'description' => 'nullable|string|max:255', 'permissions' => 'nullable|array', 'permissions.*' => 'string']);
        $role->update(['label' => $d['label'], 'description' => $d['description'] ?? null]);
        $this->savePermissions($request->user(), $role, $d['permissions'] ?? []);
        return redirect()->route('admin.roles.edit', $role)->with('success', 'Role saved. Users with this role get the changes on their next page load.');
    }

    /** Save the whole matrix at once. */
    public function matrix(Request $request)
    {
        $grid = (array) $request->input('grid', []); // grid[role_id][] = permission name
        foreach (Role::whereNotIn('name', ['admin', 'member'])->get() as $role) {
            $this->savePermissions($request->user(), $role, (array) ($grid[$role->id] ?? []));
        }
        return back()->with('success', 'Permissions saved.');
    }

    public function destroy(Role $role)
    {
        abort_if($role->is_system, 403, 'Built-in roles cannot be deleted. You can remove their permissions instead.');
        if (User::where('role', $role->name)->exists()) return back()->with('error', 'Move the users with this main role to another role first.');
        $role->extraUsers()->detach();
        $role->delete();
        Permissions::flush();
        AuditLog::record('role_deleted', null, ['role' => $role->name]);
        return redirect()->route('admin.roles.index')->with('success', 'Role deleted.');
    }

    /**
     * Non-administrators can only grant permissions they hold themselves, and never take away
     * permissions they don't hold (those stay as they were).
     */
    private function savePermissions(User $by, Role $role, array $names): void
    {
        $valid = Permission::whereIn('name', $names)->pluck('id', 'name')->all();
        $current = $role->permissions()->pluck('permissions.id', 'permissions.name')->all();
        if (! $by->isAdmin()) {
            $mine = array_flip($by->permissionNames());
            $valid = array_intersect_key($valid, $mine);                    // can't add what you don't have
            $valid += array_diff_key($current, $mine);                      // can't remove what you don't have
        }
        $before = array_keys($current);
        $role->permissions()->sync(array_values($valid));
        Permissions::flush();
        $after = array_keys($valid);
        if (array_diff($before, $after) || array_diff($after, $before)) {
            AuditLog::record('role_permissions_changed', $role, ['added' => array_values(array_diff($after, $before)), 'removed' => array_values(array_diff($before, $after))]);
        }
    }

    private function allowedIds(User $by, array $ids): array
    {
        if ($by->isAdmin()) return $ids;
        return Permission::whereIn('id', $ids)->whereIn('name', $by->permissionNames())->pluck('id')->all();
    }
}
