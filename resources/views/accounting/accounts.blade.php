@extends('layouts.app')
@section('title', 'Chart of Accounts')
@section('content')
@include('accounting._nav')
<div class="row g-3">
<div class="col-lg-8"><div class="card"><div class="card-body">
    <table class="table table-sm table-hover align-middle">
        <thead><tr><th>Code</th><th>Account</th><th>Type</th><th>System key</th><th class="text-end">Balance</th><th></th></tr></thead>
        @foreach($accounts->groupBy('type') as $type => $group)
            <tr class="table-light"><th colspan="6" class="text-uppercase small">{{ $type }}</th></tr>
            @foreach($group as $a)
                <tr class="{{ $a->is_active ? '' : 'text-muted' }}">
                    <td>{{ $a->code }}</td>
                    <td><a href="{{ route('accounting.ledger', $a) }}">{{ $a->name }}</a> @if($a->is_cash)<span class="badge text-bg-light">cash</span>@endif</td>
                    <td class="small">{{ $a->type }}</td><td class="small text-muted">{{ $a->system_key }}</td>
                    <td class="text-money">{{ money($a->bal) }}</td>
                    <td><button class="btn btn-sm btn-link p-0" data-bs-toggle="collapse" data-bs-target="#e{{ $a->id }}"><i class="bi bi-pencil"></i></button></td>
                </tr>
                <tr class="collapse" id="e{{ $a->id }}"><td colspan="6">
                    <form method="POST" action="{{ route('accounting.accounts.update', $a) }}" class="row g-1">@csrf @method('PUT')
                        <div class="col-2"><input name="code" value="{{ $a->code }}" class="form-control form-control-sm"></div>
                        <div class="col-4"><input name="name" value="{{ $a->name }}" class="form-control form-control-sm"></div>
                        <div class="col-2"><select name="type" class="form-select form-select-sm">@foreach(['asset','liability','equity','income','expense'] as $t)<option @selected($a->type === $t)>{{ $t }}</option>@endforeach</select></div>
                        <div class="col-3 small d-flex gap-2 align-items-center"><label><input type="checkbox" name="is_cash" value="1" @checked($a->is_cash)> cash</label><label><input type="checkbox" name="is_active" value="1" @checked($a->is_active)> active</label></div>
                        <div class="col-1"><button class="btn btn-sm btn-success w-100">Save</button></div>
                    </form>
                </td></tr>
            @endforeach
        @endforeach
    </table>
</div></div></div>
<div class="col-lg-4">
    <div class="card mb-3"><div class="card-body">
        <h6>Add account</h6>
        <form method="POST" action="{{ route('accounting.accounts') }}">@csrf
            <input name="code" class="form-control mb-2" placeholder="Code e.g. 5150" required>
            <input name="name" class="form-control mb-2" placeholder="Account name" required>
            <select name="type" class="form-select mb-2">@foreach(['asset','liability','equity','income','expense'] as $t)<option>{{ $t }}</option>@endforeach</select>
            <select name="parent_id" class="form-select mb-2"><option value="">No parent</option>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} {{ $a->name }}</option>@endforeach</select>
            <label class="small"><input type="checkbox" name="is_cash" value="1"> Cash / bank / mobile money account</label>
            <input type="hidden" name="is_active" value="1">
            <button class="btn btn-success w-100 mt-2">Add</button>
        </form>
    </div></div>
    @if(can_route('accounting.close-year'))
    <div class="card"><div class="card-body">
        <h6>Year-end close</h6>
        <p class="small text-muted">Transfers income and expenses to Retained Earnings and locks the books up to 31 Dec. Run after the audit adjustments are posted.</p>
        <form method="POST" action="{{ route('accounting.close-year') }}" data-confirm="Close the financial year? Postings on or before 31 Dec will be blocked.">@csrf
            <div class="input-group"><input type="number" name="year" value="{{ now()->year - 1 }}" class="form-control"><button class="btn btn-outline-danger">Close year</button></div>
        </form>
        <div class="small text-muted mt-2">Books locked until: <strong>{{ setting('books_locked_until') ?: '—' }}</strong></div>
    </div></div>
    @endif
</div>
</div>
@endsection
