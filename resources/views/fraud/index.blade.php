@extends('layouts.app')
@section('title', 'Fraud & Suspicious Activity')
@section('content')
<div class="row g-3 mb-3">
    @foreach(['high' => 'danger', 'medium' => 'warning', 'low' => 'secondary'] as $sev => $c)
        <div class="col-md-3"><a href="?severity={{ $sev }}" class="text-decoration-none"><div class="card stat"><div class="card-body">
            <div class="label">{{ $sev }} – open</div><div class="value text-{{ $c }}">{{ $counts[$sev] ?? 0 }}</div></div></div></a></div>
    @endforeach
    <div class="col-md-3 d-flex flex-column gap-2 justify-content-center">
        @if(can_route('fraud.scan'))<form method="POST" action="{{ route('fraud.scan') }}">@csrf<button class="btn btn-outline-danger w-100"><i class="bi bi-radar"></i> Run pattern scan now</button></form>@endif
        <a href="?status=reported" class="btn btn-outline-secondary btn-sm">Reported to FRC</a>
    </div>
</div>
<div class="row g-3">
<div class="col-lg-9">
    <form class="d-flex gap-2 mb-2">
        <select name="status" class="form-select form-select-sm w-auto"><option value="">Open &amp; investigating</option>@foreach(['open', 'investigating', 'cleared', 'reported'] as $s)<option @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select>
        <select name="rule" class="form-select form-select-sm w-auto"><option value="">All rules</option>@foreach($rules as $k => [$l]) <option value="{{ $k }}" @selected(request('rule') === $k)>{{ $l }}</option>@endforeach</select>
        <button class="btn btn-sm btn-outline-secondary">Filter</button>
    </form>
    @forelse($alerts as $a)
        <div class="card mb-2 border-start border-4 border-{{ ['high' => 'danger', 'medium' => 'warning', 'low' => 'secondary'][$a->severity] }}"><div class="card-body py-2">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="badge text-bg-{{ ['high' => 'danger', 'medium' => 'warning', 'low' => 'secondary'][$a->severity] }}">{{ $a->severity }}</span>
                <strong>{{ $rules[$a->rule][0] ?? $a->rule }}</strong>
                <span class="text-muted small">{{ $a->created_at->format('d M Y H:i') }} · score {{ $a->score }}</span>
                <span class="badge text-bg-light ms-auto">{{ $a->status }}</span>
            </div>
            <div class="my-1">{{ $a->summary }}</div>
            <div class="small text-muted">
                @if($a->member)Member <a href="{{ route('members.show', $a->member) }}">{{ $a->member->member_no }} {{ $a->member->full_name }}</a> ({{ $a->member->risk_rating }} risk{{ $a->member->is_pep ? ', PEP' : '' }}) · @endif
                @if($a->transaction)Txn {{ $a->transaction->reference }} ({{ $a->transaction->channel }}) · @endif
                @if($a->staff)Staff {{ $a->staff->name }} · @endif
                @if($a->reviewer)Reviewed by {{ $a->reviewer->name }} {{ $a->reviewed_at?->diffForHumans() }}@endif
            </div>
            @if($a->review_notes)<div class="small mt-1"><strong>Notes:</strong> {{ $a->review_notes }} @if($a->str_reference)· STR {{ $a->str_reference }}@endif</div>@endif
            @if(in_array($a->status, ['open', 'investigating']))
            <form method="POST" action="{{ route('fraud.update', $a) }}" class="row g-1 mt-1">@csrf @method('PUT')
                <div class="col-md-3"><select name="status" class="form-select form-select-sm" onchange="this.form.querySelector('.str').classList.toggle('d-none', this.value !== 'reported')">
                    <option value="investigating">Investigating</option><option value="cleared">Clear – not suspicious</option><option value="reported">Report to FRC (STR)</option></select></div>
                <div class="col-md-5"><input name="review_notes" class="form-control form-control-sm" placeholder="Findings (required to clear or report)"></div>
                <div class="col-md-2 str d-none"><input name="str_reference" class="form-control form-control-sm" placeholder="goAML ref"></div>
                <div class="col-md-2"><button class="btn btn-sm btn-success w-100">Save</button></div>
            </form>
            @endif
        </div></div>
    @empty <div class="card"><div class="card-body text-muted">No alerts.</div></div> @endforelse
    {{ $alerts->links() }}
</div>
<div class="col-lg-3">
    <div class="card mb-3"><div class="card-body small">
        <h6>Last 30 days by rule</h6>
        @forelse($byRule as $r => $n)<div class="d-flex"><span>{{ $rules[$r][0] ?? $r }}</span><span class="ms-auto fw-semibold">{{ $n }}</span></div>@empty<span class="text-muted">None</span>@endforelse
    </div></div>
    <div class="card"><div class="card-body small">
        <h6>Rules monitored</h6>
        <ul class="ps-3 mb-0">@foreach($rules as [$l, $s])<li>{{ $l }} <span class="text-muted">({{ $s }})</span></li>@endforeach</ul>
        <p class="text-muted mt-2 mb-0">Real-time rules run on every posting; pattern rules run nightly. Thresholds: Settings (keys starting <code>fraud_</code>). The system flags; the compliance officer decides and files STRs with the FRC via goAML.</p>
    </div></div>
</div>
</div>
@endsection
