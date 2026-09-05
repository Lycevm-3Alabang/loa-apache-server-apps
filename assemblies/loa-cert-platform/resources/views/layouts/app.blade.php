<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Cert Platform Logs')</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: #f2f2f7;
            color: #1c1c1e;
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .875rem 2rem;
            background: #020618;
            color: #f8fafc;
        }
        .topbar-brand {
            font-size: .8rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #f8fafc;
            text-decoration: none;
        }
        .topbar a { color: #f8fafc; text-decoration: none; font-size: .8125rem; }
        .topbar a:hover { text-decoration: underline; }
        .container { width: min(100% - 2rem, 72rem); margin: 2rem auto; }
        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .page-header h1 {
            font-size: clamp(1.5rem, 3vw, 2rem);
            letter-spacing: -.02em;
        }
        .page-header p { color: #8e8e93; font-size: .875rem; margin-top: .35rem; }
        .page-actions { display: inline-flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
        .button {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            height: 2.5rem;
            padding: 0 1.1rem;
            border: 1px solid #c49a0e;
            border-radius: .75rem;
            background: #e0b311;
            color: #fff;
            cursor: pointer;
            font-family: inherit;
            font-size: .8125rem;
            font-weight: 600;
            text-decoration: none;
        }
        .button:hover { background: #c49a0e; }
        .button-ghost {
            background: transparent;
            border-color: #d1d1d6;
            color: #3a3a3c;
        }
        .button-ghost:hover { background: #f9f9fb; }
        .panel {
            background: #fff;
            border: 1px solid #d1d1d6;
            border-radius: 1rem;
            overflow: hidden;
        }
        .login-box {
            max-width: 24rem;
            margin: 4rem auto;
            background: #fff;
            border: 1px solid #d1d1d6;
            border-radius: 1rem;
            padding: 2rem;
        }
        .login-box h1 { font-size: 1.25rem; margin-bottom: .5rem; }
        .login-box p { color: #8e8e93; font-size: .875rem; margin-bottom: 1.5rem; }
        .form-row { margin-bottom: 1rem; }
        .form-row label { display: block; font-size: .8125rem; font-weight: 600; color: #3a3a3c; margin-bottom: .35rem; }
        .form-row input {
            width: 100%;
            height: 2.5rem;
            padding: .5rem .75rem;
            border: 1.5px solid #d1d1d6;
            border-radius: .75rem;
            outline: none;
            background: #f9f9fb;
            font-family: inherit;
            font-size: .875rem;
        }
        .form-row input:focus { border-color: #fcca13; box-shadow: 0 0 0 4px rgba(252,202,19,.15); }
        .error-text { font-size: .75rem; color: #a43f3d; margin-top: .25rem; }
        pre {
            margin: 0;
            padding: 1.25rem;
            overflow: auto;
            max-height: 70vh;
            font-size: .8125rem;
            line-height: 1.6;
            background: #1e293b;
            color: #e2e8f0;
            border-radius: 0 0 1rem 1rem;
            white-space: pre-wrap;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <header class="topbar">
        <span class="topbar-brand">LOA Cert Platform</span>
        <nav>
            @auth('web')
                <form method="post" action="{{ route('logs.logout') }}" style="display:inline;">
                    @csrf
                    <button type="submit" style="background:none;border:none;color:#f8fafc;font-size:.8125rem;cursor:pointer;font-family:inherit;">Sign out</button>
                </form>
            @endauth
        </nav>
    </header>
    <main class="container">
        @yield('content')
    </main>
</body>
</html>
