{{-- Application progress tracker. $loan required; $compact = small version for lists --}}
@php
    $rejected = $loan->status === 'rejected';
    $acceptedG = $loan->guarantors->where('status', 'accepted')->count();
    $needG = (int) ($loan->product->min_guarantors ?? 0);
    $approvals = $loan->approvals()->count();
    $order = ['guarantors_pending' => 1, 'pending' => 2, 'appraised' => 3, 'approved' => 4, 'disbursed' => 5, 'closed' => 6, 'written_off' => 5];
    $pos = $order[$loan->status] ?? 0;
    $steps = [
        ['Applied', $loan->applied_on->format('d M'), true],
        ['Guarantors', $needG ? "$acceptedG of $needG accepted" : 'Not needed', $pos >= 2],
        ['Appraisal', $loan->appraised_at ? $loan->appraised_at->format('d M') : 'Loan officer review', $pos >= 3],
        ['Approval', $loan->approved_at && $pos >= 4 ? $loan->approved_at->format('d M') : ($loan->approvals_required > 1 ? "$approvals of {$loan->approvals_required} approvals" : 'Credit committee'), $pos >= 4],
        ['Disbursed', $loan->disbursed_on ? $loan->disbursed_on->format('d M') : 'Paid to your account', $pos >= 5],
        ['Cleared', $loan->closed_on ? $loan->closed_on->format('d M') : 'Fully repaid', $loan->status === 'closed'],
    ];
    $current = collect($steps)->search(fn ($s) => ! $s[2]);
@endphp
@if($compact ?? false)
    <div class="d-flex gap-1 align-items-center" title="{{ $loan->status_label }}">
        @foreach($steps as $i => $s)
            <span class="rounded-pill" style="width:18px;height:6px;background: {{ $rejected && $i === $current ? '#dc3545' : ($s[2] ? theme()['brand2'] : ($i === $current ? '#ffc107' : '#dee2e6')) }}"></span>
        @endforeach
    </div>
@else
    <div class="tracker d-flex flex-wrap my-2">
        @foreach($steps as $i => $s)
            @php($state = $rejected && $i === $current ? 'rejected' : ($s[2] ? 'done' : ($i === $current ? 'current' : 'todo')))
            <div class="flex-fill text-center position-relative px-1" style="min-width:90px">
                <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center"
                     style="width:34px;height:34px;color:#fff;background: {{ ['done' => theme()['brand2'], 'current' => '#ffc107', 'rejected' => '#dc3545', 'todo' => '#ced4da'][$state] }}">
                    <i class="bi bi-{{ ['done' => 'check-lg', 'current' => 'hourglass-split', 'rejected' => 'x-lg', 'todo' => 'circle'][$state] }}"></i>
                </div>
                <div class="small fw-semibold mt-1">{{ $state === 'rejected' ? 'Not approved' : $s[0] }}</div>
                <div class="small text-muted" style="font-size:.75rem">{{ $s[1] }}</div>
            </div>
        @endforeach
    </div>
    @if($rejected && $loan->remarks)<div class="alert alert-danger small py-2 mb-0">Reason: {{ \Illuminate\Support\Str::afterLast($loan->remarks, "\n") }}</div>
    @elseif($loan->status === 'guarantors_pending')<div class="alert alert-warning small py-2 mb-0">Waiting for your guarantors to accept in their portal. You can remind them by SMS or call.</div>
    @elseif(in_array($loan->status, ['pending', 'appraised']))<div class="alert alert-info small py-2 mb-0">Your application is with the SACCO for review. You'll get an SMS when it is approved.</div>
    @elseif($loan->status === 'approved')<div class="alert alert-success small py-2 mb-0">Approved – disbursement is being processed. @unless(\App\Models\Signature::where('signable_type', \App\Models\Loan::class)->where('signable_id', $loan->id)->exists())Please sign the loan agreement below.@endunless</div>@endif
@endif
