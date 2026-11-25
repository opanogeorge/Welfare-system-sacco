<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\MemberChangeRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\AccountService;
use App\Services\NotificationService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use RuntimeException;

/** Staff side of member self-service: withdrawal requests, profile-change requests, complaints & inquiries. */
class ServiceDeskController extends Controller
{
    public function __construct(private SmsService $sms) {}

    public function withdrawals(Request $r)
    {
        $rows = WithdrawalRequest::with('member', 'processor')->where('status', $r->input('status', 'pending'))->latest()->paginate(30)->withQueryString();
        return view('service.withdrawals', compact('rows'));
    }

    public function payWithdrawal(Request $r, WithdrawalRequest $w, AccountService $accounts)
    {
        $r->validate(['external_ref' => 'nullable|string|max:60']);
        if ($w->status !== 'pending') return back()->with('error', 'Already processed.');
        try {
            $tx = $accounts->withdraw($w->member, $w->account_code, (float) $w->amount,
                ['channel' => $w->channel, 'external_ref' => $r->external_ref, 'description' => "Withdrawal request #{$w->id} to {$w->destination}"]);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $w->update(['status' => 'paid', 'processed_by' => auth()->id(), 'transaction_id' => $tx->id]);
        $this->sms->send($w->member->phone, 'Your withdrawal of KES ' . number_format($w->amount, 2) . " has been paid via {$w->channel}. Ref {$tx->reference}.", $w->member_id);
        return back()->with('success', 'Withdrawal paid.');
    }

    public function rejectWithdrawal(Request $r, WithdrawalRequest $w)
    {
        $r->validate(['remarks' => 'required|string|max:255']);
        $w->update(['status' => 'rejected', 'processed_by' => auth()->id(), 'remarks' => $r->remarks]);
        $this->sms->send($w->member->phone, "Your withdrawal request of KES " . number_format($w->amount, 2) . " was declined: {$r->remarks}", $w->member_id);
        return back()->with('success', 'Request rejected.');
    }

    public function changeRequests()
    {
        $rows = MemberChangeRequest::with('member')->where('status', 'pending')->latest()->get();
        return view('service.change-requests', compact('rows'));
    }

    public function processChange(Request $r, MemberChangeRequest $c)
    {
        if ($c->status !== 'pending') return back()->with('error', 'Already processed.');
        if ($r->boolean('approve')) {
            $allowed = array_intersect_key($c->changes, array_flip(['phone', 'email', 'address', 'employer', 'department', 'payroll_no', 'kra_pin', 'occupation']));
            $c->member->update($allowed);
            if (isset($allowed['phone'])) $c->member->user?->update(['phone' => $allowed['phone']]);
            AuditLog::record('member_change_approved', $c->member, $allowed);
        }
        $c->update(['status' => $r->boolean('approve') ? 'approved' : 'rejected', 'processed_by' => auth()->id()]);
        return back()->with('success', 'Request processed.');
    }

    public function tickets(Request $r)
    {
        $tickets = Ticket::with('member', 'assignee')->when($r->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->whereIn('status', ['open', 'in_progress']))
            ->latest()->paginate(30)->withQueryString();
        $staff = User::where('role', '!=', 'member')->where('is_active', true)->orderBy('name')->get();
        return view('service.tickets', compact('tickets', 'staff'));
    }

    public function storeTicket(Request $r)
    {
        $d = $r->validate(['member_id' => 'nullable|exists:members,id', 'category' => 'required|in:complaint,inquiry,suggestion',
            'subject' => 'required|string|max:150', 'message' => 'required|string|max:5000']);
        Ticket::create($d + ['ticket_no' => 'TK' . now()->format('ymd') . str_pad((string) (Ticket::count() + 1), 4, '0', STR_PAD_LEFT)]);
        return back()->with('success', 'Ticket logged.');
    }

    public function updateTicket(Request $r, Ticket $ticket, NotificationService $notify)
    {
        $d = $r->validate(['status' => 'required|in:open,in_progress,resolved,closed', 'assigned_to' => 'nullable|exists:users,id', 'response' => 'nullable|string|max:5000']);
        if (in_array($d['status'], ['resolved', 'closed']) && ! $ticket->resolved_at) $d['resolved_at'] = now();
        $ticket->update($d);
        if (! empty($d['response']) && $ticket->member) {
            $notify->toMember($ticket->member, "Re {$ticket->ticket_no} ({$ticket->subject}): {$d['response']}", ['sms', 'email'], "Ticket {$ticket->ticket_no}");
        }
        return back()->with('success', 'Ticket updated.');
    }
}
