@extends('layouts.app')
@section('title', 'Partner API documentation')
@section('content')
@php($base = rtrim(\App\Support\Integrations::publicUrl(), '/') . '/api/v1')
<div class="card"><div class="card-body" style="max-width:980px">
<h5>Authentication</h5>
<p>Every request needs an API key created under <a href="{{ route('admin.api') }}">API &amp; Webhooks</a>, sent as a bearer token over HTTPS:</p>
<pre class="bg-light p-2 rounded small">Authorization: Bearer sk_xxxxxxxxxxxxxxxxxxxxxxxx
Accept: application/json</pre>
<p class="small">Base URL: <code>{{ $base }}</code> · Limit: 120 requests/minute per key · Errors return JSON <code>{"error": "..."}</code> with status 401 (bad key), 403 (IP or scope), 404, 422 (validation), 429 (rate limit).</p>

<h5 class="mt-4">Endpoints</h5>
<table class="table table-sm">
    <thead><tr><th>Method &amp; path</th><th>Scope</th><th>Purpose</th></tr></thead>
    <tr><td><code>GET /ping</code></td><td>any</td><td>Check the key works</td></tr>
    <tr><td><code>GET /members/{member_no}</code></td><td>members.read</td><td>Validate an account before accepting money: name, status, accounts</td></tr>
    <tr><td><code>GET /members/{member_no}/balances</code></td><td>balances.read</td><td>Account and running-loan balances</td></tr>
    <tr><td><code>POST /members</code></td><td>members.write</td><td>Submit a member application (lands as <em>pending</em> for staff approval)</td></tr>
    <tr><td><code>GET /loans/{loan_no}</code></td><td>loans.read</td><td>Balance, arrears and next installment</td></tr>
    <tr><td><code>POST /payments</code></td><td>payments.write</td><td>Post a collection to a member account or loan</td></tr>
    <tr><td><code>GET /payments/{reference}</code></td><td>payments.write</td><td>Status of a payment you posted</td></tr>
    <tr><td><code>GET /transactions?since=YYYY-MM-DD&amp;page=1</code></td><td>transactions.read</td><td>Paged transaction export (max 500/page) for BI or reconciliation</td></tr>
</table>

<h6>Example: post a bank collection</h6>
<pre class="bg-light p-2 rounded small">curl -X POST {{ $base }}/payments \
  -H "Authorization: Bearer sk_..." -H "Content-Type: application/json" \
  -d '{"reference":"EQB-20261124-00017","member_no":"MBR00012","account":"DEP","amount":5000,"paid_at":"2026-11-24","payer":"Jane Achieng"}'

# to repay a loan instead:  {"reference":"...","loan_no":"LN202600031","amount":7400}

201 Created
{"reference":"EQB-20261124-00017","status":"posted","target":"MBR00012","account":"DEP","amount":5000,"transaction_id":4821,...}</pre>
<p class="small"><strong>Idempotent:</strong> sending the same <code>reference</code> again returns the original result with <code>"duplicate": true</code> and never posts twice. Payments post through the <em>Bank</em> channel (Dr Bank / Cr member account or loan) and trigger the member's SMS confirmation.</p>

<h6>Example: submit a member from an HR system</h6>
<pre class="bg-light p-2 rounded small">POST /members
{"first_name":"Peter","last_name":"Ouma","national_id":"31234567","phone":"0712345678","date_of_birth":"1990-05-14",
 "gender":"male","employer_code":"JOOTRH","payroll_no":"P1043","monthly_contribution":3000}</pre>

<h5 class="mt-4">Webhooks</h5>
<p>Subscribe a client to events and the system POSTs JSON to its webhook URL when they happen:</p>
<table class="table table-sm">@foreach(\App\Models\ApiClient::EVENTS as $k => $v)<tr><td><code>{{ $k }}</code></td><td>{{ $v }}</td></tr>@endforeach</table>
<pre class="bg-light p-2 rounded small">POST https://partner.example/webhooks
X-Sacco-Event: loan.disbursed
X-Sacco-Delivery: 912
X-Sacco-Signature: sha256=5d41402abc4b2a76b9719d911017c592...

{"id":"6f1c…","event":"loan.disbursed","created_at":"2026-11-24T10:15:02+03:00","sacco":"{{ sacco_name() }}",
 "data":{"loan_no":"LN202600031","member_no":"MBR00012","principal":100000,"net":98500,"channel":"savings","date":"2026-11-24"}}</pre>
<p class="small">Verify every webhook: compute <code>HMAC-SHA256(raw request body, webhook secret)</code> and compare it with the <code>X-Sacco-Signature</code> header using a constant-time comparison. Reply with any 2xx within 5 seconds. Failed deliveries retry after 1 min, 5 min, 30 min, 2 h and 12 h; you can also re-send from the delivery log.</p>
<pre class="bg-light p-2 rounded small">// PHP receiver
$sig = 'sha256=' . hash_hmac('sha256', file_get_contents('php://input'), $secret);
if (! hash_equals($sig, $_SERVER['HTTP_X_SACCO_SIGNATURE'] ?? '')) { http_response_code(401); exit; }</pre>
</div></div>
@endsection
