<?php

namespace App\Http\Controllers;

use App\Models\Employer;
use App\Models\Loan;
use App\Models\Member;
use App\Models\MemberCategory;
use App\Models\MessageLog;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class MessagingController extends Controller
{
    public function index(Request $r)
    {
        $logs = MessageLog::with('member', 'sender')->when($r->batch, fn ($q, $b) => $q->where('batch', $b))
            ->when($r->channel, fn ($q, $c) => $q->where('channel', $c))->latest()->paginate(40)->withQueryString();
        return view('messaging.index', [
            'logs' => $logs,
            'employers' => Employer::orderBy('name')->get(),
            'categories' => MemberCategory::orderBy('name')->get(),
        ]);
    }

    public function send(Request $r, NotificationService $notify)
    {
        $d = $r->validate([
            'audience' => 'required|in:all,arrears,employer,category,board,member',
            'employer_id' => 'nullable|required_if:audience,employer|exists:employers,id',
            'category_id' => 'nullable|required_if:audience,category|exists:member_categories,id',
            'member_id' => 'nullable|required_if:audience,member|exists:members,id',
            'channels' => 'required|array|min:1', 'channels.*' => 'in:sms,email,whatsapp',
            'subject' => 'nullable|string|max:150', 'message' => 'required|string|max:1000',
        ]);

        $q = Member::query()->whereIn('status', ['active', 'dormant']);
        match ($d['audience']) {
            'arrears'  => $q->whereIn('id', Loan::where('status', 'disbursed')->get()->filter(fn ($l) => $l->daysInArrears() > 0)->pluck('member_id')),
            'employer' => $q->where('employer_id', $d['employer_id']),
            'category' => $q->where('member_category_id', $d['category_id']),
            'board'    => $q->whereIn('id', \App\Models\CommitteeMember::whereNull('to_date')->orWhere('to_date', '>=', today())->pluck('member_id')),
            'member'   => $q->where('id', $d['member_id']),
            default    => null,
        };

        $res = $notify->bulk($q->cursor(), $d['message'], $d['channels'], $d['subject'] ?? null);
        return redirect()->route('messaging.index', ['batch' => $res['batch']])->with('success', "Message queued to {$res['count']} member(s). Batch {$res['batch']}.");
    }
}
