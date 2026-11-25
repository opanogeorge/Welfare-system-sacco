@extends('layouts.app')
@section('title', $product->exists ? "Edit {$product->name}" : 'New Loan Product')
@section('content')
<div class="card"><div class="card-body">
<form method="POST" action="{{ $product->exists ? route('loan-products.update', $product) : route('loan-products.store') }}">
    @csrf @if($product->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-md-2"><label class="form-label">Code *</label><input name="code" value="{{ old('code', $product->code) }}" class="form-control text-uppercase" required></div>
        <div class="col-md-6"><label class="form-label">Name *</label><input name="name" value="{{ old('name', $product->name) }}" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label">Interest % p.a. *</label><input type="number" step="0.01" name="interest_rate" value="{{ old('interest_rate', $product->interest_rate) }}" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label">Method *</label><select name="interest_method" class="form-select">
            @foreach(\App\Models\Loan::METHODS as $k => $v)<option value="{{ $k }}" @selected(old('interest_method', $product->interest_method ?? 'reducing') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2">{{ old('description', $product->description) }}</textarea></div>
        @foreach([
            'min_period' => ['Min period (months)', 1], 'max_period' => ['Max period (months)', 1],
            'min_amount' => ['Min amount (KES)', 0.01], 'max_amount' => ['Max amount (KES)', 0.01],
            'deposit_multiplier' => ['Deposit multiplier (×)', 0.01], 'min_guarantors' => ['Min guarantors', 1],
            'processing_fee_rate' => ['Processing fee %', 0.01], 'insurance_rate' => ['Insurance %', 0.01],
            'penalty_rate' => ['Penalty % per month on arrears', 0.01], 'min_membership_months' => ['Min membership (months)', 1],
            'grace_period_months' => ['Grace period (months before 1st installment)', 1], 'max_active_per_member' => ['Max running loans of this product per member', 1],
            'max_deduction_ratio' => ['Max deductions as % of gross pay (⅔ rule)', 0.01],
        ] as $f => [$label, $step])
            <div class="col-md-3"><label class="form-label">{{ $label }}</label><input type="number" step="{{ $step }}" name="{{ $f }}" value="{{ old($f, $product->$f ?? ['max_deduction_ratio' => 66.67, 'max_active_per_member' => 1][$f] ?? 0) }}" class="form-control" required></div>
        @endforeach
        <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="act" @checked(old('is_active', $product->is_active))><label class="form-check-label" for="act">Active</label></div></div>
        <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input type="checkbox" name="allow_top_up" value="1" class="form-check-input" id="atu" @checked(old('allow_top_up', $product->allow_top_up ?? true))><label class="form-check-label" for="atu">Allow top-ups / refinancing</label></div></div>
        <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input type="checkbox" name="collateral_required" value="1" class="form-check-input" id="colr" @checked(old('collateral_required', $product->collateral_required))><label class="form-check-label" for="colr">Collateral required before approval</label></div></div>
    </div>
    <button class="btn btn-success mt-3">Save product</button>
    <a href="{{ route('loan-products.index') }}" class="btn btn-link mt-3">Cancel</a>
</form>
</div></div>
@endsection
