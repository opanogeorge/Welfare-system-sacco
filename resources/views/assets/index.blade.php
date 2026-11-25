@extends('layouts.app')
@section('title', 'Fixed Assets')
@section('content')
<div class="card mb-3"><div class="card-body">
    <div class="d-flex mb-2 gap-2">
        <a href="?status=active" class="btn btn-sm btn-outline-secondary">Active</a><a href="?status=disposed" class="btn btn-sm btn-outline-secondary">Disposed</a>
        <form method="POST" action="{{ route('assets.depreciate') }}" class="ms-auto d-flex gap-1">@csrf
            <input type="month" name="month" value="{{ now()->subMonthNoOverflow()->format('Y-m') }}" class="form-control form-control-sm"><button class="btn btn-sm btn-outline-success text-nowrap">Post depreciation</button></form>
    </div>
    <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
        <thead><tr><th>Code</th><th>Asset</th><th>Category</th><th>Location / custodian</th><th>Bought</th><th class="text-end">Cost</th><th class="text-end">Acc. dep.</th><th class="text-end">NBV</th><th>Method</th><th>Maintenance</th><th></th></tr></thead>
        @forelse($assets as $a)
            <tr><td>{{ $a->code }}</td><td>{{ $a->name }}</td><td class="small">{{ $a->category }}</td><td class="small">{{ $a->location }} {{ $a->custodian ? '· ' . $a->custodian : '' }}</td>
                <td>{{ $a->purchase_date->format('d/m/Y') }}</td><td class="text-money">{{ money($a->cost) }}</td><td class="text-money">{{ money($a->accumulated_depreciation) }}</td>
                <td class="text-money fw-semibold">{{ money($a->netBookValue()) }}</td><td class="small">{{ $a->method === 'straight_line' ? "SL {$a->useful_life_years}y" : "RB {$a->rate}%" }}</td>
                <td class="small {{ $a->next_maintenance_on?->isPast() ? 'text-danger' : '' }}">{{ $a->next_maintenance_on?->format('d/m/Y') }}</td>
                <td>@if($a->status === 'active')<button class="btn btn-sm btn-link p-0" data-bs-toggle="collapse" data-bs-target="#a{{ $a->id }}"><i class="bi bi-three-dots"></i></button>@else <span class="badge text-bg-secondary">disposed</span>@endif</td></tr>
            @if($a->status === 'active')
            <tr class="collapse" id="a{{ $a->id }}"><td colspan="11" class="bg-light">
                <div class="row g-2">
                    <form method="POST" action="{{ route('assets.update', $a) }}" class="col-md-6 d-flex gap-1">@csrf @method('PUT')
                        <input name="location" value="{{ $a->location }}" class="form-control form-control-sm" placeholder="Location">
                        <input name="custodian" value="{{ $a->custodian }}" class="form-control form-control-sm" placeholder="Custodian">
                        <input type="date" name="next_maintenance_on" value="{{ $a->next_maintenance_on?->toDateString() }}" class="form-control form-control-sm">
                        <button class="btn btn-sm btn-outline-secondary">Transfer / update</button></form>
                    @if(can_route('assets.dispose'))
                    <form method="POST" action="{{ route('assets.dispose', $a) }}" class="col-md-6 d-flex gap-1" data-confirm="Dispose of this asset?">@csrf
                        <input type="number" step="0.01" name="proceeds" class="form-control form-control-sm" placeholder="Proceeds" required>
                        <input type="date" name="disposed_on" value="{{ today()->toDateString() }}" class="form-control form-control-sm">
                        <select name="received_into" class="form-select form-select-sm">@foreach($cashAccounts as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                        <button class="btn btn-sm btn-outline-danger">Dispose</button></form>
                    @endif
                </div>
            </td></tr>
            @endif
        @empty <tr><td colspan="11" class="text-muted">No assets registered.</td></tr> @endforelse
        <tfoot class="fw-bold"><tr><td colspan="5">Totals</td><td class="text-money">{{ money($assets->sum('cost')) }}</td><td class="text-money">{{ money($assets->sum('accumulated_depreciation')) }}</td><td class="text-money">{{ money($assets->sum(fn ($a) => $a->netBookValue())) }}</td><td colspan="3"></td></tr></tfoot>
    </table>
    </div>
</div></div>
<div class="card"><div class="card-body">
    <h6>Register asset</h6>
    <form method="POST" action="{{ route('assets.store') }}" class="row g-2">@csrf
        <div class="col-md-2"><input name="code" class="form-control" placeholder="Tag / code" required></div>
        <div class="col-md-4"><input name="name" class="form-control" placeholder="Description" required></div>
        <div class="col-md-3"><select name="category" class="form-select">@foreach($categories as $c)<option>{{ $c }}</option>@endforeach</select></div>
        <div class="col-md-3"><input type="date" name="purchase_date" value="{{ today()->toDateString() }}" class="form-control" required></div>
        <div class="col-md-2"><input type="number" step="0.01" name="cost" class="form-control" placeholder="Cost" required></div>
        <div class="col-md-2"><input type="number" step="0.01" name="salvage_value" class="form-control" placeholder="Salvage value"></div>
        <div class="col-md-2"><select name="method" class="form-select"><option value="straight_line">Straight line</option><option value="reducing_balance">Reducing balance</option></select></div>
        <div class="col-md-1"><input type="number" name="useful_life_years" value="5" class="form-control" title="Useful life (years)"></div>
        <div class="col-md-1"><input type="number" step="0.01" name="rate" class="form-control" placeholder="RB %"></div>
        <div class="col-md-2"><input name="location" class="form-control" placeholder="Location"></div>
        <div class="col-md-2"><input name="custodian" class="form-control" placeholder="Custodian"></div>
        <div class="col-md-3"><select name="paid_from" class="form-select"><option value="">Unpaid (Accounts payable)</option>@foreach($cashAccounts as $c)<option value="{{ $c->id }}">Paid from {{ $c->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><input type="date" name="next_maintenance_on" class="form-control" title="Next maintenance"></div>
        <div class="col-md-2"><button class="btn btn-success w-100">Register</button></div>
    </form>
</div></div>
@endsection
