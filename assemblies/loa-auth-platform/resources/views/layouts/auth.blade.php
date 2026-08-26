<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#020618">
    <title>@yield('title', 'Lyceum of Alabang')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --surface: #ffffff;
            --surface-muted: #f2f2f7;
            --surface-secondary: #f9f9fb;
            --text: #1c1c1e;
            --text-secondary: #3a3a3c;
            --text-muted: #8e8e93;
            --border: #d1d1d6;
            --border-strong: #c6c6c8;
            --brand-50: #fffbe6;
            --brand-500: #fcca13;
            --brand-600: #e0b311;
            --brand-700: #c49a0e;
            --danger: #a43f3d;
            --success: #176b58;
            --slate-950: #020618;
            --slate-800: #1d293d;
            --slate-700: #314158;
            --slate-400: #90a1b9;
            --slate-300: #cad5e2;
            --radius-lg: 0.5rem;
            --radius-xl: 0.75rem;
            --radius-2xl: 1rem;
            --shadow-sm: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px -1px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.04);
            --shadow-lg: 0 12px 32px rgba(0,0,0,0.1), 0 4px 8px rgba(0,0,0,0.04);
            color-scheme: light;
        }

        * { box-sizing: border-box; }
        html, body { min-height: 100%; }

        body {
            margin: 0;
            background: var(--surface-muted);
            color: var(--text);
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        a { color: var(--brand-600); text-decoration: none; font-weight: 600; }
        a:hover { color: var(--brand-700); text-decoration: underline; }

        .auth-shell {
            display: grid;
            grid-template-columns: minmax(320px, 0.86fr) minmax(480px, 1.14fr);
            min-height: 100svh;
        }

        .brand-panel {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            padding: clamp(2rem, 5vw, 5rem);
            background: var(--slate-950);
            color: #f8fafc;
        }

        .brand-panel::before,
        .brand-panel::after {
            position: absolute;
            content: "";
            pointer-events: none;
            border: 1px solid rgba(255, 255, 255, 0.08);
            transform: rotate(28deg);
        }

        .brand-panel::before {
            right: -16%;
            bottom: 12%;
            width: 62%;
            aspect-ratio: 1;
            border-radius: 42% 58% 60% 40%;
        }

        .brand-panel::after {
            right: -9%;
            bottom: 20%;
            width: 42%;
            aspect-ratio: 1;
            border-radius: 50%;
        }

        .brand-lockup,
        .brand-copy,
        .brand-footer { position: relative; z-index: 1; }

        .brand-lockup {
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            width: fit-content;
            color: #fff;
            font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .brand-mark {
            display: grid;
            width: 2.65rem;
            height: 2.65rem;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: var(--radius-xl);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0.75rem 2rem rgba(0, 0, 0, 0.2);
            font-size: 0.84rem;
            letter-spacing: 0.03em;
        }

        .brand-copy { max-width: 31rem; margin: auto 0; padding: 4rem 0; }

        .brand-kicker {
            margin: 0 0 1.25rem;
            color: var(--brand-500);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .brand-copy h2 {
            max-width: 26rem;
            margin: 0;
            font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
            font-size: clamp(2.4rem, 4.4vw, 4.8rem);
            line-height: 0.98;
            letter-spacing: -0.04em;
            font-weight: 700;
        }

        .brand-copy p {
            max-width: 25rem;
            margin: 1.6rem 0 0;
            color: var(--slate-400);
            font-size: 1rem;
            line-height: 1.7;
        }

        .brand-footer {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            color: var(--slate-400);
            font-size: 0.75rem;
        }

        .brand-footer::before {
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 0 0.3rem rgba(34, 197, 94, 0.15);
            content: "";
        }

        .content-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(1.5rem, 5vw, 5rem);
        }

        .auth-card {
            width: min(100%, 24rem);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
        }

        .mobile-lockup { display: none; }

        .card-header { margin-bottom: 1.75rem; }

        .eyebrow {
            margin: 0 0 0.75rem;
            color: var(--brand-600);
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .card-header h1 {
            margin: 0;
            color: var(--text);
            font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
            font-size: clamp(1.75rem, 3.5vw, 2.25rem);
            line-height: 1.1;
            letter-spacing: -0.03em;
            font-weight: 700;
        }

        .card-intro {
            max-width: 26rem;
            margin: 0.75rem 0 0;
            color: var(--text-muted);
            font-size: 0.9375rem;
            line-height: 1.6;
        }

        .alert {
            margin: 0 0 1.25rem;
            padding: 0.75rem 1rem;
            border: 1px solid;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .alert-success {
            border-color: #b8e1d5;
            background: #effaf6;
            color: var(--success);
        }

        .alert-error {
            border-color: #edc5c0;
            background: #fff5f3;
            color: var(--danger);
        }

        .alert-error ul { margin: 0; padding-left: 1.15rem; }

        .auth-form { display: grid; gap: 1rem; }

        .field { display: grid; gap: 0.375rem; }

        .field label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .field input {
            width: 100%;
            height: 2.75rem;
            padding: 0.625rem 0.875rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-xl);
            outline: none;
            background: var(--surface-secondary);
            color: var(--text);
            font-family: inherit;
            font-size: 0.9375rem;
            transition: border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
        }

        .field input::placeholder { color: var(--text-muted); }
        .field input:hover { border-color: var(--border-strong); }
        .field input:focus {
            border-color: var(--brand-500);
            background: var(--surface);
            box-shadow: 0 0 0 4px rgba(252, 202, 19, 0.15);
        }
        .field input[readonly] { background: var(--surface-muted); color: var(--text-muted); cursor: default; }

        .form-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .field-hint,
        .password-hint {
            color: var(--text-muted);
            font-size: 0.8125rem;
            line-height: 1.5;
        }

        .form-link { font-size: 0.8125rem; font-weight: 600; }
        .password-hint { display: block; margin-top: 0.1rem; }

        .button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            height: 2.75rem;
            padding: 0 1.25rem;
            border: 1px solid var(--brand-700);
            border-radius: var(--radius-xl);
            background: var(--brand-600);
            color: #fff;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.875rem;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
            transition: background 160ms ease, transform 160ms ease, box-shadow 160ms ease;
        }

        .button:hover { background: var(--brand-700); box-shadow: 0 2px 6px rgba(0,0,0,0.12); }
        .button:active { transform: scale(0.97); }
        .button:focus-visible { outline: 3px solid rgba(252, 202, 19, 0.3); outline-offset: 2px; }

        .button-arrow {
            display: none;
        }

        .back-link { display: inline-block; margin-top: 1.25rem; font-size: 0.8125rem; font-weight: 600; }

        .card-footer {
            margin-top: 1.5rem;
            color: var(--text-muted);
            font-size: 0.75rem;
            line-height: 1.5;
            text-align: center;
        }

        @media (max-width: 820px) {
            .auth-shell { grid-template-columns: 1fr; }
            .brand-panel { display: none; }
            .content-panel { align-items: flex-start; padding: 2rem 1.25rem 2.5rem; }
            .auth-card { width: min(100%, 30rem); margin: 0 auto; box-shadow: none; border: none; padding: 0; }
            .mobile-lockup { display: inline-flex; align-items: center; gap: 0.65rem; margin-bottom: 2rem; color: var(--text); font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
            .mobile-lockup .brand-mark { border-color: var(--border); background: var(--surface-muted); color: var(--text); box-shadow: none; }
        }

        @media (max-width: 420px) {
            .content-panel { padding-inline: 1rem; }
            .card-header { margin-bottom: 1.5rem; }
            .card-header h1 { font-size: 1.75rem; }
            .form-row { align-items: flex-start; flex-direction: column; gap: 0.35rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition-duration: 0.01ms !important; animation-duration: 0.01ms !important; }
        }
    </style>
</head>
<body>
    <div class="auth-shell">
        <aside class="brand-panel" aria-label="LOA Platform">
            <a class="brand-lockup" href="{{ route('login') }}">
                <span class="brand-mark" aria-hidden="true">LOA</span>
                <span>LOA Platform</span>
            </a>

            <div class="brand-copy">
                <p class="brand-kicker">One identity. Every platform.</p>
                <h2>Lyceum of Alabang Single Sign-On.</h2>
                <p>Secure access to the LOA digital campus, from consultation to certificates and everything in between.</p>
            </div>

            <div class="brand-footer">Identity services operational</div>
        </aside>

        <main class="content-panel">
            <div class="auth-card">
                <a class="mobile-lockup" href="{{ route('login') }}">
                    <span class="brand-mark" aria-hidden="true">LOA</span>
                    <span>LOA Platform</span>
                </a>

                <header class="card-header">
                    <p class="eyebrow">@yield('eyebrow', 'Identity')</p>
                    <h1>@yield('heading')</h1>
                    <p class="card-intro">@yield('intro')</p>
                </header>

                @if (session('status'))
                    <div class="alert alert-success" role="status">{{ session('status') }}</div>
                @endif

                @if (session('error'))
                    <div class="alert alert-error" role="alert">{{ session('error') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-error" role="alert">
                        @if ($errors->has('credentials'))
                            {{ $errors->first('credentials') }}
                        @else
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                @yield('content')

                <p class="card-footer">LOA Platform uses secure, time-limited access links and encrypted connections.</p>
            </div>
        </main>
    </div>
</body>
</html>
