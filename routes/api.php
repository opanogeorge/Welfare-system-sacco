<?php

use App\Http\Controllers\Api\MpesaController;
use App\Http\Controllers\Api\PartnerApiController;
use Illuminate\Support\Facades\Route;

/*
| Safaricom Daraja callbacks. Safaricom rejects URLs containing the words
| "mpesa" or "safaricom", so these live under /api/payments.
| Register them from Admin > Integrations > M-Pesa (button "Register C2B URLs").
*/
Route::prefix('payments')->group(function () {
    Route::post('c2b/validation', [MpesaController::class, 'validation']);
    Route::post('c2b/confirmation', [MpesaController::class, 'confirmation']);
    Route::post('stk/callback', [MpesaController::class, 'stkCallback']);
});

/*
| Partner API for banks, agents, HR/payroll and BI systems.
| Keys and scopes: Admin > API & Webhooks.   Header: Authorization: Bearer sk_...
*/
Route::prefix('v1')->group(function () {
    Route::get('ping', [PartnerApiController::class, 'ping'])->middleware('api.client');
    Route::get('members/{memberNo}', [PartnerApiController::class, 'member'])->middleware('api.client:members.read');
    Route::get('members/{memberNo}/balances', [PartnerApiController::class, 'balances'])->middleware('api.client:balances.read');
    Route::post('members', [PartnerApiController::class, 'createMember'])->middleware('api.client:members.write');
    Route::get('loans/{loanNo}', [PartnerApiController::class, 'loan'])->middleware('api.client:loans.read');
    Route::post('payments', [PartnerApiController::class, 'pay'])->middleware('api.client:payments.write');
    Route::get('payments/{reference}', [PartnerApiController::class, 'payment'])->middleware('api.client:payments.write');
    Route::get('transactions', [PartnerApiController::class, 'transactions'])->middleware('api.client:transactions.read');
});
