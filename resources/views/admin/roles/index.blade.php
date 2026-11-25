@extends('layouts.app')
@section('title', 'Roles & Permissions')
@section('content')
@php($me = auth()->user())
<div class="row g-3 mb-3">
    @foreach($roles as $r)
        <div class="col-6 col-md-4 col-xl-2"><div class="card h-100"><div class="card-body p-2">
            <div class="d-flex align-items-start"><a href="{{ route('admin.roles.edit', $r) }}" class="fw-semibold">{{ $r->label }}</a>
                @if($r->is_system)<span class="badge text-bg-light ms-auto">built-in</span>@endif</div>
            <div class="small text-muted">{{ $r->name === 'admin' ? 'all' : $r->permissions->count() }} permissions · {{ $r->users_count + $r->extra_users_count }} user(s)</div>
            @if($r->description)<div class="small mt-1">{{ \Illuminate\Support\Str::limit($r->description, 80) }}</div>@endif
        </div></div></div>
    @endforeach
    <div class="col-6 col-md-4 col-xl-2"><div class="card h-100 border-primary"><div class="card-body p-2">
        <form method="POST" action="{{ route('admin.roles.store') }}">@csrf
            <div class="fw-semibold small mb-1"><i class="bi bi-plus-circle"></i> New role</div>
            <input name="label" class="form-control form-control-sm mb-1" placeholder="e.g. Welfare Officer" required>
            <select name="copy_from" class="form-select form-select-sm mb-1"><option value="">Start empty</option>@foreach($roles as $r)@continue($r->name === 'admin')<option value="{{ $r->id }}">Copy {{ $r->label }}</option>@endforeach</select>
            <button class="btn btn-sm btn-primary w-100">Create</button></form>
    </div></div></div>
</div>

<form method="POST" action="{{ route('admin.roles.matrix') }}">@csrf @method('PUT')
<div class="card"><div class="card-body">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <h6 class="mb-0">Permission matrix</h6>
        <input type="search" id="permFilter" class="form-control form-control-sm" style="max-width:240px" placeholder="Filter permissions…">
        <span class="small text-muted">Administrator always has everything. @unless($me->isAdmin())You can only change permissions you hold yourself.@endunless</span>
        <button class="btn btn-sm btn-primary ms-auto"><i class="bi bi-save"></i> Save all changes</button>
    </div>
    <div class="table-responsive" style="max-height: 70vh">
    <table class="table table-sm table-hover align-middle mb-0" id="matrix">
        <thead class="sticky-top bg-white"><tr><th style="min-width:280px">Permission</th>
            @foreach($roles as $r)<th class="text-center small" style="min-width:90px"><a href="{{ route('admin.roles.edit', $r) }}">{{ $r->label }}</a></th>@endforeach</tr></thead>
        @foreach($catalogue as $group => $items)
            <tr class="table-light group-row"><td class="fw-semibold small text-uppercase">{{ $group }}</td>
                @foreach($roles as $r)<td class="text-center">@unless($r->isLocked())<input type="checkbox" class="form-check-input group-toggle" data-group="{{ \Illuminate\Support\Str::slug($group) }}" data-role="{{ $r->id }}" title="All in {{ $group }}">@endunless</td>@endforeach</tr>
            @foreach($items as $perm => $label)
                <tr class="perm-row" data-text="{{ strtolower($label . ' ' . $perm) }}"><td class="small">{{ $label }}<br><code class="text-muted" style="font-size:.7rem">{{ $perm }}</code></td>
                    @foreach($roles as $r)
                        @php($on = $r->name === 'admin' || $r->permissions->contains('name', $perm))
                        <td class="text-center">
                            @if($r->isLocked())<i class="bi bi-check-lg text-success"></i>
                            @else<input type="checkbox" name="grid[{{ $r->id }}][]" value="{{ $perm }}" class="form-check-input" data-group="{{ \Illuminate\Support\Str::slug($group) }}" data-role="{{ $r->id }}"
                                @checked($on) @disabled(! $me->isAdmin() && ! in_array($perm, $mine))>
                                @if(! $me->isAdmin() && ! in_array($perm, $mine) && $on)<input type="hidden" name="grid[{{ $r->id }}][]" value="{{ $perm }}">@endif
                            @endif</td>
                    @endforeach</tr>
            @endforeach
        @endforeach
    </table>
    </div>
</div></div>
</form>
@endsection
@push('scripts')
<script>
document.querySelectorAll('.group-toggle').forEach(t => {
    const boxes = () => [...document.querySelectorAll(`input[name^="grid"][data-group="${t.dataset.group}"][data-role="${t.dataset.role}"]:not(:disabled)`)];
    const sync = () => { const b = boxes(); t.checked = b.length && b.every(x => x.checked); t.indeterminate = !t.checked && b.some(x => x.checked); };
    t.addEventListener('change', () => boxes().forEach(x => x.checked = t.checked));
    boxes().forEach(x => x.addEventListener('change', sync)); sync();
});
document.getElementById('permFilter').addEventListener('input', e => {
    const q = e.target.value.toLowerCase();
    document.querySelectorAll('#matrix .perm-row').forEach(r => r.style.display = r.dataset.text.includes(q) ? '' : 'none');
});
</script>
@endpush
