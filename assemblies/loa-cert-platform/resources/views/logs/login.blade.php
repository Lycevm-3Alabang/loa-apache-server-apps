@extends('layouts.app')

@section('title', 'Logs Login | Cert Platform')
@section('content')
    <div class="login-box">
        <h1>Application Logs</h1>
        <p>Enter the log viewer password to continue.</p>

        @if ($errors->has('password'))
            <div class="error-text" style="margin-bottom:1rem;">{{ $errors->first('password') }}</div>
        @endif

        <form method="post" action="{{ route('logs.login.post') }}">
            @csrf
            <div class="form-row">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autofocus>
            </div>
            <button class="button" type="submit" style="width:100%;">View Logs</button>
        </form>
    </div>
@endsection
