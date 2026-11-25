<?php

namespace App\Http\Controllers;

use App\Models\CommitteeMember;
use App\Models\Meeting;
use App\Models\Member;
use App\Models\Resolution;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class GovernanceController extends Controller
{
    public const COMMITTEES = ['board' => 'Board of Directors', 'supervisory' => 'Supervisory Committee', 'credit' => 'Credit Committee',
        'audit' => 'Audit & Risk Committee', 'education' => 'Education Committee'];

    public function index()
    {
        $committees = CommitteeMember::with('member')->where(fn ($q) => $q->whereNull('to_date')->orWhere('to_date', '>=', today()))
            ->orderBy('committee')->get()->groupBy('committee');
        $meetings = Meeting::withCount('resolutions')->latest('scheduled_at')->paginate(20);
        return view('governance.index', ['committees' => $committees, 'meetings' => $meetings, 'names' => self::COMMITTEES]);
    }

    public function storeCommitteeMember(Request $r)
    {
        CommitteeMember::create($r->validate(['member_id' => 'required|exists:members,id', 'committee' => 'required|in:' . implode(',', array_keys(self::COMMITTEES)),
            'position' => 'required|string|max:60', 'from_date' => 'required|date', 'to_date' => 'nullable|date|after:from_date']));
        return back()->with('success', 'Committee member added.');
    }

    public function endTerm(CommitteeMember $cm)
    {
        $cm->update(['to_date' => today()]);
        return back()->with('success', 'Term ended.');
    }

    public function storeMeeting(Request $r, NotificationService $notify)
    {
        $d = $r->validate(['type' => 'required|in:board,committee,agm,sgm', 'title' => 'required|string|max:150',
            'scheduled_at' => 'required|date', 'venue' => 'nullable|string|max:150', 'agenda' => 'nullable|string']);
        $m = Meeting::create($d + ['created_by' => auth()->id()]);

        if ($r->boolean('notify')) {
            $text = "{$m->title}: " . $m->scheduled_at->format('D d M Y, g:ia') . ($m->venue ? " at {$m->venue}" : '') . '. ' . config('sacco.name');
            $audience = in_array($m->type, ['agm', 'sgm'])
                ? Member::whereIn('status', ['active', 'dormant'])->cursor()
                : Member::whereIn('id', CommitteeMember::whereNull('to_date')->pluck('member_id'))->get();
            $notify->bulk($audience, "Dear {first_name}, you are invited to the $text", ['sms', 'email'], $m->title);
        }
        return redirect()->route('governance.meetings.show', $m)->with('success', 'Meeting scheduled.');
    }

    public function showMeeting(Meeting $meeting)
    {
        $meeting->load('resolutions', 'creator');
        return view('governance.meeting', compact('meeting'));
    }

    public function updateMeeting(Request $r, Meeting $meeting)
    {
        $meeting->update($r->validate(['status' => 'required|in:scheduled,held,cancelled', 'minutes' => 'nullable|string',
            'attendance' => 'nullable|integer|min:0', 'agenda' => 'nullable|string']));
        return back()->with('success', 'Meeting updated.');
    }

    public function storeResolution(Request $r, Meeting $meeting)
    {
        $meeting->resolutions()->create($r->validate(['number' => 'required|string|max:30', 'title' => 'required|string|max:200',
            'details' => 'nullable|string', 'status' => 'required|in:passed,rejected,deferred,implemented']));
        return back()->with('success', 'Resolution recorded.');
    }

    public function updateResolution(Request $r, Resolution $resolution)
    {
        $resolution->update($r->validate(['status' => 'required|in:passed,rejected,deferred,implemented']));
        return back()->with('success', 'Resolution updated.');
    }
}
