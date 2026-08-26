@extends('layouts.auth')

@section('title', 'Account | Lyceum of Alabang')
@section('eyebrow', 'LOA Platform')
@section('heading', 'Your account')
@section('intro', 'Your profile details on the LOA identity platform.')

@section('content')
    <style>
        .account-list { display: grid; gap: 0.75rem; margin: 0 0 1.25rem; padding: 0; list-style: none; }
        .account-list li { display: flex; flex-direction: column; gap: 0.2rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border); }
        .account-list dt, .account-label { color: var(--text-muted); font-size: 0.8125rem; }
        .account-value { font-weight: 600; overflow-wrap: anywhere; }
        .name-row { display: flex; align-items: baseline; justify-content: space-between; gap: 0.75rem; }
        .edit-link { font-size: 0.8125rem; font-weight: 600; }
    </style>

    <dl class="account-list">
        <li>
            <dt>Email</dt>
            <dd class="account-value">{{ $portalUser->email }}</dd>
        </li>
        <li>
            <dt>Status</dt>
            <dd class="account-value">{{ ucfirst($portalUser->status) }}</dd>
        </li>
        <li>
            <dt>Name</dt>
            <dd>
                @if ($editName)
                    <form class="auth-form" method="post" action="{{ route('portal.account.name') }}">
                        @csrf
                        <div class="field">
                            <label for="name">Account name</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $portalUser->name) }}" required maxlength="255" autocomplete="name">
                        </div>
                        <div class="form-row">
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

    <p><a href="{{ route('portal.account.password.show') }}">Change password</a></p>

    <p class="back-link"><a href="{{ route('portal.home') }}">Back to dashboard</a></p>
@endsection
