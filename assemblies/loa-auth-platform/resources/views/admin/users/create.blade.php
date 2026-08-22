@extends('layouts.admin')

@section('title', 'Create User | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Users', 'url' => route('admin.users')],
        ['label' => 'Create User'],
    ]])
    <div class="page-header">
        <div>
            <h1>Create User</h1>
            <p>Add a new user account to the platform.</p>
        </div>
    </div>

    <div class="detail-card">
        <form method="post" action="{{ route('admin.users.store') }}" class="form-grid">
            @csrf

            <div class="form-row">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="user@example.com">
                @error('email') <span class="error-text">{{ $message }}</span> @enderror
            </div>

            <div class="form-row">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required placeholder="Full name">
                @error('name') <span class="error-text">{{ $message }}</span> @enderror
            </div>

            <div class="form-row">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Leave blank to auto-generate">
                <span class="hint">Min 8 chars, must include uppercase, lowercase, number, and special character. Leave blank to auto-generate a random password.</span>
                @error('password') <span class="error-text">{{ $message }}</span> @enderror
            </div>

            <div class="form-row">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                    <option value="disabled" @selected(old('status') === 'disabled')>Disabled</option>
                </select>
                @error('status') <span class="error-text">{{ $message }}</span> @enderror
            </div>

            <div style="display:flex;gap:0.75rem;margin-top:0.5rem;">
                <button class="button" type="submit">Create user</button>
                <a class="button button-ghost" href="{{ route('admin.users') }}" style="border-color:var(--border);color:var(--text-secondary);">Cancel</a>
            </div>
        </form>
    </div>
@endsection
