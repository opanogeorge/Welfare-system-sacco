@extends('layouts.app')
@section('title', 'Welfare & Support')
@section('content')
@php($sav = $member->account('SAV'))
@if($dues->count())
<div class="alert alert-warning d-flex align-items-center flex-wrap gap-2">
    <div><i class="bi bi-exclamation-circle"></i> You have <strong>{{ $dues->count() }}</strong> unpaid welfare contribution(s) totalling <strong>KES {{ money($dues->sum('amount')) }}</strong>.</div>
    <a href="{{ route('portal.pay', ['to' => 'WLF']) }}" class="btn btn-sm btn-warning ms-auto"><i class="bi bi-phone"></i> Pay now</a>
</div>
@endif
<div class="row g-3">
<div class="col-lg-8">
    <h6 class="mb-2">Members who need our support</h6>
    @forelse($open as $e)
        @php($myDue = $dues->firstWhere('welfare_event_id', $e->id))
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex gap-3">
                <div class="fs-2 text-primary"><i class="bi bi-{{ $e->icon() }}"></i></div>
                <div class="flex-grow-1">
                    <h6 class="mb-0">{{ $e->title }}</h6>
                    <div class="small text-muted">{{ $e->type_label }} · {{ $e->event_date?->format('d M Y') }}@if($e->closes_on) · contributions close {{ $e->closes_on->format('d M') }}@endif</div>
                    @if($e->details)<p class="small mt-2 mb-2">{!! nl2br(e($e->details)) !!}</p>@endif
                    <div class="small">
                        @if($e->levy_amount > 0 && $e->mode !== 'voluntary')
                            Levy KES {{ money($e->levy_amount, 0) }} per member:
                            @if($myDue)<span class="badge text-bg-danger">unpaid</span>@elseif($mine->where('welfare_event_id', $e->id)->where('kind', 'levy')->where('status', 'paid')->count())<span class="badge text-bg-success">paid, thank you</span>@endif
                        @elseif($e->suggested_amount > 0) Suggested KES {{ money($e->suggested_amount, 0) }} @endif
                        · Raised so far KES {{ money($e->raised, 0) }}
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <a href="{{ route('portal.pay', ['to' => 'W' . $e->id]) }}" class="btn btn-sm btn-primary"><i class="bi bi-phone"></i> Contribute via M-Pesa</a>
                        @if($sav)
                        <form method="POST" action="{{ route('portal.welfare.give', $e) }}" class="d-flex gap-1" data-confirm="Send this amount from your savings?">@csrf
                            <input type="number" name="amount" min="10" value="{{ $myDue?->amount ?? ($e->suggested_amount > 0 ? $e->suggested_amount + 0 : '') }}" class="form-control form-control-sm" style="max-width:110px" placeholder="KES" required>
                            <button class="btn btn-sm btn-outline-primary">From my savings</button></form>
                        @endif
                    </div>
                    <div class="small text-muted mt-1">Or Paybill {{ config('sacco.mpesa.paybill') }}, account <strong>{{ $member->member_no }}-W{{ $e->id }}</strong></div>
                </div>
            </div>
        </div></div>
    @empty
        <div class="card mb-3"><div class="card-body text-muted">No open welfare events right now.</div></div>
    @endforelse

    <div class="card"><div class="card-body">
        <h6>My welfare contributions</h6>
        <table class="table table-sm small mb-0">
            @forelse($mine as $c)
                <tr><td>{{ ($c->paid_at ?? $c->created_at)->format('d/m/Y') }}</td><td>{{ $c->event?->title ?? 'Monthly welfare ' . $c->period }}</td><td class="text-money">{{ money($c->amount) }}</td>
                    <td><span class="badge text-bg-{{ ['paid' => 'success', 'due' => 'danger', 'waived' => 'secondary'][$c->status] }}">{{ $c->status === 'due' ? 'unpaid' : $c->status }}</span></td></tr>
            @empty<tr><td class="text-muted">No contributions yet.</td></tr>@endforelse
        </table>
    </div></div>
</div>

<div class="col-lg-4">
    @if((int) setting('welfare_requests_from_portal', 1) === 1)
    <div class="card mb-3"><div class="card-body">
        <h6><i class="bi bi-heart"></i> Request support</h6>
        <p class="small text-muted">Let the welfare committee know about a bereavement, wedding, new baby, illness or other event.</p>
        <form method="POST" action="{{ route('portal.welfare.request') }}" enctype="multipart/form-data">@csrf
            <select name="type" class="form-select form-select-sm mb-2" required>@foreach(\App\Models\WelfareEvent::TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            <select name="relationship" class="form-select form-select-sm mb-2"><option value="">Who is it about?</option>@foreach(\App\Models\WelfareEvent::RELATIONSHIPS as $k => $v)<option value="{{ $k }}">{{ $k === 'self' ? 'Me' : $v }}</option>@endforeach</select>
            <input type="date" name="event_date" class="form-control form-control-sm mb-2">
            <textarea name="details" rows="3" class="form-control form-control-sm mb-2" placeholder="Tell us what happened, dates and arrangements" required></textarea>
            <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm mb-2">
            <button class="btn btn-sm btn-primary w-100">Send request</button>
        </form>
    </div></div>
    @endif
    @if($myEvents->count())
    <div class="card"><div class="card-body">
        <h6>My requests</h6>
        @foreach($myEvents as $e)
            <div class="small mb-2"><strong>{{ $e->title }}</strong><br><span class="badge text-bg-{{ $e->badge() }}">{{ $e->status_label }}</span>
                @if($e->paid_out > 0) · KES {{ money($e->paid_out) }} paid to you @endif</div>
        @endforeach
    </div></div>
    @endif
</div>
</div>
@endsection
