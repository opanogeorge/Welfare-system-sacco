@extends('layouts.app')
@section('title', 'Welfare Benefits & Monthly Fund')
@section('content')
@php($mgr = auth()->user()->hasRole('manager'))
<div class="row g-3">
<div class="col-lg-6">
    <div class="card"><div class="card-body">
        <h6>Benefit schedule <small class="text-muted">· what the fund pays per event</small></h6>
        <table class="table table-sm align-middle">
            <thead><tr><th>Event</th><th>Relationship</th><th class="text-end">Amount</th><th></th></tr></thead>
            @foreach($benefits as $b)
                <tr class="{{ $b->is_active ? '' : 'text-muted' }}">
                    @if($mgr)
                    <td colspan="4"><form method="POST" action="{{ route('welfare.benefits.save') }}" class="d-flex gap-1 align-items-center">@csrf<input type="hidden" name="id" value="{{ $b->id }}">
                        <select name="type" class="form-select form-select-sm">@foreach(\App\Models\WelfareEvent::TYPES as $k => $v)<option value="{{ $k }}" @selected($b->type === $k)>{{ $v }}</option>@endforeach</select>
                        <select name="relationship" class="form-select form-select-sm"><option value="">Any</option>@foreach(\App\Models\WelfareEvent::RELATIONSHIPS as $k => $v)<option value="{{ $k }}" @selected($b->relationship === $k)>{{ $v }}</option>@endforeach</select>
                        <input type="number" name="amount" value="{{ $b->amount + 0 }}" class="form-control form-control-sm text-end" style="max-width:120px">
                        <input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="form-check-input" @checked($b->is_active) title="Active">
                        <button class="btn btn-sm btn-outline-success"><i class="bi bi-check"></i></button>
                    </form></td>
                    <td><form method="POST" action="{{ route('welfare.benefits.delete', $b) }}" data-confirm="Remove this benefit?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form></td>
                    @else
                    <td>{{ \App\Models\WelfareEvent::TYPES[$b->type] ?? $b->type }}</td><td>{{ $b->relationship ? \App\Models\WelfareEvent::RELATIONSHIPS[$b->relationship] ?? $b->relationship : 'Any' }}</td><td class="text-money">{{ money($b->amount) }}</td><td></td>
                    @endif
                </tr>
            @endforeach
        </table>
        @if($mgr)
        <form method="POST" action="{{ route('welfare.benefits.save') }}" class="d-flex gap-1">@csrf
            <select name="type" class="form-select form-select-sm">@foreach(\App\Models\WelfareEvent::TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            <select name="relationship" class="form-select form-select-sm"><option value="">Any</option>@foreach(\App\Models\WelfareEvent::RELATIONSHIPS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            <input type="number" name="amount" class="form-control form-control-sm" placeholder="Amount" required style="max-width:120px">
            <button class="btn btn-sm btn-primary">Add</button>
        </form>
        @endif
    </div></div>
</div>
<div class="col-lg-6">
    <div class="card mb-3"><div class="card-body">
        <h6>Monthly welfare contribution</h6>
        @if((float) setting('welfare_monthly_amount', 0) > 0)
            <p class="small mb-2">KES <strong>{{ money(setting('welfare_monthly_amount'), 0) }}</strong> per active member, charged on day {{ setting('welfare_monthly_day', 1) }} of every month from {{ setting('welfare_monthly_source', 'SAV') === 'DEP' ? 'deposits' : 'savings' }}. Members without enough balance pay by M-Pesa to <code>member no.-WLF</code>.</p>
            @if(auth()->user()->hasRole('manager', 'accountant'))
            <form method="POST" action="{{ route('welfare.monthly') }}" class="d-flex gap-1" data-confirm="Charge / retry the monthly welfare contribution for this month?">@csrf
                <input type="month" name="period" value="{{ now()->format('Y-m') }}" class="form-control form-control-sm" style="max-width:170px">
                <button class="btn btn-sm btn-outline-primary">Charge / retry now</button></form>
            @endif
        @else
            <p class="small text-muted mb-0">Switched off. To build a welfare reserve, set <em>Monthly welfare contribution</em> in Administration → Settings.</p>
        @endif
        @if($byPeriod->count())
            <table class="table table-sm small mt-3 mb-0"><thead><tr><th>Month</th><th class="text-end">Paid</th><th class="text-end">Unpaid</th></tr></thead>
                @foreach($byPeriod as $period => $rows)
                    <tr><td>{{ $period }}</td><td class="text-money">{{ money($rows->where('status', 'paid')->sum('total'), 0) }} ({{ $rows->where('status', 'paid')->sum('n') }})</td>
                        <td class="text-money text-danger">{{ money($rows->where('status', 'due')->sum('total'), 0) }} ({{ $rows->where('status', 'due')->sum('n') }})</td></tr>
                @endforeach
            </table>
        @endif
    </div></div>
</div>
</div>
@endsection
