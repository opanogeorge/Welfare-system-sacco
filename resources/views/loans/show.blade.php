@extends('layouts.app')
@section('title', "Loan {$loan->loan_no}")
@section('content')
@php($u = auth()->user())
<div class="row g-3">
<div class="col-lg-8">
    <div class="card mb-3"><div class="card-body">
        <div class="d-flex align-items-start mb-3">
            <div>
                <h5 class="mb-0">{{ $loan->product->name }} <span class="badge text-bg-{{ $loan->badge() }} align-middle">{{ $loan->status_label }}</span></h5>
                <a href="{{ route('members.show', $loan->member) }}">{{ $loan->member->member_no }} · {{ $loan->member->full_name }}</a> · {{ $loan->member->phone }}
            </div>
            @if($loan->status === 'disbursed')
                <div class="ms-auto text-end"><div class="small text-muted">Outstanding</div><div class="fs-4 fw-bold text-danger">KES {{ money($loan->outstanding) }}</div>
                    <span class="badge text-bg-{{ $loan->daysInArrears() ? 'danger' : 'success' }}">{{ $loan->classification() }} @if($loan->daysInArrears()) · {{ $loan->daysInArrears() }} days @endif</span></div>
            @endif
        </div>
        <div class="row small g-2">
            @foreach([
                'Applied' => 'KES ' . money($loan->amount_applied), 'Approved' => $loan->amount_approved ? 'KES ' . money($loan->amount_approved) : '—',
                'Rate' => "{$loan->interest_rate}% p.a. (" . (\App\Models\Loan::METHODS[$loan->interest_method] ?? $loan->interest_method) . ')' . ($loan->grace_period_months ? " · {$loan->grace_period_months} mo grace" : ''), 'Period' => "{$loan->period} months",
                'Applied on' => $loan->applied_on->format('d M Y'), 'Disbursed' => $loan->disbursed_on?->format('d M Y') ?? '—',
                'Maturity' => $loan->maturity_date?->format('d M Y') ?? '—', 'Purpose' => $loan->purpose ?: '—',
                'Processing fee' => money($loan->processing_fee), 'Insurance' => money($loan->insurance_fee),
                'Net disbursed' => money($loan->net_disbursed), 'Total paid' => money($loan->total_paid),
            ] as $k => $v)
                <div class="col-6 col-md-3"><div class="text-muted">{{ $k }}</div><div class="fw-semibold">{{ $v }}</div></div>
            @endforeach
        </div>
        @if($loan->status === 'disbursed')
            <div class="row small g-2 mt-1 border-top pt-2">
                <div class="col-4">Principal bal: <strong>{{ money($loan->principal_balance) }}</strong></div>
                <div class="col-4">Interest bal: <strong>{{ money($loan->interest_balance) }}</strong></div>
                <div class="col-4">Penalty bal: <strong>{{ money($loan->penalty_balance) }}</strong></div>
            </div>
        @endif
        @if($loan->status === 'written_off')
            <div class="alert alert-danger small mt-3 mb-0">Written off on {{ $loan->written_off_on?->format('d M Y') }}: KES {{ money($loan->written_off_amount) }}. Recovered since: <strong>KES {{ money($loan->recovered_after_write_off) }}</strong> · still owed KES {{ money($loan->outstanding) }}. Payments are posted to <em>Bad Debts Recovered</em>.</div>
        @endif
        @if($loan->gross_pay)
            <div class="small mt-2 text-muted">Affordability at appraisal: gross {{ money($loan->gross_pay) }}, other deductions {{ money($loan->other_deductions) }}, deductions/gross <strong class="{{ $loan->affordability_ratio > ($loan->product->max_deduction_ratio ?? 66.67) ? 'text-danger' : 'text-success' }}">{{ $loan->affordability_ratio }}%</strong></div>
        @endif
        @if($loan->remarks)<div class="alert alert-light small mt-3 mb-0"><strong>Remarks:</strong> {!! nl2br(e($loan->remarks)) !!}</div>@endif
        @if($loan->topUpOf)<div class="alert alert-info small mt-2 mb-0">Top-up of <a href="{{ route('loans.show', $loan->topUpOf) }}">{{ $loan->topUpOf->loan_no }}</a>@if($loan->top_up_payoff > 0) · KES {{ money($loan->top_up_payoff) }} used to clear it @endif</div>@endif
        <div class="mt-3 d-flex gap-2 no-print">
            <a href="{{ route('loans.statement', $loan) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Loan statement</a>
            @if($loan->status === 'disbursed' && auth()->user()->hasRole('loan_officer') && ($loan->product->allow_top_up ?? true))
                <a href="{{ route('loans.create', ['top_up' => $loan->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-up-circle"></i> Top-up / refinance</a>
            @endif
            @if(in_array($loan->status, ['disbursed', 'written_off']) && $loan->daysInArrears() > 0 && $u->hasRole('manager', 'loan_officer'))
                <a href="{{ route('loans.demand-letter', $loan) }}" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-envelope-exclamation"></i> Demand letter</a>
            @endif
        </div>
    </div></div>

    @if($loan->approvals->count() || $loan->approvals_required > 1)
    <div class="card mb-3"><div class="card-body">
        <h6>Approval trail <small class="text-muted">· {{ $loan->approvals->count() }} of {{ $loan->approvals_required }} level(s)</small></h6>
        <table class="table table-sm mb-0">@forelse($loan->approvals as $a)<tr><td>Level {{ $a->level }}</td><td>{{ $a->user->name }}</td><td class="text-money">{{ money($a->amount) }}</td><td class="small">{{ $a->remarks }}</td><td class="small text-muted">{{ $a->created_at->format('d/m/Y H:i') }}</td></tr>
            @empty<tr><td class="text-muted">Awaiting first approval.</td></tr>@endforelse</table>
    </div></div>
    @endif

    <div class="card mb-3"><div class="card-body">
        <h6>Collateral &amp; security</h6>
        <table class="table table-sm align-middle">
            @forelse($loan->collaterals as $c)
                <tr><td>{{ str_replace('_', ' ', $c->type) }}</td><td>{{ $c->description }} <span class="small text-muted">{{ $c->reference }}</span></td><td class="text-money">{{ money($c->value) }}</td>
                    <td>@if($c->document)<a href="{{ route('loans.collateral.document', $c) }}"><i class="bi bi-paperclip"></i></a>@endif</td>
                    <td><span class="badge text-bg-light">{{ $c->status }}</span> @if($c->status === 'held' && $loan->status !== 'disbursed' && auth()->user()->hasRole('manager'))
                        <form method="POST" action="{{ route('loans.collateral.release', $c) }}" class="d-inline">@csrf<button class="btn btn-sm btn-link p-0">release</button></form>@endif</td></tr>
            @empty <tr><td class="text-muted">No collateral recorded (guarantors / deposits only).</td></tr> @endforelse
        </table>
        @if(auth()->user()->hasRole('loan_officer') && ! in_array($loan->status, ['closed', 'rejected']))
        <form method="POST" action="{{ route('loans.collateral.store', $loan) }}" enctype="multipart/form-data" class="row g-1">@csrf
            <div class="col-md-2"><select name="type" class="form-select form-select-sm">@foreach(['title_deed' => 'Title deed', 'logbook' => 'Logbook', 'salary' => 'Salary assignment', 'equipment' => 'Equipment', 'fixed_deposit' => 'Fixed deposit', 'other' => 'Other'] as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="col-md-4"><input name="description" class="form-control form-control-sm" placeholder="Description" required></div>
            <div class="col-md-2"><input type="number" step="0.01" name="value" class="form-control form-control-sm" placeholder="Value" required></div>
            <div class="col-md-2"><input name="reference" class="form-control form-control-sm" placeholder="Title/reg no."></div>
            <div class="col-md-2"><input type="file" name="document" class="form-control form-control-sm"></div>
            <div class="col-12"><button class="btn btn-sm btn-outline-success">Add collateral</button> <span class="small text-muted">Total security: KES {{ money($loan->collaterals->where('status', 'held')->sum('value') + $loan->guaranteedAmount()) }} (collateral + guarantees)</span></div>
        </form>
        @endif
    </div></div>

    <div class="card mb-3" id="guarantors"><div class="card-body">
        <h6>Guarantors <small class="text-muted">· accepted cover KES {{ money($loan->guaranteedAmount()) }}@if($loan->status === 'disbursed') · current exposure KES {{ money($loan->guarantors->where('status', 'accepted')->sum(fn ($g) => $g->exposure())) }}@endif</small></h6>
        <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            @forelse($loan->guarantors->sortBy(fn ($g) => array_search($g->status, ['accepted', 'pending', 'declined', 'expired', 'released', 'withdrawn'])) as $g)
                <tr class="{{ in_array($g->status, ['released', 'withdrawn', 'expired', 'declined']) ? 'text-muted' : '' }}">
                    <td><a href="{{ route('guarantors.show', $g->member) }}">{{ $g->member->member_no }}</a> {{ $g->member->full_name }}
                        @if($g->release_requested_at)<br><span class="badge text-bg-warning">asked to be released</span> <small>{{ $g->release_request_reason }}</small>@endif
                        @if($g->replaced_by_id && $g->status === 'accepted')<br><small class="text-muted">replacement awaiting consent</small>@endif</td>
                    <td class="text-money">{{ money($g->amount) }}@if($g->status === 'accepted' && $loan->status === 'disbursed')<br><small class="text-muted">now {{ money($g->exposure()) }} ({{ $g->reducedPercent() }}% reduced)</small>@endif</td>
                    <td><span class="badge text-bg-{{ $g->badge() }}">{{ $g->status_label }}</span>
                        <div class="small text-muted">@if($g->status === 'pending' && $g->expires_at)lapses {{ $g->expires_at->format('d/m') }}@elseif($g->responded_at){{ $g->consent_channel }} {{ $g->responded_at->format('d/m/Y') }}@endif
                            @if($g->decline_reason)· “{{ $g->decline_reason }}”@endif @if($g->released_at)· {{ $g->release_reason }}@endif
                            @if($g->recovered_amount > 0)<span class="text-danger">· recovered {{ money($g->recovered_amount) }}</span>@endif</div></td>
                    <td class="text-end text-nowrap">
                        @if(in_array($g->status, ['accepted', 'declined', 'expired', 'pending']) && in_array($loan->status, ['guarantors_pending', 'pending', 'appraised', 'approved', 'disbursed']) && $u->hasRole('loan_officer') && ! ($g->replaced_by_id && $g->status === 'accepted'))
                            <button class="btn btn-sm btn-link p-0" data-bs-toggle="collapse" data-bs-target="#rg{{ $g->id }}">replace</button>
                        @endif
                        @if($g->status === 'accepted' && $u->hasRole('manager'))
                            @if($g->release_requested_at)
                                <form method="POST" action="{{ route('guarantors.release-decision', $g) }}" class="d-inline">@csrf<button name="decision" value="approve" class="btn btn-sm btn-link p-0 text-danger">approve release</button> <button name="decision" value="reject" class="btn btn-sm btn-link p-0">decline</button></form>
                            @else
                                <form method="POST" action="{{ route('loans.guarantor.release', $g) }}" class="d-inline" data-confirm="Release this guarantor? Allowed only if the loan stays covered.">@csrf<input type="hidden" name="reason" value="Released by the SACCO"><button class="btn btn-sm btn-link p-0 text-danger">release</button></form>
                            @endif
                        @endif
                        @if($g->status === 'pending' && $u->hasRole('loan_officer'))
                            <form method="POST" action="{{ route('loans.guarantor.respond', $g) }}" class="d-inline">@csrf<input type="hidden" name="accept" value="1"><button class="btn btn-sm btn-outline-success py-0">Signed form</button></form>
                            <form method="POST" action="{{ route('loans.guarantor.respond', $g) }}" class="d-inline">@csrf<input type="hidden" name="accept" value="0"><button class="btn btn-sm btn-outline-danger py-0">Declined</button></form>
                        @endif
                        <form method="POST" action="{{ route('loans.guarantor.replace', $g) }}" class="collapse mt-1 text-start" id="rg{{ $g->id }}">@csrf
                            <select name="member_id" class="member-select guarantor-pick" data-amount-target="ra{{ $g->id }}" required></select>
                            <div class="d-flex gap-1 mt-1"><input type="number" step="0.01" name="amount" id="ra{{ $g->id }}" value="{{ $g->amount }}" class="form-control form-control-sm"><button class="btn btn-sm btn-primary">Send request</button></div>
                            <div class="small g-check"></div>
                            <div class="form-text">The current guarantor stays liable until the new one accepts.</div>
                        </form>
                    </td></tr>
            @empty <tr><td class="text-muted">No guarantors.</td></tr> @endforelse
        </table>
        </div>
        @if(in_array($loan->status, ['guarantors_pending', 'pending', 'appraised', 'approved', 'disbursed']) && $u->hasRole('loan_officer'))
            <button class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="collapse" data-bs-target="#addG"><i class="bi bi-person-plus"></i> Add guarantor</button>
            <form method="POST" action="{{ route('loans.guarantor.add', $loan) }}" class="collapse mt-2 border rounded p-2" id="addG">@csrf
                <select name="member_id" class="member-select guarantor-pick" data-amount-target="addGAmt" required></select>
                <div class="d-flex gap-1 mt-1"><input type="number" step="0.01" name="amount" id="addGAmt" class="form-control form-control-sm" placeholder="Amount to guarantee" required><button class="btn btn-sm btn-primary">Add &amp; request consent</button></div>
                <div class="small g-check mt-1"></div>
            </form>
        @endif
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <h6>{{ $preview ? 'Projected repayment schedule' : 'Repayment schedule' }}</h6>
        @if($preview)
            <p class="small text-muted">Installment KES {{ money($preview['installment']) }} · Total interest KES {{ money($preview['total_interest']) }} · Total payable KES {{ money($preview['total_payable']) }}</p>
            @include('loans._schedule', ['rows' => $preview['rows'], 'live' => false])
        @else
            @include('loans._schedule', ['rows' => $loan->schedules, 'live' => true])
        @endif
    </div></div>

    @if($loan->charges->count())
    <div class="card mb-3"><div class="card-body">
        <h6>Fines &amp; waivers</h6>
        <table class="table table-sm mb-0">@foreach($loan->charges->sortByDesc('created_at') as $c)
            <tr><td class="small">{{ $c->created_at->format('d/m/Y') }}</td><td><span class="badge text-bg-{{ $c->kind === 'fine' ? 'danger' : 'success' }}">{{ $c->kind }}</span> {{ str_replace('_', ' ', $c->category) }}</td>
                <td class="small">{{ $c->reason }}</td><td class="text-money">{{ $c->kind === 'waiver' ? '-' : '' }}{{ money($c->amount) }}</td><td class="small text-muted">{{ $c->user?->name }}</td></tr>
        @endforeach</table>
    </div></div>
    @endif

    @if(in_array($loan->status, ['disbursed', 'written_off', 'closed']) && ($loan->recoveryActions->count() || $loan->daysInArrears() > 0))
    <div class="card mb-3"><div class="card-body">
        <h6>Recovery log @if($loan->recovery_stage)<span class="badge text-bg-warning">{{ $loan->recovery_stage }}</span>@endif</h6>
        <table class="table table-sm">@forelse($loan->recoveryActions->sortByDesc('created_at') as $a)
            <tr><td class="small text-nowrap">{{ $a->created_at->format('d/m/Y H:i') }}</td><td>{{ \App\Services\LoanService::RECOVERY_ACTIONS[$a->action] ?? $a->action }}</td>
                <td class="small">{{ $a->notes }} @if($a->promise_date)<div>Promise: KES {{ money($a->promise_amount) }} by {{ $a->promise_date->format('d/m/Y') }} <span class="badge text-bg-{{ ['kept' => 'success', 'broken' => 'danger'][$a->promise_status] ?? 'secondary' }}">{{ $a->promise_status }}</span></div>@endif</td>
                <td class="small text-muted">{{ $a->user?->name }}</td></tr>
        @empty<tr><td class="text-muted small">No follow-up recorded yet.</td></tr>@endforelse</table>
        @if($u->hasRole('manager', 'loan_officer', 'accountant') && $loan->status !== 'closed')
        <form method="POST" action="{{ route('loans.recovery-action', $loan) }}" class="row g-1">@csrf
            <div class="col-md-3"><select name="action" class="form-select form-select-sm" onchange="document.getElementById('ptp').classList.toggle('d-none', this.value !== 'promise_to_pay')">@foreach(\App\Services\LoanService::RECOVERY_ACTIONS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="col-md-5"><input name="notes" class="form-control form-control-sm" placeholder="Notes (who you spoke to, outcome)"></div>
            <div class="col-md-4 d-flex gap-1"><button class="btn btn-sm btn-outline-primary">Record</button></div>
            <div class="col-12 d-none row g-1 m-0 p-0" id="ptp">
                <div class="col-md-4"><input type="date" name="promise_date" class="form-control form-control-sm" min="{{ today()->toDateString() }}"></div>
                <div class="col-md-4"><input type="number" step="0.01" name="promise_amount" class="form-control form-control-sm" placeholder="Promised amount"></div>
            </div>
        </form>
        <div class="small text-muted mt-1">"Guarantors notified" also sends each guarantor an SMS. Promises are checked every morning and marked kept or broken.</div>
        @endif
    </div></div>
    @endif

    @if($loan->repayments->count())
    <div class="card"><div class="card-body">
        <h6>Repayments</h6>
        <table class="table table-sm mb-0">
            <thead><tr><th>Date</th><th>Ref</th><th>Channel</th><th class="text-end">Amount</th><th class="text-end">Penalty</th><th class="text-end">Interest</th><th class="text-end">Principal</th><th>By</th></tr></thead>
            @foreach($loan->repayments as $r)
                <tr><td>{{ $r->paid_on->format('d/m/Y') }}</td><td class="small">{{ $r->reference }} {{ $r->external_ref }}</td><td>{{ $r->channel }}</td>
                    <td class="text-money fw-semibold">{{ money($r->amount) }}</td><td class="text-money">{{ money($r->penalty_paid) }}</td>
                    <td class="text-money">{{ money($r->interest_paid) }}</td><td class="text-money">{{ money($r->principal_paid) }}</td><td class="small">{{ $r->poster?->name }}</td></tr>
            @endforeach
        </table>
    </div></div>
    @endif
</div>

<div class="col-lg-4">
    @php
        $riskModel = app(\App\Services\CreditRiskService::class)->activeModel();
        $ml = null;
        if ($riskModel && $loan->risk_features) { try { $ml = $loan->status === 'disbursed' ? app(\App\Services\CreditRiskService::class)->predictRunning($loan) : app(\App\Services\CreditRiskService::class)->predict($loan->risk_features); } catch (\Throwable $e) { $ml = null; } }
        $crbLatest = \App\Models\CrbCheck::where('member_id', $loan->member_id)->latest()->first();
        $loanSigs = \App\Models\Signature::where('signable_type', \App\Models\Loan::class)->where('signable_id', $loan->id)->get();
    @endphp
    @if($ml)
        <div class="card mb-3 border-start border-4 border-{{ $ml['pd'] < 0.07 ? 'success' : ($ml['pd'] < 0.3 ? 'warning' : 'danger') }}"><div class="card-body small">
            <div class="d-flex align-items-center"><h6 class="mb-0"><i class="bi bi-cpu"></i> AI risk prediction</h6>
                <span class="ms-auto fs-5 fw-bold">{{ number_format($ml['pd'] * 100, 1) }}%</span><span class="badge text-bg-dark ms-2">{{ $ml['band'] }}</span></div>
            <div class="text-muted">Probability of going 90+ days in arrears · model {{ $ml['model']->version }}</div>
            <div class="my-2 fw-semibold">Recommendation: {{ $ml['recommendation'] }}</div>
            @foreach($ml['drivers'] as $d)<div><i class="bi bi-arrow-{{ $d['effect'] === 'raises risk' ? 'up text-danger' : 'down text-success' }}"></i> {{ $d['factor'] }} <span class="text-muted">({{ $d['value'] }})</span></div>@endforeach
            @if(auth()->user()->hasRole('loan_officer') && $loan->status !== 'disbursed')
                <form method="POST" action="{{ route('loans.rescore', $loan) }}" class="mt-2">@csrf<button class="btn btn-sm btn-link p-0">Re-score with latest data</button></form>
            @endif
        </div></div>
    @endif

    @if(in_array($loan->status, ['pending', 'guarantors_pending', 'appraised', 'approved']))
    <div class="card mb-3"><div class="card-body small">
        <h6><i class="bi bi-clipboard-data"></i> Credit bureau (CRB)</h6>
        @if($crbLatest)
            <div class="mb-2">Last check {{ $crbLatest->created_at->diffForHumans() }} ({{ ucfirst($crbLatest->provider) }}):
                @if($crbLatest->score)<strong>score {{ $crbLatest->score }}</strong>@endif
                @if($crbLatest->is_listed === true)<span class="badge text-bg-danger">Negative listing</span>@elseif($crbLatest->is_listed === false)<span class="badge text-bg-success">Clear</span>@endif
                <div class="text-muted">{{ $crbLatest->summary }}</div></div>
        @else<div class="text-muted mb-2">No CRB check on file.</div>@endif
        @if(auth()->user()->hasRole('loan_officer'))
            <form method="POST" action="{{ route('crb.run', $loan->member) }}" class="d-flex gap-1 mb-1">@csrf<input type="hidden" name="loan_id" value="{{ $loan->id }}">
                <select name="type" class="form-select form-select-sm"><option value="score">Credit score</option><option value="delinquency">Delinquency status</option><option value="report">Full report</option></select>
                <label class="small text-nowrap"><input type="checkbox" name="consent" value="1" required> consent</label>
                <button class="btn btn-sm btn-outline-success">Run</button></form>
            <a class="small" data-bs-toggle="collapse" href="#crbManual">Record a result manually</a>
            <form method="POST" action="{{ route('crb.manual', $loan->member) }}" enctype="multipart/form-data" class="collapse mt-1" id="crbManual">@csrf<input type="hidden" name="loan_id" value="{{ $loan->id }}">
                <div class="row g-1">
                    <div class="col-6"><select name="provider" class="form-select form-select-sm"><option value="metropol">Metropol</option><option value="transunion">TransUnion</option><option value="creditinfo">Creditinfo</option></select></div>
                    <div class="col-6"><input name="score" type="number" class="form-control form-control-sm" placeholder="Score"></div>
                    <div class="col-12"><input name="summary" class="form-control form-control-sm" placeholder="Summary"></div>
                    <div class="col-12"><input type="file" name="report" accept=".pdf" class="form-control form-control-sm"></div>
                    <div class="col-12 d-flex gap-2"><label><input type="checkbox" name="is_listed" value="1"> negative listing</label><label><input type="checkbox" name="consent" value="1" required> consent</label>
                        <button class="btn btn-sm btn-outline-success ms-auto">Save</button></div>
                </div></form>
        @endif
    </div></div>
    @endif

    <div class="card mb-3"><div class="card-body small">
        <div class="d-flex"><h6><i class="bi bi-pen"></i> Loan agreement</h6><a href="{{ route('loans.agreement', $loan) }}" target="_blank" class="ms-auto">View / PDF</a></div>
        @forelse($loanSigs as $s)<div><i class="bi bi-check-circle text-success"></i> {{ $s->signer_name }} ({{ $s->signer_role }}) {{ $s->signed_at->format('d/m/Y H:i') }} <code>{{ $s->verification_code }}</code></div>
        @empty<div class="text-muted">Not signed yet. The member can sign in the portal, or capture it here.</div>@endforelse
        @if(auth()->user()->hasRole('loan_officer', 'teller') && ! in_array($loan->status, ['rejected', 'closed']))
            <a class="small" data-bs-toggle="collapse" href="#sigPad">Capture signature at the counter</a>
            <form method="POST" action="{{ route('loans.sign', $loan) }}" class="collapse mt-2" id="sigPad">@csrf
                <select name="signer" class="form-select form-select-sm mb-1"><option value="borrower">Borrower: {{ $loan->member->full_name }}</option><option value="witness">Witness (me)</option></select>
                @include('partials.signature-pad', ['name' => 'signature', 'height' => 140])
                <button class="btn btn-sm btn-success w-100">Save signature</button>
            </form>
        @endif
    </div></div>
    @if($score)
        @php($grade = $score['score'] >= 80 ? 'A' : ($score['score'] >= 65 ? 'B' : ($score['score'] >= 50 ? 'C' : ($score['score'] >= 35 ? 'D' : 'E'))))
        <div class="card mb-3"><div class="card-body small">
            <div class="d-flex align-items-center mb-2"><h6 class="mb-0">Credit score</h6>
                <span class="ms-auto fs-4 fw-bold text-{{ $score['score'] >= 65 ? 'success' : ($score['score'] >= 50 ? 'warning' : 'danger') }}">{{ $score['score'] }}</span><span class="badge text-bg-dark ms-2">{{ $grade }}</span></div>
            <div class="progress mb-2" style="height:6px"><div class="progress-bar bg-{{ $score['score'] >= 65 ? 'success' : ($score['score'] >= 50 ? 'warning' : 'danger') }}" style="width: {{ $score['score'] }}%"></div></div>
            @foreach($score['factors'] as $k => $v)<div class="d-flex"><span class="text-muted">{{ $k }}</span><span class="ms-auto">{{ $v }}</span></div>@endforeach
            <div class="text-muted mt-2">Rule-based score from SACCO records. Check CRB separately for external debt.</div>
        </div></div>
    @endif

    @if($eligibility)
        <div class="card mb-3"><div class="card-body small">
            <h6>Appraisal check</h6>
            <table class="table table-sm mb-2">
                <tr><td>Deposits</td><td class="text-money">{{ money($eligibility['deposits']) }}</td></tr>
                <tr><td>Other running loans</td><td class="text-money">{{ money($eligibility['outstanding']) }}</td></tr>
                <tr><td>Max qualifying</td><td class="text-money fw-bold">{{ money($eligibility['max_amount']) }}</td></tr>
                <tr><td>Membership</td><td class="text-money">{{ $loan->member->monthsOfMembership() }} months</td></tr>
            </table>
            @foreach($eligibility['issues'] as $i)<div class="text-danger">• {{ $i }}</div>@endforeach
            @if($eligibility['eligible'])<span class="badge text-bg-success">Within policy</span>@endif
        </div></div>
    @endif

    @if($loan->status === 'pending' && $u->hasRole('loan_officer'))
        <div class="card mb-3"><div class="card-body"><h6>Appraise</h6>
            <form method="POST" action="{{ route('loans.appraise', $loan) }}">@csrf
                <div class="small fw-semibold mb-1">Affordability (from payslip)</div>
                <div class="row g-1 mb-1">
                    <div class="col-6"><input type="number" step="0.01" name="gross_pay" id="affGross" value="{{ old('gross_pay', $loan->gross_pay) }}" class="form-control form-control-sm" placeholder="Gross pay"></div>
                    <div class="col-6"><input type="number" step="0.01" name="other_deductions" id="affOther" value="{{ old('other_deductions', $loan->other_deductions) }}" class="form-control form-control-sm" placeholder="Other deductions"></div>
                    <div class="col-12"><input type="number" step="0.01" name="net_pay" value="{{ old('net_pay', $loan->net_pay) }}" class="form-control form-control-sm" placeholder="Net pay (optional)"></div>
                </div>
                <div id="affResult" class="small mb-2 text-muted">Enter gross pay to test the {{ $loan->product->max_deduction_ratio ?? 66.67 }}% deduction rule. Leave blank for non-salaried members.</div>
                <textarea name="remarks" class="form-control mb-2" rows="2" placeholder="Appraisal notes (payslip, CRB, ability to pay)">{{ old('remarks') }}</textarea>
                <div class="form-check small mb-2"><input type="checkbox" name="override" value="1" class="form-check-input" id="affOv"><label class="form-check-label" for="affOv">Forward even if it fails affordability (reason required in notes)</label></div>
                <button class="btn btn-primary w-100">Forward for approval</button></form>
        </div></div>
    @endif

    @if(in_array($loan->status, ['appraised', 'pending']) && $u->hasRole('manager'))
        <div class="card mb-3"><div class="card-body"><h6>Approve <small class="text-muted">level {{ $loan->approvals->count() + 1 }} of {{ $loan->approvals_required }}</small></h6>
            <form method="POST" action="{{ route('loans.approve', $loan) }}">@csrf
                <label class="form-label small">Amount approved</label>
                <input type="number" step="0.01" name="amount_approved" value="{{ $loan->amount_applied }}" max="{{ $loan->amount_applied }}" class="form-control mb-2" required>
                <textarea name="remarks" class="form-control mb-2" rows="2" placeholder="Committee remarks"></textarea>
                <button class="btn btn-success w-100">Approve</button></form>
        </div></div>
    @endif

    @if($loan->status === 'approved' && $u->hasRole('accountant', 'manager'))
        <div class="card mb-3"><div class="card-body"><h6>Disburse</h6>
            <form method="POST" action="{{ route('loans.disburse', $loan) }}" data-confirm="Disburse this loan?">@csrf
                <label class="form-label small">Channel</label>
                <select name="channel" class="form-select mb-2"><option value="savings">Credit member's savings (FOSA)</option><option value="bank">Bank transfer</option><option value="mpesa">M-Pesa (B2C)</option><option value="cheque">Cheque</option>
                    @if($u->hasRole('accountant'))<option value="opening">Go-live balance migration (no cash)</option>@endif</select>
                @if($loan->topUpOf)<div class="alert alert-info small py-1">KES {{ money($loan->topUpOf->outstanding) }} will be used to clear {{ $loan->topUpOf->loan_no }}.</div>@endif
                <div class="row g-2 mb-2">
                    <div class="col"><label class="form-label small">Disbursement date</label><input type="date" name="disbursed_on" value="{{ today()->toDateString() }}" class="form-control"></div>
                    <div class="col"><label class="form-label small">First due date</label><input type="date" name="first_due_date" value="{{ today()->addMonthsNoOverflow(1 + (int) $loan->grace_period_months)->endOfMonth()->toDateString() }}" class="form-control">
                        @if($loan->grace_period_months)<div class="form-text">Includes {{ $loan->grace_period_months }} month(s) grace.</div>@endif</div>
                </div>
                <input name="external_ref" class="form-control mb-2" placeholder="Bank/M-Pesa/cheque ref (optional)">
                <p class="small text-muted">Fees deducted: processing {{ $loan->product->processing_fee_rate }}%, insurance {{ $loan->product->insurance_rate }}%.</p>
                <button class="btn btn-success w-100">Disburse KES {{ money($loan->amount_approved) }}</button></form>
        </div></div>
    @endif

    @if($loan->status === 'disbursed' && $u->hasRole('teller', 'accountant'))
        <div class="card mb-3"><div class="card-body"><h6>Post repayment</h6>
            <form method="POST" action="{{ route('loans.repay', $loan) }}">@csrf
                <input type="number" step="0.01" name="amount" class="form-control mb-2" placeholder="Amount" required>
                <div class="row g-2 mb-2">
                    <div class="col"><select name="channel" class="form-select">@foreach(['cash', 'mpesa', 'bank', 'payroll', 'cheque'] as $c)<option>{{ $c }}</option>@endforeach</select></div>
                    <div class="col"><input type="date" name="paid_on" value="{{ today()->toDateString() }}" class="form-control"></div>
                </div>
                <input name="external_ref" class="form-control mb-2" placeholder="M-Pesa code / slip no.">
                <button class="btn btn-primary w-100">Post repayment</button></form>
            <div class="small text-muted mt-2">Arrears now: KES {{ money($loan->arrears()) }}</div>
        </div></div>
    @endif

    @if(in_array($loan->status, ['disbursed', 'written_off']))
        @php($reminders = \App\Models\ReminderLog::where('loan_id', $loan->id)->latest()->take(6)->get())
        <div class="card mb-3"><div class="card-body"><h6><i class="bi bi-alarm"></i> Reminders</h6>
            @forelse($reminders as $rl)
                <div class="small border-bottom py-1"><span class="text-muted">{{ $rl->created_at->format('d/m H:i') }}</span> @if($rl->recipient === 'guarantor')<span class="badge text-bg-warning">guarantor</span>@endif
                    <span class="badge text-bg-{{ $rl->status === 'sent' ? 'success' : 'danger' }}">{{ $rl->status }}</span><br>{{ \Illuminate\Support\Str::limit($rl->message, 110) }}</div>
            @empty<div class="small text-muted">No reminders sent yet. Automatic reminders go out each morning.</div>@endforelse
            @if($u->hasRole('manager', 'loan_officer', 'accountant'))
            <form method="POST" action="{{ route('loans.remind', $loan) }}" class="mt-2">@csrf
                <textarea name="message" rows="2" maxlength="480" class="form-control form-control-sm mb-1" placeholder="Leave blank for the standard {{ $loan->daysInArrears() ? 'arrears' : 'due date' }} message"></textarea>
                <button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-send"></i> Send SMS reminder now</button></form>
            @endif
        </div></div>
    @endif

    @if($loan->status === 'disbursed' && $u->hasRole('manager'))
        <div class="card mb-3"><div class="card-body"><h6>Reschedule / restructure</h6>
            <form method="POST" action="{{ route('loans.reschedule', $loan) }}" data-confirm="Re-amortise the remaining principal of KES {{ money($loan->principal_balance) }}?">@csrf
                <div class="row g-2 mb-2">
                    <div class="col"><label class="form-label small">New period (months)</label><input type="number" name="period" class="form-control" required></div>
                    <div class="col"><label class="form-label small">New rate % (optional)</label><input type="number" step="0.01" name="interest_rate" placeholder="{{ $loan->interest_rate }}" class="form-control"></div>
                </div>
                <label class="form-label small">First installment due</label><input type="date" name="first_due_date" value="{{ today()->addMonthNoOverflow()->endOfMonth()->toDateString() }}" class="form-control mb-2">
                <input name="reason" class="form-control mb-2" placeholder="Reason / committee minute ref" required>
                <button class="btn btn-outline-primary w-100">Reschedule</button>
            </form>
            <p class="small text-muted mt-2 mb-0">Unpaid installments are replaced by a new schedule on the outstanding principal. Penalties carry forward. Rescheduled {{ $loan->reschedule_count }} time(s).</p>
        </div></div>
    @endif

    @if($loan->status === 'disbursed' && $u->hasRole('manager') && $loan->daysInArrears() > 90)
        <div class="card mb-3 border-danger"><div class="card-body"><h6 class="text-danger">Default recovery</h6>
            <p class="small">Recover the outstanding balance from guarantors' deposits, pro-rata to their guarantee.</p>
            <form method="POST" action="{{ route('loans.recover', $loan) }}" data-confirm="Debit guarantors' deposits to clear this loan?">@csrf<button class="btn btn-outline-danger w-100">Recover from guarantors</button></form>
        </div></div>
    @endif

    @if($loan->status === 'disbursed' && $u->hasRole('manager', 'accountant'))
        <div class="card mb-3"><div class="card-body"><h6>Charge a fine</h6>
            <form method="POST" action="{{ route('loans.fine', $loan) }}" data-confirm="Charge this fine to the member's loan?">@csrf
                <div class="row g-1 mb-1">
                    <div class="col-6"><select name="category" class="form-select form-select-sm">@foreach(['late_penalty' => 'Late payment', 'bounced_cheque' => 'Bounced cheque', 'legal_fees' => 'Legal fees', 'collection_costs' => 'Collection costs', 'other' => 'Other'] as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                    <div class="col-6"><input type="number" step="0.01" name="amount" class="form-control form-control-sm" placeholder="Amount" required></div>
                </div>
                <input name="reason" class="form-control form-control-sm mb-1" placeholder="Reason" required>
                <button class="btn btn-sm btn-outline-danger w-100">Charge fine</button></form>
            <p class="small text-muted mt-1 mb-0">Added to the next unpaid installment and recovered before interest and principal.</p>
        </div></div>
    @endif

    @if(in_array($loan->status, ['disbursed']) && $u->hasRole('manager') && ($loan->penalty_balance > 0 || $loan->interest_balance > 0))
        <div class="card mb-3"><div class="card-body"><h6>Waive penalty / interest</h6>
            <form method="POST" action="{{ route('loans.waive', $loan) }}" data-confirm="Waive this amount? This cannot be undone.">@csrf
                <div class="row g-1 mb-1">
                    <div class="col-6"><select name="what" class="form-select form-select-sm"><option value="penalty">Penalty ({{ money($loan->penalty_balance) }})</option><option value="interest">Interest ({{ money($loan->interest_balance) }})</option><option value="both">Penalty then interest</option></select></div>
                    <div class="col-6"><input type="number" step="0.01" name="amount" class="form-control form-control-sm" placeholder="Amount" required></div>
                </div>
                <input name="reason" class="form-control form-control-sm mb-1" placeholder="Reason / committee minute" required>
                <button class="btn btn-sm btn-outline-success w-100">Waive</button></form>
        </div></div>
    @endif

    @if($loan->status === 'disbursed' && $u->hasRole('manager') && $u->isHq())
        <div class="card mb-3 border-danger"><div class="card-body"><h6 class="text-danger">Write off</h6>
            @if($loan->daysInArrears() >= (int) setting('write_off_min_days', 180))
                <form method="POST" action="{{ route('loans.write-off', $loan) }}" data-confirm="Write off KES {{ money($loan->outstanding) }}? The balance moves off the loan book against the provision.">@csrf
                    <input name="reason" class="form-control form-control-sm mb-1" placeholder="Board resolution / reason" required>
                    <button class="btn btn-sm btn-danger w-100">Write off loan</button></form>
                <p class="small text-muted mt-1 mb-0">The member still owes the balance. Later payments post to Bad Debts Recovered.</p>
            @else
                <p class="small text-muted mb-0">Available once the loan is {{ setting('write_off_min_days', 180) }}+ days in arrears (now {{ $loan->daysInArrears() }}).</p>
            @endif
        </div></div>
    @endif

    @if(in_array($loan->status, ['pending', 'guarantors_pending', 'appraised', 'approved']) && $u->hasRole('manager', 'loan_officer'))
        <div class="card"><div class="card-body"><h6>Reject</h6>
            <form method="POST" action="{{ route('loans.reject', $loan) }}" data-confirm="Reject this application?">@csrf
                <input name="reason" class="form-control mb-2" placeholder="Reason" required><button class="btn btn-outline-danger w-100">Reject</button></form>
        </div></div>
    @endif
</div>
</div>
@endsection
@push('scripts')
<script>
// live guarantor eligibility check (limits, arrears, blocked, max loans, mutual guarantees)
document.querySelectorAll('form').forEach(f => {
    const sel = f.querySelector('select.guarantor-pick'); if (!sel) return;
    const amt = document.getElementById(sel.dataset.amountTarget), out = f.querySelector('.g-check');
    const run = () => {
        if (!sel.value || !+amt.value) { out.innerHTML = ''; return; }
        fetch(`{{ route('guarantors.check') }}?member_id=${sel.value}&amount=${amt.value}&borrower_id={{ $loan->member_id }}&loan_id={{ $loan->id }}`, {headers: {Accept: 'application/json'}})
            .then(r => r.json()).then(e => {
                out.innerHTML = (e.eligible ? `<span class="text-success">✔ Eligible · free capacity KES ${Number(e.capacity).toLocaleString()}</span>` : '')
                    + e.issues.map(i => `<div class="text-danger">✖ ${i}</div>`).join('') + e.warnings.map(w => `<div class="text-warning-emphasis">⚠ ${w}</div>`).join('');
            });
    };
    sel.addEventListener('change', run); amt.addEventListener('input', run);
});
(() => {
    const g = document.getElementById('affGross'), o = document.getElementById('affOther'), out = document.getElementById('affResult');
    if (!g) return;
    let t;
    const run = () => { clearTimeout(t); if (!+g.value) return; t = setTimeout(() =>
        fetch(`{{ route('loans.affordability', $loan) }}?gross_pay=${g.value}&other_deductions=${o.value || 0}`, {headers: {Accept: 'application/json'}})
            .then(r => r.json()).then(a => {
                const f = v => Number(v).toLocaleString(undefined, {maximumFractionDigits: 0});
                out.className = 'small mb-2 ' + (a.ok ? 'text-success' : 'text-danger');
                out.innerHTML = `${a.ok ? '✔ Passes' : '✖ Fails'}: installment ${f(a.installment)} + other ${f(o.value || 0)} = <strong>${a.ratio}%</strong> of gross (limit ${a.limit}%). Max affordable installment ${f(a.max_installment)} · take-home after loan ${f(a.take_home)}.`;
            }), 400); };
    g.addEventListener('input', run); o.addEventListener('input', run); run();
})();
</script>
@endpush
