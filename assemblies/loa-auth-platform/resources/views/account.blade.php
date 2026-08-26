@extends('layouts.admin')

@section('title', 'Account | LOA Platform')

@section('content')
    <style>
        /* Console-chrome account page (dashboard-account.md v1.3 D16) */
        .account-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr)); }

        .account-card {
            padding: 1.25rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            background: var(--surface-secondary);
        }

        .account-card h2 { margin: 0 0 0.9rem; font-size: 1rem; font-weight: 700; }
        .account-list { display: grid; gap: 0.75rem; margin: 0; padding: 0; list-style: none; }
        .account-list li { display: flex; flex-direction: column; gap: 0.2rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border); }
        .account-list li:last-child { padding-bottom: 0; border-bottom: 0; }
        .account-label { color: var(--text-muted); font-size: 0.8125rem; }
        .account-value { font-weight: 600; overflow-wrap: anywhere; }
        .name-row { display: flex; align-items: baseline; justify-content: space-between; gap: 0.75rem; }
        .edit-link { font-size: 0.8125rem; font-weight: 600; }

        .name-form { display: grid; gap: 0.6rem; }
        .name-form label { color: var(--text-muted); font-size: 0.8125rem; font-weight: 600; }
        .name-form input {
            width: 100%;
            height: 2.5rem;
            padding: 0 0.75rem;
            border: 1px solid var(--border-strong, var(--border));
            border-radius: var(--radius-lg, var(--radius-xl));
            background: var(--surface);
            color: var(--text);
            font-family: inherit;
            font-size: 0.875rem;
        }
        .name-form input:focus { outline: 2px solid var(--brand-500); outline-offset: 1px; }
        .name-actions { display: flex; align-items: center; gap: 0.75rem; }

        .password-hint { margin: 0.5rem 0 0; color: var(--text-muted); font-size: 0.8125rem; }
    </style>

    <div class="page-header">
        <div>
            <h1>Your account</h1>
            <p>Your profile details on the LOA identity platform.</p>
        </div>
    </div>

    <div class="account-grid">
        <section class="account-card">
            <h2>Profile</h2>
            <dl class="account-list">
                <li>
                    <dt class="account-label">Email</dt>
                    <dd class="account-value" style="margin:0;">{{ $portalUser->email }}</dd>
                </li>
                <li>
                    <dt class="account-label">Status</dt>
                    <dd class="account-value" style="margin:0;">{{ ucfirst($portalUser->status) }}</dd>
                </li>
                <li>
                    <dt class="account-label">Name</dt>
                    <dd style="margin:0;">
                        @if ($editName)
                            <form class="name-form" method="post" action="{{ route('portal.account.name') }}">
                                @csrf
                                <label for="name">Account name</label>
                                <input id="name" name="name" type="text" value="{{ old('name', $portalUser->name) }}" required maxlength="255" autocomplete="name">
                                @error('name')
                                    <span style="color:var(--danger);font-size:0.75rem;">{{ $message }}</span>
                                @enderror
                                <div class="name-actions">
                                    <button class="button" type="submit">Save</button>
                                    <a class="edit-link" href="{{ route('portal.account') }}">Cancel</a>
                                </div>
                            </form>
                        @else
                            <div class="name-row">
                                <span class="account-value">{{ $portalUser->name }}</span>
                                <a class="edit-link" href="{{ route('portal.account', ['edit' => 'name']) }}">Edit</a>
                            </div>
                        @endif
                    </dd>
                </li>
            </dl>
        </section>

        <section class="account-card">
            <h2>Password</h2>
            <p style="margin:0;font-size:0.875rem;">
                We'll email a secure reset link to <strong>{{ $portalUser->email }}</strong>.
            </p>
            <form method="post" action="{{ route('portal.account.password.email') }}" style="margin-top:0.9rem;">
                @csrf
                <button class="button" type="submit">Change password</button>
            </form>
            <p class="password-hint">
                The reset link signs you out of all LOA applications — including this one — once you use it.
            </p>
        </section>
    </div>
@endsection
