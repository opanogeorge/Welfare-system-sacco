@extends('layouts.public')
@section('title', 'Become a member')
@section('content')
<div class="row justify-content-center"><div class="col-lg-9">
<h1 class="h3">Apply for membership</h1>
<p class="text-muted">Takes about 10 minutes. Have your National ID ready and allow camera access for a quick selfie.</p>

<form method="POST" action="{{ route('join') }}" enctype="multipart/form-data" id="joinForm" autocomplete="off">
@csrf
<input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off">

<div class="card mb-3"><div class="card-body">
    <h5><span class="step-num">1</span> Personal details</h5>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">First name *</label><input name="first_name" value="{{ old('first_name') }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Middle name</label><input name="middle_name" value="{{ old('middle_name') }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Last name *</label><input name="last_name" value="{{ old('last_name') }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">National ID number *</label><input name="national_id" value="{{ old('national_id') }}" class="form-control" inputmode="numeric" required></div>
        <div class="col-md-4"><label class="form-label">Date of birth *</label><input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" class="form-control" max="{{ now()->subYears(18)->toDateString() }}" required></div>
        <div class="col-md-4"><label class="form-label">Gender *</label><select name="gender" class="form-select" required><option value=""></option>@foreach(['female' => 'Female', 'male' => 'Male', 'other' => 'Other'] as $k => $v)<option value="{{ $k }}" @selected(old('gender') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">KRA PIN</label><input name="kra_pin" value="{{ old('kra_pin') }}" class="form-control text-uppercase"></div>
        <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email') }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Residential address</label><input name="address" value="{{ old('address') }}" class="form-control"></div>
    </div>
</div></div>

<div class="card mb-3"><div class="card-body">
    <h5><span class="step-num">2</span> Verify your phone</h5>
    <div class="row g-2 align-items-end">
        <div class="col-md-5"><label class="form-label">Mobile number *</label><input name="phone" id="phone" value="{{ old('phone') }}" class="form-control" placeholder="07XXXXXXXX" required></div>
        <div class="col-md-3"><button type="button" class="btn btn-outline-success w-100" id="sendOtp">Send code</button></div>
        <div class="col-md-4"><label class="form-label">6-digit code *</label><input name="otp" class="form-control" inputmode="numeric" maxlength="6" required></div>
    </div>
    <div class="small mt-2" id="otpMsg"></div>
</div></div>

<div class="card mb-3"><div class="card-body">
    <h5><span class="step-num">3</span> Work &amp; savings</h5>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Employer (for payroll check-off)</label>
            <select name="employer_id" class="form-select"><option value="">Not listed / self-employed</option>@foreach($employers as $e)<option value="{{ $e->id }}" @selected(old('employer_id') == $e->id)>{{ $e->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Payroll / staff no.</label><input name="payroll_no" value="{{ old('payroll_no') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Monthly contribution *</label><input type="number" name="monthly_contribution" value="{{ old('monthly_contribution', setting('min_monthly_contribution', 500)) }}" min="{{ setting('min_monthly_contribution', 500) }}" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Occupation</label><input name="occupation" value="{{ old('occupation') }}" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Main source of income</label><input name="source_of_funds" value="{{ old('source_of_funds') }}" class="form-control" placeholder="Salary, business, farming…"></div>
    </div>
</div></div>

<div class="card mb-3"><div class="card-body">
    <h5><span class="step-num">4</span> Nominee (next of kin)</h5>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Full name *</label><input name="nominee_name" value="{{ old('nominee_name') }}" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Relationship *</label><input name="nominee_relationship" value="{{ old('nominee_relationship') }}" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Phone</label><input name="nominee_phone" value="{{ old('nominee_phone') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">ID no.</label><input name="nominee_id" value="{{ old('nominee_id') }}" class="form-control"></div>
    </div>
</div></div>

<div class="card mb-3"><div class="card-body">
    <h5><span class="step-num">5</span> Documents &amp; selfie</h5>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">ID – front *</label><input type="file" name="id_front" accept="image/*,.pdf" capture="environment" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">ID – back</label><input type="file" name="id_back" accept="image/*,.pdf" capture="environment" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Latest payslip</label><input type="file" name="payslip" accept="image/*,.pdf" class="form-control"></div>
        <div class="col-md-6">
            <label class="form-label">Live selfie *</label>
            <div class="border rounded bg-dark position-relative text-center" style="aspect-ratio:4/3">
                <video id="cam" autoplay playsinline muted class="w-100 h-100" style="object-fit:cover"></video>
                <img id="shot" class="w-100 h-100 d-none" style="object-fit:cover">
            </div>
            <div class="d-flex gap-2 mt-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="camStart"><i class="bi bi-camera-video"></i> Start camera</button>
                <button type="button" class="btn btn-success btn-sm" id="camSnap" disabled><i class="bi bi-camera"></i> Take selfie</button>
                <button type="button" class="btn btn-link btn-sm d-none" id="camRetake">Retake</button>
            </div>
            <input type="hidden" name="selfie" id="selfie">
            <div class="form-text">Face the camera in good light, no hat or sunglasses. This is compared with your ID photo.</div>
        </div>
    </div>
</div></div>

<div class="card mb-3"><div class="card-body">
    <h5><span class="step-num">6</span> Portal password, consent &amp; signature</h5>
    <div class="row g-3 mb-3">
        <div class="col-md-6"><label class="form-label">Choose a password *</label><input type="password" name="password" class="form-control" required autocomplete="new-password"><div class="form-text">At least 8 characters with letters and numbers. You can sign in once approved.</div></div>
        <div class="col-md-6"><label class="form-label">Confirm password *</label><input type="password" name="password_confirmation" class="form-control" required></div>
    </div>
    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="accept_bylaws" value="1" id="c1" required><label class="form-check-label" for="c1">I apply for membership and agree to abide by the by-laws of {{ sacco_name() }}. The information I have given is true.</label></div>
    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="accept_data" value="1" id="c2" required><label class="form-check-label" for="c2">I consent to the SACCO processing my personal data, including verifying my ID with the national registry, as required by the Data Protection Act, 2019 and anti-money-laundering law.</label></div>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="accept_crb" value="1" id="c3" @checked(old('accept_crb'))><label class="form-check-label" for="c3">I consent to the SACCO checking and sharing my credit information with licensed credit reference bureaus.</label></div>
    @include('partials.signature-pad', ['name' => 'signature', 'label' => 'Your signature *'])
    <button class="btn btn-success btn-lg mt-2 w-100"><i class="bi bi-send"></i> Submit application</button>
</div></div>
</form>
</div></div>
@endsection
@push('scripts')
<script>
document.getElementById('sendOtp').addEventListener('click', async (e) => {
    const btn = e.target, msg = document.getElementById('otpMsg'), phone = document.getElementById('phone').value;
    if (!phone) { msg.innerHTML = '<span class="text-danger">Enter your phone number first.</span>'; return; }
    btn.disabled = true;
    try {
        const r = await fetch('{{ route('join.otp') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ phone }) });
        const j = await r.json(); msg.innerHTML = `<span class="${r.ok ? 'text-success' : 'text-danger'}">${j.message || 'Could not send the code.'}</span>`;
    } catch { msg.innerHTML = '<span class="text-danger">Network error.</span>'; }
    setTimeout(() => btn.disabled = false, 30000);
});

let stream;
const cam = document.getElementById('cam'), shot = document.getElementById('shot'), snap = document.getElementById('camSnap'), retake = document.getElementById('camRetake');
document.getElementById('camStart').addEventListener('click', async () => {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 960 } }, audio: false });
        cam.srcObject = stream; snap.disabled = false; shot.classList.add('d-none'); cam.classList.remove('d-none');
    } catch { alert('Camera not available. Please allow camera access, or use a phone with a front camera.'); }
});
snap.addEventListener('click', () => {
    const c = document.createElement('canvas'); c.width = cam.videoWidth; c.height = cam.videoHeight;
    c.getContext('2d').drawImage(cam, 0, 0);
    const data = c.toDataURL('image/jpeg', 0.85); document.getElementById('selfie').value = data;
    shot.src = data; shot.classList.remove('d-none'); cam.classList.add('d-none'); retake.classList.remove('d-none');
    stream?.getTracks().forEach(t => t.stop()); snap.disabled = true;
});
retake.addEventListener('click', () => document.getElementById('camStart').click());
document.getElementById('joinForm').addEventListener('submit', e => {
    if (!document.getElementById('selfie').value) { e.preventDefault(); alert('Please take your live selfie (step 5).'); }
});
</script>
@endpush
