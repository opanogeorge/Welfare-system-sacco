<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('branch', 'roleModel', 'extraRoles')->where('role', '!=', 'member')
            ->when($request->role, fn ($q, $r) => $q->where(fn ($w) => $w->where('role', $r)->orWhereHas('extraRoles', fn ($x) => $x->where('name', $r))))
            ->orderBy('name')->get();
        return view('admin.users.index', ['users' => $users, 'roles' => Role::where('name', '!=', 'member')->orderBy('label')->get()]);
    }

    public function create(Request $request)
    {
        return view('admin.users.form', ['user' => new User(['is_active' => true, 'role' => 'teller']), 'roles' => $this->assignable($request->user())]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['password'] = Hash::make($request->validate(['password' => 'required|min:8'])['password']);
        $data['password_changed_at'] = null; // must change at first login if expiry is on
        $user = User::create($data);
        $user->extraRoles()->sync($this->extraIds($request));
        Permissions::flush();
        AuditLog::record('user_created', $user, ['role' => $user->role]);
        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(Request $request, User $user)
    {
        $this->guardTarget($request->user(), $user);
        return view('admin.users.form', ['user' => $user->load('extraRoles'), 'roles' => $this->assignable($request->user())]);
    }

    public function update(Request $request, User $user)
    {
        $this->guardTarget($request->user(), $user);
        $data = $this->validated($request, $user);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->validate(['password' => 'min:8'])['password']);
        }
        $self = $user->id === $request->user()->id;
        if ($self) unset($data['role'], $data['is_active']); // can't change your own role or disable yourself
        $before = ['role' => $user->role, 'extra' => $user->extraRoles()->pluck('name')->all()];
        $user->update($data);
        if (! $self) $user->extraRoles()->sync($this->extraIds($request));
        AuditLog::record('user_updated', $user, ['before' => $before, 'role' => $user->role, 'extra' => $user->extraRoles()->pluck('name')->all()]);
        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    /** Roles this user may hand out: administrators any; others only roles within their own permissions. */
    private function assignable(User $by)
    {
        $roles = Role::with('permissions:id,name')->where('name', '!=', 'member')->orderBy('label')->get();
        if ($by->isAdmin()) return $roles;
        $mine = $by->permissionNames();
        return $roles->filter(fn ($r) => $r->name !== 'admin' && empty(array_diff($r->permissions->pluck('name')->all(), $mine)))->values();
    }

    private function guardTarget(User $by, User $target): void
    {
        abort_if($target->role === 'member', 404);
        abort_if($target->isAdmin() && ! $by->isAdmin(), 403, 'Only an administrator can edit an administrator.');
    }

    private function extraIds(Request $request): array
    {
        $allowed = $this->assignable($request->user())->pluck('id')->all();
        return array_values(array_intersect(array_map('intval', (array) $request->input('extra_roles', [])), $allowed));
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user)],
            'phone' => 'nullable|string|max:20',
            'role' => ['required', Rule::in($this->assignable($request->user())->pluck('name')->all())],
            'branch_id' => 'nullable|exists:branches,id',
            'extra_roles' => 'nullable|array',
        ], ['role.in' => 'You can only assign roles whose permissions you hold yourself.']);
        unset($data['extra_roles']);
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }
}
