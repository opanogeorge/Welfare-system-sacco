<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MpesaTransaction;
use App\Services\MpesaPoster;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MpesaController extends Controller
{
    public function validation(Request $request)
    {
        $this->guard($request);
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function confirmation(Request $request, MpesaPoster $poster)
    {
        $this->guard($request);
        $p = $request->all();

        if (empty($p['TransID']) || MpesaTransaction::where('trans_id', $p['TransID'])->exists()) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Duplicate ignored']);
        }

        $m = MpesaTransaction::create([
            'trans_id'   => $p['TransID'],
            'trans_type' => $p['TransactionType'] ?? null,
            'amount'     => (float) ($p['TransAmount'] ?? 0),
            'bill_ref'   => $p['BillRefNumber'] ?? null,
            'msisdn'     => $p['MSISDN'] ?? null,
            'payer_name' => trim(($p['FirstName'] ?? '') . ' ' . ($p['MiddleName'] ?? '') . ' ' . ($p['LastName'] ?? '')),
            'trans_time' => isset($p['TransTime']) ? Carbon::createFromFormat('YmdHis', $p['TransTime']) : now(),
            'payload'    => $p,
        ]);
        $poster->post($m);

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    /** STK Push result. Success => record as an M-Pesa payment and route like C2B. */
    public function stkCallback(Request $request, MpesaPoster $poster)
    {
        $this->guard($request);
        $cb = $request->input('Body.stkCallback', []);
        $checkout = $cb['CheckoutRequestID'] ?? '';
        if (($cb['ResultCode'] ?? 1) != 0) {
            // e.g. 1032 cancelled by user, 1037 phone unreachable, 2001 wrong PIN
            \Illuminate\Support\Facades\Cache::put("stk_result_$checkout", ['status' => 'failed', 'message' => $cb['ResultDesc'] ?? 'Payment not completed'], now()->addHours(2));
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Noted']);
        }
        $meta = collect($cb['CallbackMetadata']['Item'] ?? [])->pluck('Value', 'Name');
        $receipt = $meta['MpesaReceiptNumber'] ?? null;
        if (! $receipt || MpesaTransaction::where('trans_id', $receipt)->exists()) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Duplicate']);
        }
        $ctx = \Illuminate\Support\Facades\Cache::pull('stk_' . ($cb['CheckoutRequestID'] ?? ''), []);

        $m = MpesaTransaction::create([
            'trans_id'   => $receipt,
            'trans_type' => 'STK',
            'amount'     => (float) ($meta['Amount'] ?? 0),
            'bill_ref'   => $ctx['ref'] ?? null,
            'msisdn'     => (string) ($meta['PhoneNumber'] ?? ($ctx['phone'] ?? '')),
            'trans_time' => isset($meta['TransactionDate']) ? Carbon::createFromFormat('YmdHis', (string) $meta['TransactionDate']) : now(),
            'payload'    => $request->all(),
        ]);
        $poster->post($m);
        \Illuminate\Support\Facades\Cache::put("stk_result_$checkout", ['status' => $m->fresh()->status === 'posted' ? 'success' : 'received',
            'receipt' => $receipt, 'amount' => (float) $m->amount], now()->addHours(2));
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    private function guard(Request $request): void
    {
        $token = config('sacco.mpesa.callback_token');
        abort_if($token && ! hash_equals($token, (string) $request->query('token')), 403);
    }
}
