{{-- Usage: @include('partials.signature-pad', ['name' => 'signature', 'label' => 'Sign here']) inside a <form> --}}
@php($sid = 'sig' . \Illuminate\Support\Str::random(6))
<div class="sig-wrap mb-2">
    <label class="form-label small mb-1">{{ $label ?? 'Sign in the box (finger, stylus or mouse)' }}</label>
    <div class="border rounded bg-white position-relative" style="touch-action:none">
        <canvas id="{{ $sid }}" style="width:100%;height:{{ $height ?? 170 }}px;display:block;cursor:crosshair"></canvas>
        <span class="position-absolute text-muted small" style="left:12px;bottom:8px;pointer-events:none">✕ ______________________</span>
        <button type="button" class="btn btn-sm btn-light position-absolute" style="right:6px;top:6px" data-clear="{{ $sid }}"><i class="bi bi-eraser"></i> Clear</button>
    </div>
    <input type="hidden" name="{{ $name ?? 'signature' }}" id="{{ $sid }}-input">
</div>
<script>
(function () {
    const c = document.getElementById('{{ $sid }}'), input = document.getElementById('{{ $sid }}-input'), ctx = c.getContext('2d');
    let drawing = false, drawn = false, last = null;
    function size() {
        const r = c.getBoundingClientRect(), ratio = window.devicePixelRatio || 1, img = drawn ? c.toDataURL() : null;
        c.width = r.width * ratio; c.height = r.height * ratio; ctx.scale(ratio, ratio);
        ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0b2e59';
        if (img) { const i = new Image(); i.onload = () => ctx.drawImage(i, 0, 0, r.width, r.height); i.src = img; }
    }
    const pos = e => { const r = c.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; };
    c.addEventListener('pointerdown', e => { drawing = true; last = pos(e); c.setPointerCapture(e.pointerId); });
    c.addEventListener('pointermove', e => {
        if (!drawing) return; const p = pos(e);
        ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke(); last = p; drawn = true;
    });
    ['pointerup', 'pointerleave', 'pointercancel'].forEach(ev => c.addEventListener(ev, () => { drawing = false; if (drawn) input.value = c.toDataURL('image/png'); }));
    document.querySelector('[data-clear="{{ $sid }}"]').addEventListener('click', () => { ctx.clearRect(0, 0, c.width, c.height); drawn = false; input.value = ''; });
    window.addEventListener('resize', size); size();
    c.closest('form')?.addEventListener('submit', e => { if (!drawn) { e.preventDefault(); alert('Please sign in the signature box.'); } });
})();
</script>
