@extends('layouts.app')
@section('title', 'M-Pesa Payments (C2B)')
@section('content')
<div class="card"><div class="card-body">
    <div class="d-flex mb-3 gap-2">
        @foreach(['' => 'All', 'posted' => 'Posted', 'unmatched' => 'Unmatched', 'received' => 'Received'] as $k => $v)
            <a href="?status={{ $k }}" class="btn btn-sm {{ request('status', '') === $k ? 'btn-success' : 'btn-outline-secondary' }}">{{ $v }}</a>
        @endforeach
        <span class="ms-auto small text-muted align-self-center">Paybill {{ config('sacco.mpesa.paybill') }} · Account = member no., member no.-SHR/SAV, or loan no.</span>
    </div>
    <table class="table table-sm align-middle">
        <thead><tr><th>Time</th><th>M-Pesa Code</th><th>Payer</th><th>Phone</th><th>Account Ref</th><th class="text-end">Amount</th><th>Status</th><th></th></tr></thead>
        @forelse($rows as $r)
            <tr>
                <td class="small">{{ $r->trans_time?->format('d/m/Y H:i') }}</td><td>{{ $r->trans_id }}</td><td>{{ $r->payer_name }}</td><td>{{ $r->msisdn }}</td>
                <td>{{ $r->bill_ref }}</td><td class="text-money">{{ money($r->amount) }}</td>
                <td><span class="badge text-bg-{{ $r->status === 'posted' ? 'success' : ($r->status === 'unmatched' ? 'danger' : 'secondary') }}">{{ $r->status }}</span></td>
                <td>
                    @if($r->status !== 'posted')
                        <form method="POST" action="{{ route('mpesa.assign', $r) }}" class="d-flex gap-1">@csrf
                            <input name="bill_ref" class="form-control form-control-sm" placeholder="MBR00001 or LN…" style="width:150px" required>
                            <button class="btn btn-sm btn-outline-success">Post</button></form>
                    @endif
                </td>
            </tr>
        @empty <tr><td colspan="8" class="text-muted text-center py-3">No M-Pesa payments received yet.</td></tr> @endforelse
    </table>
    {{ $rows->links() }}
</div></div>
@endsection
