@extends('layouts.app')
@section('title', 'API & Webhooks')
@section('content')
@if(session('new_key'))
    <div class="alert alert-warning">
        <h6><i class="bi bi-key"></i> Copy these now – they are shown only once</h6>
        <div class="small">Client: <strong>{{ session('new_key')['name'] }}</strong></div>
        <div class="input-group input-group-sm my-1"><span class="input-group-text">API key</span><input class="form-control font-monospace" value="{{ session('new_key')['key'] }}" readonly onclick="this.select()"></div>
        <div class="input-group input-group-sm"><span class="input-group-text">Webhook secret</span><input class="form-control font-monospace" value="{{ session('new_key')['secret'] }}" readonly onclick="this.select()"></div>
    </div>
@endif
<div class="row g-3">
<div class="col-lg-8">
    <div class="card mb-3"><div class="card-body">
        <div class="d-flex mb-2"><h6 class="mb-0">API clients</h6><a href="{{ route('admin.api.docs') }}" class="ms-auto small"><i class="bi bi-book"></i> API documentation</a></div>
        @forelse($clients as $c)
            <div class="border rounded p-2 mb-2">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <strong>{{ $c->name }}</strong> <code class="small">{{ $c->key_prefix }}…</code>
                    <span class="badge text-bg-{{ $c->is_active ? 'success' : 'secondary' }}">{{ $c->is_active ? 'active' : 'revoked' }}</span>
                    <span class="small text-muted">{{ $c->calls_today }} calls today · last used {{ $c->last_used_at?->diffForHumans() ?? 'never' }}</span>
                    <button class="btn btn-sm btn-link ms-auto" data-bs-toggle="collapse" data-bs-target="#c{{ $c->id }}">Edit</button>
                </div>
                <div class="small text-muted">Scopes: {{ implode(', ', $c->scopes ?? []) }} @if($c->webhook_url)· Webhook → {{ $c->webhook_url }} ({{ implode(', ', $c->events ?? []) ?: 'no events' }})@endif @if($c->allowed_ips)· IPs {{ $c->allowed_ips }}@endif</div>
                <div class="collapse mt-2" id="c{{ $c->id }}">
                    @include('admin._api-client-form', ['client' => $c])
                    <div class="d-flex gap-2 mt-2">
                        <form method="POST" action="{{ route('admin.api.regenerate', $c) }}" data-confirm="Issue a new key? The old key stops working immediately.">@csrf<button class="btn btn-sm btn-outline-warning">New key &amp; secret</button></form>
                        <form method="POST" action="{{ route('admin.api.test-webhook', $c) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Send test webhook</button></form>
                    </div>
                </div>
            </div>
        @empty <p class="text-muted">No API clients yet.</p> @endforelse
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <h6>Recent webhook deliveries</h6>
        <table class="table table-sm small">
            @forelse($deliveries as $d)
                <tr><td class="text-nowrap">{{ $d->created_at->format('d/m H:i') }}</td><td>{{ $d->client?->name }}</td><td><code>{{ $d->event }}</code></td>
                    <td>@if($d->delivered_at)<span class="badge text-bg-success">{{ $d->response_code }}</span>@else<span class="badge text-bg-danger">{{ $d->response_code ?: 'error' }}</span> <span class="text-muted">try {{ $d->attempts }}{{ $d->next_attempt_at ? ', next ' . $d->next_attempt_at->diffForHumans() : ', gave up' }}</span>@endif</td>
                    <td>@unless($d->delivered_at)<form method="POST" action="{{ route('admin.api.redeliver', $d) }}">@csrf<button class="btn btn-sm btn-link p-0">retry</button></form>@endunless</td></tr>
            @empty <tr><td class="text-muted">None.</td></tr> @endforelse
        </table>
    </div></div>

    <div class="card"><div class="card-body">
        <h6>Recent API requests</h6>
        <table class="table table-sm small mb-0">
            @forelse($logs as $l)
                <tr><td class="text-nowrap">{{ $l->created_at?->format('d/m H:i:s') }}</td><td>{{ $l->client?->name ?? 'unknown key' }}</td><td><code>{{ $l->method }} /{{ $l->path }}</code></td>
                    <td><span class="badge text-bg-{{ $l->status < 300 ? 'success' : ($l->status < 500 ? 'warning' : 'danger') }}">{{ $l->status }}</span></td><td>{{ $l->ip }}</td><td>{{ $l->duration_ms }}ms</td></tr>
            @empty <tr><td class="text-muted">None.</td></tr> @endforelse
        </table>
    </div></div>
</div>
<div class="col-lg-4"><div class="card"><div class="card-body">
    <h6>New API client</h6>
    <p class="small text-muted">For a bank or agent network posting collections, an employer's HR/payroll system, or a BI / data-warehouse tool. Give each system its own key with only the scopes it needs.</p>
    @include('admin._api-client-form', ['client' => null])
</div></div></div>
</div>
@endsection
