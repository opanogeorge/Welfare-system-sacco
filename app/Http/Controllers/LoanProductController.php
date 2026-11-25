<?php

namespace App\Http\Controllers;

use App\Models\LoanProduct;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanProductController extends Controller
{
    public function index()
    {
        $products = LoanProduct::withCount(['loans as running' => fn ($q) => $q->where('status', 'disbursed')])->orderBy('name')->get();
        return view('loan-products.index', compact('products'));
    }

    public function create()
    {
        return view('loan-products.form', ['product' => new LoanProduct(['interest_method' => 'reducing', 'is_active' => true, 'deposit_multiplier' => 3])]);
    }

    public function store(Request $request)
    {
        LoanProduct::create($this->validated($request));
        return redirect()->route('loan-products.index')->with('success', 'Loan product created.');
    }

    public function edit(LoanProduct $loanProduct)
    {
        return view('loan-products.form', ['product' => $loanProduct]);
    }

    public function update(Request $request, LoanProduct $loanProduct)
    {
        $loanProduct->update($this->validated($request, $loanProduct));
        return redirect()->route('loan-products.index')->with('success', 'Loan product updated. Running loans keep their original terms.');
    }

    private function validated(Request $request, ?LoanProduct $p = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('loan_products')->ignore($p)],
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'interest_method' => 'required|in:flat,reducing,equal_principal',
            'grace_period_months' => 'nullable|integer|min:0|max:12',
            'max_active_per_member' => 'nullable|integer|min:0|max:10',
            'max_deduction_ratio' => 'nullable|numeric|min:10|max:100',
            'min_period' => 'required|integer|min:1',
            'max_period' => 'required|integer|gte:min_period',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gte:min_amount',
            'deposit_multiplier' => 'required|numeric|min:0',
            'processing_fee_rate' => 'required|numeric|min:0|max:100',
            'insurance_rate' => 'required|numeric|min:0|max:100',
            'penalty_rate' => 'required|numeric|min:0|max:100',
            'min_guarantors' => 'required|integer|min:0|max:10',
            'min_membership_months' => 'required|integer|min:0',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['allow_top_up'] = $request->boolean('allow_top_up');
        $data['collateral_required'] = $request->boolean('collateral_required');
        $data['grace_period_months'] = $data['grace_period_months'] ?? 0;
        $data['max_active_per_member'] = $data['max_active_per_member'] ?? 0;
        $data['max_deduction_ratio'] = $data['max_deduction_ratio'] ?? 66.67;
        return $data;
    }
}
