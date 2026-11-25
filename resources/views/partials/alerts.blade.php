{{-- Flash messages and validation errors, shared by all pages --}}
@php
    $styles = [
        'success' => ['success', 'check-circle-fill', 'Done'],
        'error'   => ['danger', 'x-octagon-fill', 'Could not complete that'],
        'warning' => ['warning', 'exclamation-triangle-fill', 'Please note'],
        'info'    => ['info', 'info-circle-fill', 'For your information'],
    ];
@endphp
<div class="alerts-stack">
@foreach($styles as $key => [$color, $icon, $title])
    @if(session($key))
        <div class="alert alert-{{ $color }} alert-dismissible fade show d-flex gap-3 align-items-start shadow-sm {{ $key === 'success' ? 'alert-auto' : '' }}" role="alert">
            <i class="bi bi-{{ $icon }} fs-4 flex-shrink-0"></i>
            <div class="flex-grow-1">
                <div class="fw-semibold">{{ $title }}</div>
                <div>{{ session($key) }}</div>
                @if($key === 'error' && session('error_detail'))
                    <details class="mt-2 small"><summary class="text-muted">Technical details (shown because APP_DEBUG is on)</summary>
                        <pre class="mb-0 mt-1 p-2 bg-white border rounded small text-wrap" style="max-height:220px;overflow:auto">{{ session('error_detail') }}</pre></details>
                @endif
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach

@if($errors->any() && ! session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex gap-3 align-items-start shadow-sm" role="alert">
        <i class="bi bi-exclamation-octagon-fill fs-4 flex-shrink-0"></i>
        <div class="flex-grow-1">
            <div class="fw-semibold">Please correct {{ $errors->count() === 1 ? 'this' : 'these ' . $errors->count() }} {{ \Illuminate\Support\Str::plural('field', $errors->count()) }}</div>
            <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
</div>
