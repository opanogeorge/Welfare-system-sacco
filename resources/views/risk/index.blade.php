@extends('layouts.app')
@section('title', 'Credit Risk (AI Scoring)')
@section('content')
<div class="row g-3 mb-3">
<div class="col-lg-8"><div class="card h-100"><div class="card-body">
    @if($model)
        <div class="d-flex align-items-start">
            <div><h5 class="mb-0">Active model {{ $model->version }}</h5>
                <small class="text-muted">Trained {{ $model->created_at->format('d M Y H:i') }} on {{ number_format($model->samples) }} loans ({{ $model->defaults }} defaults) · logistic regression</small></div>
            @if(can_route('risk.train') && auth()->user()->isHq())
                <form method="POST" action="{{ route('risk.train') }}" class="ms-auto">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-arrow-repeat"></i> Retrain now</button></form>
            @endif
        </div>
        <div class="row g-2 my-3">
            <div class="col"><div class="border rounded p-2 text-center"><div class="small text-muted">Validation AUC</div><div class="fs-4 fw-bold">{{ $model->auc ?? '—' }}</div>
                <div class="small {{ ($model->auc ?? 0) >= 0.7 ? 'text-success' : 'text-warning' }}">{{ ($model->auc ?? 0) >= 0.8 ? 'Strong' : (($model->auc ?? 0) >= 0.7 ? 'Good' : 'Weak – needs more data') }}</div></div></div>
            <div class="col"><div class="border rounded p-2 text-center"><div class="small text-muted">Accuracy</div><div class="fs-4 fw-bold">{{ $model->accuracy ? number_format($model->accuracy * 100, 1) . '%' : '—' }}</div></div></div>
            <div class="col"><div class="border rounded p-2 text-center"><div class="small text-muted">Default rate in data</div><div class="fs-4 fw-bold">{{ number_format($model->defaults / max(1, $model->samples) * 100, 1) }}%</div></div></div>
        </div>
        <h6>What drives risk in your SACCO</h6>
        <table class="table table-sm small">
            @foreach($weights as $w)
                <tr><td style="width:45%">{{ $w['name'] }}</td>
                    <td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:8px"><div class="progress-bar bg-{{ $w['w'] > 0 ? 'danger' : 'success' }}" style="width: {{ min(100, abs($w['w']) * 50) }}%"></div></div>
                        <span class="text-{{ $w['w'] > 0 ? 'danger' : 'success' }}" style="width:110px">{{ $w['w'] > 0 ? '↑ raises' : '↓ lowers' }} risk</span></div></td></tr>
            @endforeach
        </table>
    @else
        <h5>No model trained yet</h5>
        <p>The system learns from your own loan history: which borrowers repaid and which went 90+ days in arrears. It needs at least <strong>{{ setting('risk_min_samples', 50) }}</strong> finished loans including <strong>{{ setting('risk_min_defaults', 5) }}</strong> defaults. Until then, loans use the rule-based score.</p>
        @if(can_route('risk.train') && auth()->user()->isHq())
            <form method="POST" action="{{ route('risk.train') }}">@csrf<button class="btn btn-success"><i class="bi bi-cpu"></i> Train model now</button></form>
        @endif
        <p class="small text-muted mt-3 mb-0">Retrains automatically on the 2nd of every month.</p>
    @endif
</div></div></div>
<div class="col-lg-4"><div class="card h-100"><div class="card-body">
    <h6>Running loans by risk band</h6>
    <table class="table table-sm">
        <thead><tr><th>Band</th><th class="text-end">Loans</th><th class="text-end">Exposure</th></tr></thead>
        @foreach($bands as $b => $v)
            <tr><td><span class="badge text-bg-{{ ['A' => 'success', 'B' => 'success', 'C' => 'warning', 'D' => 'danger', 'E' => 'dark'][$b] }}">{{ $b }}</span>
                <small class="text-muted">PD &lt; {{ number_format(collect(\App\Services\CreditRiskService::BANDS)->firstWhere(0, $b)[1] * 100, 0) }}%</small></td>
                <td class="text-money">{{ $v['count'] }}</td><td class="text-money">{{ money($v['exposure'], 0) }}</td></tr>
        @endforeach
    </table>
    <div class="small">Expected loss (PD × balance × LGD {{ $lgd * 100 }}%): <strong>KES {{ money($running->sum('el')) }}</strong></div>
    @if($history->count() > 1)
        <h6 class="mt-3">Model history</h6>
        <table class="table table-sm small mb-0">@foreach($history as $h)<tr><td>{{ $h->version }}</td><td>AUC {{ $h->auc ?? '—' }}</td>
            <td>@if($h->is_active)<span class="badge text-bg-success">active</span>@elseif(can_route('risk.activate'))<form method="POST" action="{{ route('risk.activate', $h) }}">@csrf<button class="btn btn-sm btn-link p-0">use</button></form>@endif</td></tr>@endforeach</table>
    @endif
</div></div></div>
</div>

@if($model)
<div class="card"><div class="card-body">
    <h6>Loan risk prediction – running loans (highest risk first)</h6>
    <table class="table table-sm table-hover align-middle">
        <thead><tr><th>Loan</th><th>Member</th><th class="text-end">Balance</th><th class="text-end">Days late</th><th class="text-end">PD</th><th>Band</th><th>Main factors</th><th class="text-end">Exp. loss</th></tr></thead>
        @foreach($running->take(100) as $r)
            <tr><td><a href="{{ route('loans.show', $r['loan']) }}">{{ $r['loan']->loan_no }}</a></td><td>{{ $r['loan']->member->full_name }}</td>
                <td class="text-money">{{ money($r['ead'], 0) }}</td><td class="text-money">{{ $r['loan']->daysInArrears() }}</td>
                <td class="text-money fw-semibold">{{ number_format($r['pd'] * 100, 1) }}%</td>
                <td><span class="badge text-bg-{{ ['A' => 'success', 'B' => 'success', 'C' => 'warning', 'D' => 'danger', 'E' => 'dark'][$r['band']] }}">{{ $r['band'] }}</span></td>
                <td class="small">@foreach(collect($r['drivers'])->where('effect', 'raises risk')->take(2) as $d)<span class="text-danger">{{ $d['factor'] }}</span>@if(! $loop->last), @endif @endforeach</td>
                <td class="text-money">{{ money($r['el'], 0) }}</td></tr>
        @endforeach
    </table>
    <p class="small text-muted mb-0">PD = probability the loan goes 90+ days in arrears. Use it to prioritise follow-up and to set provisions alongside the SASRA classification. It supports, but does not replace, the credit committee's judgement.</p>
</div></div>
@endif
@endsection
