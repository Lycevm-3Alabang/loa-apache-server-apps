@extends('layouts.admin')

@section('title', 'Create Tenant | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Tenants', 'url' => route('admin.tenants')],
        ['label' => 'Create Tenant'],
    ]])
    <div class="page-header">
        <div>
            <h1>Create Tenant</h1>
            <p>Add a new client tenant to the platform.</p>
        </div>
    </div>

    <div class="detail-card">
        <form method="post" action="{{ route('admin.tenants.store') }}" class="form-grid">
            @csrf

            <div class="form-row">
                <label for="slug">Slug</label>
                <input type="text" id="slug" name="slug" value="{{ old('slug') }}" pattern="[a-z0-9][a-z0-9\-]*" required placeholder="e.g. consult">
                <span class="hint">Lowercase letters, numbers, hyphens only. Immutable after creation.</span>
                @error('slug') <span class="error-text">{{ $message }}</span> @enderror
            </div>

            <div class="form-row">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required placeholder="e.g. Consult Platform">
                @error('name') <span class="error-text">{{ $message }}</span> @enderror
            </div>

            <div class="form-row">
                <label for="app_url">App URL</label>
                <input type="url" id="app_url" name="app_url" value="{{ old('app_url') }}" placeholder="https://aces-api.lyceumalabang.edu.ph">
                <span class="hint">The tenant's production application URL (optional).</span>
                @error('app_url') <span class="error-text">{{ $message }}</span> @enderror
            </div>

            <div class="form-row">
                <label for="dev_app_url">Dev App URL</label>
                <input type="url" id="dev_app_url" name="dev_app_url" value="{{ old('dev_app_url') }}" placeholder="http://localhost:9001">
                <span class="hint">The tenant's development application URL (optional). Used when APP_ENV is not production.</span>
                @error('dev_app_url') <span class="error-text">{{ $message }}</span> @enderror
            </div>

            <div class="form-row">
                <label for="redirect_origins">Redirect Origins</label>
                <textarea id="redirect_origins" name="redirect_origins" placeholder="https://aces-api.lyceumalabang.edu.ph">{{ old('redirect_origins') }}</textarea>
                <span class="hint">Comma-separated list of allowed production redirect origins (optional).</span>
                @error('redirect_origins') <span class="error-text">{{ $message }}</span> @enderror
            </div>

            <div class="form-row">
                <label for="dev_redirect_origins">Dev Redirect Origins</label>
                <textarea id="dev_redirect_origins" name="dev_redirect_origins" placeholder="http://localhost:9001">{{ old('dev_redirect_origins') }}</textarea>
                <span class="hint">Comma-separated list of allowed dev redirect origins (optional). Used when APP_ENV is not production.</span>
                @error('dev_redirect_origins') <span class="error-text">{{ $message }}</span> @enderror
            </div>

            <div style="display:flex;gap:0.75rem;margin-top:0.5rem;">
                <button class="button" type="submit">Create tenant</button>
                <a class="button button-ghost" href="{{ route('admin.tenants') }}" style="border-color:var(--border);color:var(--text-secondary);">Cancel</a>
            </div>
        </form>
    </div>
@endsection
