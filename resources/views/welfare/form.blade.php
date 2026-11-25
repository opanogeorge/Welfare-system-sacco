@extends('layouts.app')
@section('title', $event->exists ? "Edit {$event->event_no}" : 'New Welfare Event')
@section('content')
<form method="POST" action="{{ $event->exists ? route('welfare.update', $event) : route('welfare.store') }}" enctype="multipart/form-data">@csrf
@if($event->exists) @method('PUT') @endif
<div class="row g-3">
<div class="col-lg-8">
    <div class="card mb-3"><div class="card-body">
        <h6>Who and what</h6>
        <div class="row g-3">
            <div class="col-12"><label class="form-label">Member being supported *</label>
                <select name="member_id" class="member-select" required>@if($member)<option value="{{ $member->id }}" selected>{{ $member->member_no }} - {{ $member->full_name }}</option>@endif</select></div>
            <div class="col-md-4"><label class="form-label">Type *</label><select name="type" id="wType" class="form-select">@foreach(\App\Models\WelfareEvent::TYPES as $k => $v)<option value="{{ $k }}" @selected(old('type', $event->type) === $k)>{{ $v }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Relationship to member</label><select name="relationship" id="wRel" class="form-select"><option value="">—</option>@foreach(\App\Models\WelfareEvent::RELATIONSHIPS as $k => $v)<option value="{{ $k }}" @selected(old('relationship', $event->relationship) === $k)>{{ $v }}</option>@endforeach</select>
                <div class="form-text">For bereavement: who passed on.</div></div>
            <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="event_date" value="{{ old('event_date', $event->event_date?->toDateString()) }}" class="form-control"></div>
            <div class="col-12"><label class="form-label">Title *</label><input name="title" value="{{ old('title', $event->title) }}" class="form-control" placeholder="e.g. Bereavement – mother of Jane Achieng" required></div>
            <div class="col-12"><label class="form-label">Details (shown to members)</label><textarea name="details" rows="3" class="form-control" placeholder="Funeral arrangements, venue, wedding date, hospital...">{{ old('details', $event->details) }}</textarea></div>
            <div class="col-md-6"><label class="form-label">Supporting document</label><input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png"><div class="form-text">Burial permit, invitation card, hospital note.</div></div>
        </div>
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <h6>How members contribute</h6>
        <div class="d-flex flex-wrap gap-3 mb-3">
            @foreach(['levy' => ['Fixed levy from every member', 'Same amount charged to all active members'], 'voluntary' => ['Voluntary', 'Members give what they wish'], 'both' => ['Levy + voluntary', 'A fixed levy, and members may give more']] as $k => [$t, $s])
                <label class="border rounded p-2 flex-fill" style="min-width:180px"><input type="radio" name="mode" value="{{ $k }}" class="form-check-input me-1" @checked(old('mode', $event->mode) === $k) onchange="levyBox()"> <strong>{{ $t }}</strong><br><small class="text-muted">{{ $s }}</small></label>
            @endforeach
        </div>
        <div class="row g-3" id="levyFields">
            <div class="col-md-4"><label class="form-label">Levy per member (KES)</label><input type="number" step="1" name="levy_amount" value="{{ old('levy_amount', $event->levy_amount + 0 ?: '') }}" class="form-control" id="levyAmt"></div>
            <div class="col-md-8"><label class="form-label">Collect the levy</label><select name="levy_source" class="form-select">
                <option value="SAV" @selected(old('levy_source', $event->levy_source) === 'SAV')>Deduct from each member's savings (FOSA); members without enough balance pay by M-Pesa</option>
                <option value="DEP" @selected(old('levy_source', $event->levy_source) === 'DEP')>Deduct from deposits (BOSA), if your by-laws allow</option>
                <option value="none" @selected(old('levy_source', $event->levy_source) === 'none')>Don't deduct; members pay by M-Pesa / cash</option></select></div>
            <div class="col-12 small text-muted" id="levyEstimate"></div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-4"><label class="form-label">Suggested voluntary amount</label><input type="number" name="suggested_amount" value="{{ old('suggested_amount', $event->suggested_amount + 0 ?: '') }}" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Target (optional)</label><input type="number" name="target_amount" value="{{ old('target_amount', $event->target_amount + 0 ?: '') }}" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Contributions close on</label><input type="date" name="closes_on" value="{{ old('closes_on', $event->closes_on?->toDateString()) }}" class="form-control"></div>
        </div>
    </div></div>
</div>
<div class="col-lg-4">
    <div class="card mb-3"><div class="card-body">
        <h6>Benefit from the welfare fund</h6>
        <input type="number" step="0.01" name="fund_contribution" id="fundAmt" value="{{ old('fund_contribution', $event->exists ? $event->fund_contribution : '') }}" class="form-control mb-1" placeholder="Auto from benefit schedule">
        <div class="form-text">Paid to the member from the fund reserve on top of what members raise. Filled in from the <a href="{{ route('welfare.benefits') }}">benefit schedule</a>; you can change it.</div>
    </div></div>
    @unless($event->exists)
    @if(can_route('welfare.open'))
    <div class="card mb-3"><div class="card-body">
        <div class="form-check"><input type="checkbox" name="open_now" value="1" class="form-check-input" id="openNow" checked><label class="form-check-label" for="openNow">Approve and open now (charges the levy)</label></div>
        <div class="form-check"><input type="hidden" name="announce" value="0"><input type="checkbox" name="announce" value="1" class="form-check-input" id="ann" checked><label class="form-check-label" for="ann">SMS all members</label></div>
    </div></div>
    @endif
    @endunless
    <button class="btn btn-primary w-100">{{ $event->exists ? 'Save changes' : 'Save event' }}</button>
    <a href="{{ $event->exists ? route('welfare.show', $event) : route('welfare.index') }}" class="btn btn-link w-100">Cancel</a>
</div>
</div>
</form>
@endsection
@push('scripts')
<script>
const activeMembers = {{ \App\Models\Member::active()->count() }};
function levyBox() {
    const mode = document.querySelector('input[name=mode]:checked')?.value;
    document.getElementById('levyFields').style.display = mode === 'voluntary' ? 'none' : '';
    const a = +document.getElementById('levyAmt').value;
    document.getElementById('levyEstimate').textContent = a ? `About ${(activeMembers - 1).toLocaleString()} members × KES ${a.toLocaleString()} ≈ KES ${((activeMembers - 1) * a).toLocaleString()}` : '';
}
document.getElementById('levyAmt').addEventListener('input', levyBox); levyBox();
const fund = document.getElementById('fundAmt');
let fundTouched = {{ $event->exists ? 'true' : 'false' }};
fund.addEventListener('input', () => fundTouched = true);
function benefit() {
    if (fundTouched) return;
    fetch(`{{ route('welfare.benefit-for') }}?type=${document.getElementById('wType').value}&relationship=${document.getElementById('wRel').value}`, {headers: {Accept: 'application/json'}})
        .then(r => r.json()).then(b => { fund.value = b.amount || 0; });
}
['wType', 'wRel'].forEach(id => document.getElementById(id).addEventListener('change', benefit)); benefit();
</script>
@endpush
