<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set Password | LOA Platform</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; color: #1e293b; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 2rem; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2.5rem; width: 100%; max-width: 420px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        p { color: #64748b; font-size: 0.875rem; margin-bottom: 1.5rem; }
        .field { margin-bottom: 1rem; }
        label { display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; }
        input[type="password"] { width: 100%; height: 2.5rem; padding: 0 0.75rem; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; font-family: inherit; }
        input[type="password"]:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
        .button { display: block; width: 100%; height: 2.75rem; border: none; border-radius: 8px; background: #2563eb; color: #fff; font-size: 0.875rem; font-weight: 600; cursor: pointer; }
        .button:hover { background: #1d4ed8; }
        .error { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 0.75rem 1rem; color: #dc2626; font-size: 0.8125rem; margin-bottom: 1rem; }
        .email-display { font-weight: 600; color: #1e293b; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Set your password</h1>
        <p>Account: <span class="email-display">{{ $email }}</span></p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('set-password.process') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="8" autofocus>
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8">
            </div>

            <button class="button" type="submit">Set password &amp; activate</button>
        </form>
    </div>
</body>
</html>
