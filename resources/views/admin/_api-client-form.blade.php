<form method="POST" action="{{ $client ? route('admin.api.update', $client) : route('admin.api.store') }}">@csrf @if($client) @method('PUT') @endif
    <input name="name" value="{{ $client?->name }}" class="form-control form-control-sm mb-2" placeholder="System name e.g. Equity Bank collections" required>
    <div class="small fw-semibold">Scopes</div>
    @foreach(\App\Models\ApiClient::SCOPES as $k => $label)
        <div class="form-check small"><input class="form-check-input" type="checkbox" name="scopes[]" value="{{ $k }}" id="s{{ $client?->id }}{{ $k }}" @checked(in_array($k, $client?->scopes ?? []))>
            <label class="form-check-label" for="s{{ $client?->id }}{{ $k }}"><code>{{ $k }}</code> – {{ $label }}</label></div>
    @endforeach
    <input name="allowed_ips" value="{{ $client?->allowed_ips }}" class="form-control form-control-sm my-2" placeholder="Allowed IPs (comma list, blank = any)">
    <input name="webhook_url" value="{{ $client?->webhook_url }}" class="form-control form-control-sm mb-1" placeholder="Webhook URL (https://…)">
    <div class="small fw-semibold">Webhook events</div>
    @foreach(\App\Models\ApiClient::EVENTS as $k => $label)
        <div class="form-check small"><input class="form-check-input" type="checkbox" name="events[]" value="{{ $k }}" id="e{{ $client?->id }}{{ $k }}" @checked(in_array($k, $client?->events ?? []))>
            <label class="form-check-label" for="e{{ $client?->id }}{{ $k }}"><code>{{ $k }}</code></label></div>
    @endforeach
    @if($client)<div class="form-check small mt-1"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="a{{ $client->id }}" @checked($client->is_active)><label class="form-check-label" for="a{{ $client->id }}">Active (untick to revoke)</label></div>@endif
    <button class="btn btn-sm btn-success w-100 mt-2">{{ $client ? 'Save' : 'Create client & key' }}</button>
</form>
