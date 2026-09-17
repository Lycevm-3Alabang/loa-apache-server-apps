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
        html, body { min-height: 100%; margin: 0; }

        body {
            background: var(--surface-muted);
            color: var(--text);
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        a { color: var(--brand-600); text-decoration: none; font-weight: 600; }
        a:hover { color: var(--brand-700); text-decoration: underline; }

        .auth-shell {
            min-height: 100vh;
            background: var(--surface-muted);
        }

        .content-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
            max-width: 100%;
        }

        .auth-card {
            width: 100%;
            max-width: 360px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-2xl);
            padding: 1.5rem;
            margin: 1.5rem auto 0;
            box-shadow: var(--shadow-md);
        }

        .mobile-lockup { display: none; }

        .card-header { margin-bottom: 1.25rem; }

        .eyebrow {
            margin: 0 0 0.5rem;
            color: var(--brand-600);
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .card-header h1 {
            margin: 0;
            color: var(--text);
            font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
            font-size: 1.6rem;
            line-height: 1.2;
            letter-spacing: -0.03em;
            font-weight: 700;
        }

        .card-intro {
            max-width: 100%;
            margin: 0.5rem 0 0;
            color: var(--text-muted);
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .alert {
            margin: 0 0 0.75rem;
            padding: 0.5rem 0.75rem;
            border: 1px solid;
            border-radius: var(--radius-lg);
            font-size: 0.8125rem;
            line-height: 1.4;
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

        .alert-error ul { margin: 0; padding-left: 0.9rem; }

        .auth-form { display: grid; gap: 0.75rem; }

        .field { display: grid; gap: 0.25rem; }

        .field label {
            color: var(--text-secondary);
            font-size: 0.75rem;
            font-weight: 500;
        }

        .field input {
            width: 100%;
            height: 2.5rem;
            padding: 0.5rem 0.65rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            outline: none;
            background: var(--surface-secondary);
            color: var(--text);
            font-family: inherit;
            font-size: 0.875rem;
            transition: border-color 150ms ease, box-shadow 150ms ease, background 150ms ease;
        }

        .field input::placeholder { color: var(--text-muted); }
        .field input:hover { border-color: var(--border-strong); }
        .field input:focus {
            border-color: var(--brand-500);
            background: var(--surface);
            box-shadow: 0 0 0 3px rgba(252, 202, 19, 0.15);
        }
        .field input[readonly] { background: var(--surface-muted); color: var(--text-muted); cursor: default; }

        .form-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .form-link { font-size: 0.75rem; font-weight: 600; }

        .button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            width: 100%;
            height: 2.5rem;
            padding: 0 1rem;
            border: 1px solid var(--brand-700);
            border-radius: var(--radius-xl);
            background: var(--brand-600);
            color: #fff;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.8125rem;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
            transition: background 150ms ease, transform 150ms ease, box-shadow 150ms ease;
        }

        .button:hover { background: var(--brand-700); box-shadow: 0 2px 6px rgba(0,0,0,0.12); }
        .button:active { transform: scale(0.96); }
        .button:focus-visible { outline: 3px solid rgba(252, 202, 19, 0.3); outline-offset: 2px; }

        .button-arrow { display: none; }

        .back-link { display: inline-block; margin-top: 0.75rem; font-size: 0.75rem; font-weight: 600; }

        .card-footer {
            margin-top: 1rem;
            color: var(--text-muted);
            font-size: 0.65rem;
            line-height: 1.4;
            text-align: center;
        }

        @media (min-width: 641px) {
            .auth-shell {
                display: flex;
                min-height: 100vh;
            }

            .content-panel {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: clamp(1.5rem, 4vw, 4rem);
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .auth-shell { display: flex; }

            .content-panel {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 1.5rem;
                width: 100%;
            }

            .auth-card { width: 100%; max-width: none; margin: 0; }

            .form-row { flex-direction: column; align-items: flex-start; gap: 0.35rem; }

            .button { height: 2.5rem; }

            .back-link { margin-top: 0.5rem; }
        }
    </style>
</head>
<body>
    <div class="auth-shell">
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