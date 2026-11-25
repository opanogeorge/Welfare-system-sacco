<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ sacco_name() }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { font-size: .925rem; }
        :root { --sb-w: 250px; --sb-mini: 68px; }
        /* ---------- Sidebar ---------- */
        .sidebar { width: var(--sb-w); height: 100vh; height: 100dvh; background: var(--brand); position: fixed; top: 0; left: 0;
                   overflow-y: auto; overflow-x: hidden; z-index: 1045; transition: width .2s ease, transform .25s ease; scrollbar-width: thin; }
        .sidebar .brand { color: #fff; font-weight: 700; padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,.1);
                          font-size: 1.05rem; white-space: nowrap; display: flex; align-items: center; }
        .sidebar .nav-link { color: rgba(255,255,255,.78); padding: .5rem 1.25rem; border-radius: 0; white-space: nowrap; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,.12); }
        .sidebar .nav-link i { width: 1.4rem; display: inline-block; font-size: 1rem; }
        /* collapsible groups */
        .sidebar .group-toggle { display: flex; align-items: center; }
        .sidebar .group-toggle .chev { margin-left: auto; font-size: .75rem; width: auto; transition: transform .2s; opacity: .7; }
        .sidebar .group-toggle[aria-expanded="true"] .chev { transform: rotate(180deg); }
        .sidebar .group-toggle.parent-active { color: #fff; }
        .sidebar .group-toggle .group-badge { margin-left: .4rem; }
        .sidebar .group-toggle[aria-expanded="true"] .group-badge { display: none; }
        .sidebar .submenu { background: rgba(0,0,0,.14); margin: 0; padding: .2rem 0; }
        .sidebar .submenu .nav-link { padding: .38rem 1.25rem .38rem 2.6rem; font-size: .86rem; }
        .sidebar .submenu .nav-link i { font-size: .85rem; width: 1.2rem; }
        .sidebar .section { color: rgba(255,255,255,.45); font-size: .7rem; text-transform: uppercase; letter-spacing: .08em; padding: 1rem 1.25rem .3rem; white-space: nowrap; }
        .sidebar .btn-close-sb { display: none; margin-left: auto; color: #fff; background: none; border: 0; font-size: 1.3rem; line-height: 1; }
        .main { margin-left: var(--sb-w); transition: margin-left .2s ease; min-width: 0; }
        .topbar { background: #fff; border-bottom: 1px solid #e3e6e4; position: sticky; top: 0; z-index: 1020; }
        .sb-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 1040; opacity: 0; visibility: hidden; transition: opacity .2s; }

        /* Desktop: collapsible to an icon rail */
        @media (min-width: 992px) {
            body.sb-mini .sidebar { width: var(--sb-mini); }
            body.sb-mini .main { margin-left: var(--sb-mini); }
            body.sb-mini .sidebar .nav-link { font-size: 0; padding: .65rem 0; text-align: center; }
            body.sb-mini .sidebar .nav-link i { font-size: 1.15rem; width: auto; }
            body.sb-mini .sidebar .section, body.sb-mini .sidebar .brand-text { display: none; }
            body.sb-mini .sidebar:not(:hover) .submenu, body.sb-mini .sidebar:not(:hover) .chev, body.sb-mini .sidebar:not(:hover) .badge { display: none !important; }
            body.sb-mini .sidebar .brand { justify-content: center; padding: 1rem 0; }
            body.sb-mini .sidebar .brand i { margin: 0 !important; }
            body.sb-mini .sidebar:hover { width: var(--sb-w); box-shadow: 4px 0 20px rgba(0,0,0,.25); }
            body.sb-mini .sidebar:hover .nav-link { font-size: inherit; padding: .5rem 1.25rem; text-align: left; }
            body.sb-mini .sidebar:hover .nav-link i { font-size: 1rem; width: 1.4rem; }
            body.sb-mini .sidebar:hover .section { display: block; }
            body.sb-mini .sidebar:hover .brand-text { display: inline; }
            body.sb-mini .sidebar:hover .brand { justify-content: flex-start; padding: 1rem 1.25rem; }
            body.sb-mini .sidebar:hover .brand i { margin-right: .5rem !important; }
        }
        /* Tablet & phone: off-canvas drawer */
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); width: min(var(--sb-w), 85vw); }
            .sidebar.show { transform: translateX(0); box-shadow: 4px 0 24px rgba(0,0,0,.3); }
            .sidebar .btn-close-sb { display: block; }
            .sidebar.show + .sb-backdrop { opacity: 1; visibility: visible; }
            .main { margin-left: 0 !important; }
            body.sb-open { overflow: hidden; }
            main.p-3 { padding: .75rem !important; }
            .topbar h1 { font-size: 1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .stat .value { font-size: 1.2rem; }
        }
        @media (max-width: 575.98px) { .topbar .user-role, .topbar .user-name { display: none; } }
        .card { border: 0; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .stat .value { font-size: 1.45rem; font-weight: 700; }
        .stat .label { color: #6c757d; font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; }
        .table > :not(caption) > * > * { padding: .55rem .6rem; }
        .text-money { font-variant-numeric: tabular-nums; text-align: right; white-space: nowrap; }
        @media (max-width: 991px) { .sidebar { left: -250px; transition: left .2s; } .sidebar.show { left: 0; } .main { margin-left: 0; } }
        @media print { .sidebar, .sb-backdrop, .topbar, .no-print { display: none !important; } .main { margin-left: 0 !important; } body { background: #fff; } .card { box-shadow: none; } }
    </style>
    @include('partials.theme')
    @stack('styles')
</head>
<body>
@php($u = auth()->user())
<nav class="sidebar" id="sidebar">
    <div class="brand"><i class="bi bi-bank2 me-2"></i><span class="brand-text">{{ \Illuminate\Support\Str::limit(sacco_name(), 22) }}</span>
        <button type="button" class="btn-close-sb" data-sb-close aria-label="Close menu"><i class="bi bi-x-lg"></i></button></div>
    <ul class="nav flex-column py-2" id="sideMenu">
        @foreach(\App\Support\Menu::for($u) as $g)
            @if($g['list']->count() === 1)
                @php($i = $g['list'][0])
                <li><a class="nav-link {{ $i['active'] ? 'active' : '' }}" href="{{ $i['url'] }}" title="{{ $g['label'] }}"><i class="bi bi-{{ $g['icon'] }}"></i> {{ $g['label'] }}
                    @if($i['badge'])<span class="badge rounded-pill text-bg-warning ms-1">{{ $i['badge'] }}</span>@endif</a></li>
            @else
                <li class="menu-group {{ $g['active'] ? 'open' : '' }}">
                    <a class="nav-link group-toggle {{ $g['active'] ? 'parent-active' : '' }}" href="#m-{{ $g['key'] }}" data-bs-toggle="collapse" role="button"
                       aria-expanded="{{ $g['active'] ? 'true' : 'false' }}" aria-controls="m-{{ $g['key'] }}" title="{{ $g['label'] }}">
                        <i class="bi bi-{{ $g['icon'] }}"></i> {{ $g['label'] }}
                        @if($g['badge'])<span class="badge rounded-pill text-bg-warning group-badge">{{ $g['badge'] }}</span>@endif
                        <i class="bi bi-chevron-down chev"></i>
                    </a>
                    <ul class="collapse submenu list-unstyled {{ $g['active'] ? 'show' : '' }}" id="m-{{ $g['key'] }}" data-bs-parent="{{ setting('menu_accordion', 1) ? '#sideMenu' : '' }}">
                        @foreach($g['list'] as $i)
                            <li><a class="nav-link {{ $i['active'] ? 'active' : '' }}" href="{{ $i['url'] }}"><i class="bi bi-{{ $i['icon'] }}"></i> {{ $i['label'] }}
                                @if($i['badge'])<span class="badge rounded-pill text-bg-warning ms-1">{{ $i['badge'] }}</span>@endif</a></li>
                        @endforeach
                    </ul>
                </li>
            @endif
        @endforeach
    </ul>
</nav>
<div class="sb-backdrop" data-sb-close></div>

<div class="main">
    <header class="topbar px-3 py-2 d-flex align-items-center no-print">
        <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="sbToggle" aria-label="Toggle menu" aria-controls="sidebar"><i class="bi bi-list fs-5"></i></button>
        <h1 class="h5 mb-0">@yield('title', 'Dashboard')</h1>
        <div class="ms-auto dropdown">
            <a class="text-decoration-none text-dark dropdown-toggle" href="#" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle me-1"></i><span class="user-name">{{ $u->name }}</span> <small class="text-muted user-role">({{ \App\Models\User::ROLES[$u->role] ?? $u->role }})</small>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('profile.password') }}"><i class="bi bi-key me-2"></i>Change password</a></li>
                <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button></form></li>
            </ul>
        </div>
    </header>

    <main class="p-3 p-lg-4">
        @include('partials.alerts')
        @yield('content')
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
    // <select class="member-select"> becomes a searchable remote member picker
    function memberPicker(el, onChange) {
        return new TomSelect(el, {
            valueField: 'id', labelField: 'text', searchField: 'text', preload: false, loadThrottle: 300,
            load: (q, cb) => fetch(`{{ $u->isStaff() ? route('members.lookup') : '' }}?q=${encodeURIComponent(q)}`).then(r => r.json()).then(cb).catch(() => cb()),
            onChange: onChange || null,
        });
    }
    document.querySelectorAll('select.member-select').forEach(el => memberPicker(el));
    // Highlight the fields that failed validation and put the message under each one
    (function () {
        const errs = @json($errors->getMessages());
        let first = null;
        Object.entries(errs).forEach(([name, msgs]) => {
            const base = name.replace(/\.(\w+)/g, '[$1]');
            const el = document.querySelector(`[name="${base}"], [name="${base}[]"]`);
            if (!el || el.type === 'hidden') return;
            const target = el.tomselect ? el.tomselect.wrapper : el;
            target.classList.add('is-invalid');
            if (!target.parentElement.querySelector('.invalid-feedback.js-err')) {
                const fb = document.createElement('div'); fb.className = 'invalid-feedback js-err d-block'; fb.textContent = msgs[0];
                target.insertAdjacentElement('afterend', fb);
            }
            el.addEventListener('input', () => { target.classList.remove('is-invalid'); target.parentElement.querySelector('.js-err')?.remove(); }, { once: true });
            first = first || target;
        });
        if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); first.focus?.({ preventScroll: true }); }
        // success messages fade away after 6 seconds
        setTimeout(() => document.querySelectorAll('.alert-auto').forEach(a => bootstrap.Alert.getOrCreateInstance(a).close()), 6000);
    })();
    document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));

    // ---------- Responsive sidebar ----------
    (function () {
        const sb = document.getElementById('sidebar'), body = document.body;
        const desktop = () => window.matchMedia('(min-width: 992px)').matches;
        const store = (v) => { try { localStorage.setItem('sb-mini', v ? '1' : '0'); } catch (e) {} };
        const saved = () => { try { return localStorage.getItem('sb-mini') === '1'; } catch (e) { return false; } };
        const open = () => { sb.classList.add('show'); body.classList.add('sb-open'); };
        const close = () => { sb.classList.remove('show'); body.classList.remove('sb-open'); };

        if (saved()) body.classList.add('sb-mini');
        document.getElementById('sbToggle').addEventListener('click', () => {
            if (desktop()) { body.classList.toggle('sb-mini'); store(body.classList.contains('sb-mini')); }
            else { sb.classList.contains('show') ? close() : open(); }
        });
        document.querySelectorAll('[data-sb-close]').forEach(el => el.addEventListener('click', close));
        sb.querySelectorAll('a.nav-link:not(.group-toggle)').forEach(a => a.addEventListener('click', () => { if (!desktop()) close(); }));
        document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
        window.addEventListener('resize', () => { if (desktop()) close(); });
        // keep the active link in view inside a long menu
        sb.querySelector('.nav-link.active')?.scrollIntoView({ block: 'center' });

        // swipe left to close on touch devices
        let x0 = null;
        sb.addEventListener('touchstart', e => { x0 = e.touches[0].clientX; }, { passive: true });
        sb.addEventListener('touchend', e => { if (x0 !== null && x0 - e.changedTouches[0].clientX > 60) close(); x0 = null; });
    })();

    // Wide tables scroll sideways on small screens instead of stretching the page
    document.querySelectorAll('main table.table').forEach(t => {
        if (t.closest('.table-responsive')) return;
        const w = document.createElement('div'); w.className = 'table-responsive';
        t.parentNode.insertBefore(w, t); w.appendChild(t);
    });
</script>
@stack('scripts')
</body>
</html>
