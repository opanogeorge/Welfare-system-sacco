{{-- $rows: collection of LoanSchedule models or arrays from LoanCalculator --}}
<div class="table-responsive">
<table class="table table-sm table-striped mb-0">
    <thead class="table-light"><tr><th>#</th><th>Due date</th><th class="text-end">Opening</th><th class="text-end">Principal</th><th class="text-end">Interest</th>
        @if($live ?? false)<th class="text-end">Penalty</th>@endif<th class="text-end">Installment</th><th class="text-end">Closing</th>
        @if($live ?? false)<th class="text-end">Paid</th><th>Status</th>@endif</tr></thead>
    @foreach($rows as $r)
        @php($r = is_array($r) ? (object) $r : $r)
        <tr class="{{ ($live ?? false) && $r->status === 'overdue' ? 'table-danger' : '' }}">
            <td>{{ $r->installment_no }}</td><td>{{ \Carbon\Carbon::parse($r->due_date)->format('d/m/Y') }}</td>
            <td class="text-money">{{ money($r->opening_balance) }}</td><td class="text-money">{{ money($r->principal_due) }}</td><td class="text-money">{{ money($r->interest_due) }}</td>
            @if($live ?? false)<td class="text-money">{{ money($r->penalty_due) }}</td>@endif
            <td class="text-money fw-semibold">{{ money($r->total_due + (($live ?? false) ? $r->penalty_due : 0)) }}</td>
            <td class="text-money">{{ money($r->closing_balance) }}</td>
            @if($live ?? false)
                <td class="text-money">{{ money($r->principal_paid + $r->interest_paid + $r->penalty_paid) }}</td>
                <td><span class="badge text-bg-{{ ['paid' => 'success', 'partial' => 'info', 'overdue' => 'danger'][$r->status] ?? 'light' }}">{{ $r->status }}</span></td>
            @endif
        </tr>
    @endforeach
</table>
</div>
