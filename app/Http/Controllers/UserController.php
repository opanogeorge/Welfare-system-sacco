<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('branch')->where('role', '!=', 'member')->orderBy('name')->get();
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(['is_active' => true, 'role' => 'teller'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['password'] = Hash::make($request->validate(['password' => 'required|min:8'])['password']);
        User::create($data);
        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->validate(['password' => 'min:8'])['password']);
        }
        if ($user->id === $request->user()->id) {
            unset($data['role'], $data['is_active']); // can't demote/disable yourself
        }
        $user->update($data);
        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user)],
            'phone' => 'nullable|string|max:20',
            'role' => ['required', Rule::in(array_keys(array_diff_key(User::ROLES, ['member' => 1])))],
            'branch_id' => 'nullable|exists:branches,id',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }
}
