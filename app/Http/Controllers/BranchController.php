<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::withCount(['members' => fn ($q) => $q->where('status', 'active'), 'users'])->orderBy('name')->get();
        return view('admin.branches', compact('branches'));
    }

    public function store(Request $r)
    {
        Branch::create($this->validated($r));
        return back()->with('success', 'Branch added.');
    }

    public function update(Request $r, Branch $branch)
    {
        $branch->update($this->validated($r, $branch));
        return back()->with('success', 'Branch updated.');
    }

    /** Move a member (and their future transactions) to another branch. */
    public function transferMember(Request $r, Member $member)
    {
        $r->validate(['branch_id' => 'required|exists:branches,id']);
        $old = $member->branch_id;
        $member->update(['branch_id' => $r->branch_id]);
        $member->loans()->where('status', 'disbursed')->update(['branch_id' => $r->branch_id]);
        AuditLog::record('member_branch_transfer', $member, ['from' => $old, 'to' => $r->branch_id]);
        return back()->with('success', 'Member moved to ' . Branch::find($r->branch_id)->name . '.');
    }

    private function validated(Request $r, ?Branch $b = null): array
    {
        $d = $r->validate(['code' => ['required', 'string', 'max:10', Rule::unique('branches')->ignore($b)], 'name' => 'required|string|max:100',
            'location' => 'nullable|string|max:150', 'phone' => 'nullable|string|max:20']);
        $d['is_hq'] = $r->boolean('is_hq');
        $d['is_active'] = $r->boolean('is_active', true);
        return $d;
    }
}
