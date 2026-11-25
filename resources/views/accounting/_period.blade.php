<form class="row g-2 mb-3 no-print">
    @isset($asAt)
        <div class="col-auto"><label class="small">As at</label><input type="date" name="as_at" value="{{ $asAt }}" class="form-control"></div>
    @else
        <div class="col-auto"><label class="small">From</label><input type="date" name="from" value="{{ $from }}" class="form-control"></div>
        <div class="col-auto"><label class="small">To</label><input type="date" name="to" value="{{ $to }}" class="form-control"></div>
    @endisset
    <div class="col-auto align-self-end"><button class="btn btn-outline-secondary">Run</button> <button type="button" onclick="print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> Print / PDF</button></div>
</form>
<div class="text-center mb-3">
    <h5 class="mb-0">{{ sacco_name() }}</h5>
    <div class="fw-semibold">{{ $heading }}</div>
    <div class="small text-muted">@isset($asAt) As at {{ \Carbon\Carbon::parse($asAt)->format('d F Y') }} @else {{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }} @endisset · KES</div>
</div>
