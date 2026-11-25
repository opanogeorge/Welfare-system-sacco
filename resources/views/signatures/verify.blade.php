@extends('layouts.public')
@section('title', 'Verify a signed document')
@section('content')
<div class="row justify-content-center"><div class="col-md-7">
<div class="card"><div class="card-body p-4">
    <h1 class="h4"><i class="bi bi-patch-check"></i> Verify a signed document</h1>
    <p class="text-muted small">Enter the verification code printed on the document (for example <code>AB12-CD34-EF56</code>).</p>
    <form method="GET" action="{{ route('signatures.verify') }}" class="d-flex gap-2 mb-4">
        <input name="code" value="{{ $code }}" class="form-control text-uppercase font-monospace" placeholder="XXXX-XXXX-XXXX" required>
        <button class="btn btn-success">Verify</button>
    </form>
    @if($code)
        @if(! $result)
            <div class="alert alert-danger"><i class="bi bi-x-octagon"></i> No signature found with this code. The document may not be genuine.</div>
        @elseif($result['intact'])
            @php($s = $result['signature'])
            <div class="alert alert-success"><i class="bi bi-shield-check"></i> <strong>Genuine and unaltered.</strong> The signed terms match our records.</div>
            <table class="table table-sm">
                <tr><td class="text-muted">Document</td><td>{{ ucwords(str_replace('_', ' ', $s->purpose)) }}</td></tr>
                <tr><td class="text-muted">Signed by</td><td>{{ $s->signer_name }} ({{ $s->signer_role }})</td></tr>
                <tr><td class="text-muted">Signed on</td><td>{{ $s->signed_at->format('d M Y, H:i') }} EAT</td></tr>
                <tr><td class="text-muted">Phone verified</td><td>{{ $s->otp_verified ? 'Yes' : 'No' }}</td></tr>
                <tr><td class="text-muted">Document fingerprint</td><td class="font-monospace small text-break">{{ $s->document_hash }}</td></tr>
            </table>
        @else
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> <strong>Signature found, but the terms have changed since signing.</strong> Contact the SACCO before relying on this document.</div>
        @endif
    @endif
</div></div>
</div></div>
@endsection
