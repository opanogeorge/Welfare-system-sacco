@extends('layouts.app')
@section('title', 'Settings')
@section('content')
<div class="row g-3">
<div class="col-lg-5"><div class="card"><div class="card-body">
    <h6>System settings</h6>
    <form method="POST">@csrf @method('PUT')
        <div class="mb-3"><label class="form-label small mb-1">Colour theme</label>
            <div class="d-flex flex-wrap gap-2">
                @foreach(theme_presets() as $tk => $tp)
                    <label class="border rounded p-2 text-center small" style="cursor:pointer;min-width:92px">
                        <input type="radio" name="theme" value="{{ $tk }}" class="form-check-input" @checked(theme()['key'] === $tk)>
                        <div class="d-flex justify-content-center my-1"><span style="width:18px;height:18px;background:{{ $tp['brand'] }};border-radius:4px 0 0 4px"></span><span style="width:18px;height:18px;background:{{ $tp['brand2'] }}"></span><span style="width:18px;height:18px;background:{{ $tp['accent'] }};border-radius:0 4px 4px 0"></span></div>
                        {{ $tp['label'] }}
                    </label>
                @endforeach
            </div>
        </div>
        @foreach($keys as $k => $label)
            <div class="mb-2"><label class="form-label small mb-0">{{ $label }}</label><input name="{{ $k }}" value="{{ $values[$k] }}" class="form-control form-control-sm"></div>
        @endforeach
        <button class="btn btn-success mt-2">Save settings</button>
    </form>
</div></div></div>

<div class="col-lg-7">
<div class="card mb-3"><div class="card-body">
    <h6>Savings products (account types)</h6>
    <p class="small text-muted">Every member automatically gets one account of each active type. Interest rate % p.a. is credited monthly (for types that don't earn the annual interest-on-deposits).</p>
    @foreach($types->push(new \App\Models\AccountType(['is_active' => true])) as $t)
        <form method="POST" action="{{ $t->exists ? route('admin.account-types.update', $t) : route('admin.account-types.store') }}" class="border rounded p-2 mb-2 {{ $t->exists ? '' : 'bg-light' }}">
            @csrf @if($t->exists) @method('PUT') @endif
            <div class="row g-1 align-items-end small">
                <div class="col-2"><label>Code</label><input name="code" value="{{ $t->code }}" class="form-control form-control-sm" required></div>
                <div class="col-4"><label>Name</label><input name="name" value="{{ $t->name }}" class="form-control form-control-sm" placeholder="{{ $t->exists ? '' : 'e.g. Holiday Savings' }}" required></div>
                <div class="col-2"><label>Min bal.</label><input type="number" step="0.01" name="min_balance" value="{{ $t->min_balance ?? 0 }}" class="form-control form-control-sm"></div>
                <div class="col-2"><label>W/d fee</label><input type="number" step="0.01" name="withdrawal_fee" value="{{ $t->withdrawal_fee ?? 0 }}" class="form-control form-control-sm"></div>
                <div class="col-2"><label>Rate %</label><input type="number" step="0.01" name="interest_rate" value="{{ $t->interest_rate ?? 0 }}" class="form-control form-control-sm"></div>
                <div class="col-6"><label>GL account</label><select name="gl_account_id" class="form-select form-select-sm"><option value="">— choose —</option>@foreach($glAccounts as $g)<option value="{{ $g->id }}" @selected($t->gl_account_id == $g->id)>{{ $g->code }} {{ $g->name }}</option>@endforeach</select></div>
                <div class="col-3"><label>Monthly minimum</label><input type="number" step="0.01" name="monthly_minimum" value="{{ $t->monthly_minimum ?? 0 }}" class="form-control form-control-sm"></div>
                <div class="col-12 d-flex flex-wrap gap-3 mt-1">
                    @foreach(['withdrawable' => 'Withdrawable', 'counts_for_loan' => 'Counts for loan limit', 'earns_dividend' => 'Earns dividend', 'earns_interest' => 'Earns annual interest', 'is_active' => 'Active'] as $f => $l)
                        <label><input type="checkbox" name="{{ $f }}" value="1" @checked($t->$f)> {{ $l }}</label>
                    @endforeach
                    <button class="btn btn-sm btn-{{ $t->exists ? 'outline-secondary' : 'success' }} ms-auto">{{ $t->exists ? 'Update' : 'Add product' }}</button>
                </div>
            </div>
        </form>
    @endforeach
    <p class="small text-muted mb-0">A new savings product needs its own liability GL account; add it under Accounting → Chart of Accounts first.</p>
</div></div>

<div class="card"><div class="card-body">
    <h6>Member categories</h6>
    <table class="table table-sm small">@foreach($categories as $c)<tr><td>{{ $c->name }}</td><td class="text-muted">{{ $c->description }}</td><td>{{ $c->members_count }} members</td></tr>@endforeach</table>
    <form method="POST" action="{{ route('admin.categories.store') }}" class="d-flex gap-1">@csrf
        <input name="name" class="form-control form-control-sm" placeholder="Category name" required><input name="description" class="form-control form-control-sm" placeholder="Description"><button class="btn btn-sm btn-success">Add</button></form>
</div></div>
</div>
</div>
@endsection
