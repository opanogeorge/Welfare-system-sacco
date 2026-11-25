@php($t = theme())
<style>
    :root {
        --brand: {{ $t['brand'] }}; --brand-2: {{ $t['brand2'] }}; --accent: {{ $t['accent'] }};
        --bs-primary: {{ $t['brand2'] }}; --bs-primary-rgb: {{ hex_rgb($t['brand2']) }};
        --bs-link-color: {{ $t['brand2'] }}; --bs-link-color-rgb: {{ hex_rgb($t['brand2']) }};
        --bs-link-hover-color: {{ $t['brand'] }}; --bs-link-hover-color-rgb: {{ hex_rgb($t['brand']) }};
        --bs-primary-text-emphasis: {{ $t['brand'] }}; --bs-primary-bg-subtle: color-mix(in srgb, {{ $t['brand2'] }} 12%, white);
        --bs-primary-border-subtle: color-mix(in srgb, {{ $t['brand2'] }} 35%, white);
    }
    body { background: {{ $t['bg'] }}; }
    /* Action buttons follow the theme (green stays only for "paid / up to date" status badges) */
    .btn-primary, .btn-success {
        --bs-btn-color: #fff; --bs-btn-bg: var(--brand-2); --bs-btn-border-color: var(--brand-2);
        --bs-btn-hover-color: #fff; --bs-btn-hover-bg: var(--brand); --bs-btn-hover-border-color: var(--brand);
        --bs-btn-active-color: #fff; --bs-btn-active-bg: var(--brand); --bs-btn-active-border-color: var(--brand);
        --bs-btn-disabled-bg: var(--brand-2); --bs-btn-disabled-border-color: var(--brand-2);
        --bs-btn-focus-shadow-rgb: {{ hex_rgb($t['brand2']) }};
    }
    .btn-outline-primary, .btn-outline-success {
        --bs-btn-color: var(--brand-2); --bs-btn-border-color: var(--brand-2);
        --bs-btn-hover-color: #fff; --bs-btn-hover-bg: var(--brand-2); --bs-btn-hover-border-color: var(--brand-2);
        --bs-btn-active-color: #fff; --bs-btn-active-bg: var(--brand); --bs-btn-active-border-color: var(--brand);
        --bs-btn-focus-shadow-rgb: {{ hex_rgb($t['brand2']) }};
    }
    .text-primary { color: var(--brand-2) !important; }
    .bg-primary, .text-bg-primary { background-color: var(--brand-2) !important; color: #fff !important; }
    .border-primary { border-color: var(--brand-2) !important; }
    .progress-bar { background-color: var(--brand-2); }
    .form-check-input:checked { background-color: var(--brand-2); border-color: var(--brand-2); }
    .form-control:focus, .form-select:focus { border-color: color-mix(in srgb, var(--brand-2) 55%, white); box-shadow: 0 0 0 .2rem rgba({{ hex_rgb($t['brand2']) }}, .18); }
    .page-link { color: var(--brand-2); }
    .active > .page-link, .page-link.active { background-color: var(--brand-2); border-color: var(--brand-2); }
    .nav-pills .nav-link.active { background-color: var(--brand-2); }
    .sidebar .nav-link.active { box-shadow: inset 3px 0 0 var(--accent) !important; }
    .card.stat .value { color: var(--brand); }
</style>
