@extends('layouts.app')
@section('title', "Identity verification · {$member->member_no}")
@section('content')
<div class="d-flex align-items-center mb-3 gap-2">
    <a href="{{ route('members.show', $member) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> {{ $member->full_name }}</a>
    <span class="badge fs-6 text-bg-{{ ['verified' => 'success', 'failed' => 'danger'][$member->identity_status] ?? 'secondary' }}">{{ ucfirst($member->identity_status) }}</span>
    <span class="small text-muted">Applied via {{ $member->onboarding_channel }}</span>
</div>
<div class="row g-3">
<div class="col-lg-8"><div class="card"><div class="card-body">
    <h6>Face match – live selfie vs ID photo</h6>
    <div class="row g-2 mb-3">
        @foreach(['Live selfie' => $selfie, 'ID front' => $idFront, 'ID back' => $idBack] as $label => $doc)
            <div class="col-md-4"><div class="border rounded p-1 text-center bg-light" style="min-height:220px">
                <div class="small text-muted">{{ $label }}</div>
                @if($doc && preg_match('/\.(jpe?g|png)$/i', $doc->path))<img src="{{ route('documents.show', $doc) }}" class="img-fluid rounded" style="max-height:260px">
                @elseif($doc)<a href="{{ route('documents.show', $doc) }}" target="_blank" class="d-block mt-5">Open PDF</a>
                @else<div class="text-muted mt-5">Not provided</div>@endif
            </div></div>
        @endforeach
    </div>
    @if(can_route('members.identity.face'))
    <form method="POST" action="{{ route('members.identity.face', $member) }}" class="row g-2 align-items-end">@csrf
        <div class="col-md-6"><input name="notes" class="form-control" placeholder="Notes (e.g. compared in person, glasses, older photo)"></div>
        <div class="col-md-3"><button name="match" value="1" class="btn btn-success w-100"><i class="bi bi-person-check"></i> Same person</button></div>
        <div class="col-md-3"><button name="match" value="0" class="btn btn-outline-danger w-100"><i class="bi bi-person-x"></i> Not a match</button></div>
    </form>
    @endif
    <hr>
    <h6>Capture a live photo at the counter</h6>
    <div class="d-flex gap-3 align-items-start flex-wrap">
        <video id="cam" autoplay playsinline muted class="border rounded bg-dark" style="width:260px;height:195px;object-fit:cover"></video>
        <div>
            <button type="button" class="btn btn-sm btn-outline-secondary mb-2" onclick="startCam()"><i class="bi bi-camera-video"></i> Start camera</button><br>
            <form method="POST" action="{{ route('members.identity.capture', $member) }}" id="capForm">@csrf<input type="hidden" name="image" id="capImg">
                <button type="button" class="btn btn-sm btn-success" onclick="snap()"><i class="bi bi-camera"></i> Capture &amp; save</button></form>
        </div>
    </div>
</div></div></div>
<div class="col-lg-4">
    <div class="card mb-3"><div class="card-body">
        <h6>National registry (IPRS) check</h6>
        <p class="small text-muted">Confirms the ID number, names and date of birth with the government registry via Smile ID.</p>
        @if($configured && can_route('members.identity.iprs'))
            <form method="POST" action="{{ route('members.identity.iprs', $member) }}">@csrf<button class="btn btn-outline-success w-100"><i class="bi bi-shield-check"></i> Run IPRS check</button></form>
        @elseif(! $configured)<div class="small text-muted">Smile ID not configured (Admin → Integrations → ID check). Verify the physical ID at the counter instead.</div>@endif
    </div></div>
    <div class="card"><div class="card-body small">
        <h6>History</h6>
        @forelse($verifications as $v)
            <div class="border-bottom py-1"><span class="badge text-bg-{{ ['verified' => 'success', 'partial' => 'warning', 'failed' => 'danger'][$v->result] ?? 'secondary' }}">{{ $v->result }}</span>
                {{ str_replace('_', ' ', $v->type) }} · {{ $v->provider }} <span class="text-muted">{{ $v->created_at->format('d/m/Y H:i') }} {{ $v->verifier?->name }}</span>
                @if($v->details['result_text'] ?? null)<br><span class="text-muted">{{ $v->details['result_text'] }}</span>@endif
                @if($v->details['actions'] ?? null)<br><span class="text-muted">@foreach($v->details['actions'] as $k => $val){{ str_replace('_', ' ', $k) }}: {{ $val }}@if(! $loop->last) · @endif @endforeach</span>@endif
                @if($v->details['notes'] ?? null)<br>{{ $v->details['notes'] }}@endif</div>
        @empty <span class="text-muted">No checks yet.</span> @endforelse
    </div></div>
</div>
</div>
@endsection
@push('scripts')
<script>
let stream;
async function startCam() {
    try { stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false }); document.getElementById('cam').srcObject = stream; }
    catch { alert('Camera not available on this computer.'); }
}
function snap() {
    const v = document.getElementById('cam'); if (!v.videoWidth) return alert('Start the camera first.');
    const c = document.createElement('canvas'); c.width = v.videoWidth; c.height = v.videoHeight; c.getContext('2d').drawImage(v, 0, 0);
    document.getElementById('capImg').value = c.toDataURL('image/jpeg', 0.85); stream?.getTracks().forEach(t => t.stop());
    document.getElementById('capForm').submit();
}
</script>
@endpush
