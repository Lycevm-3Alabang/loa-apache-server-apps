<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#12243b">
    <title>@yield('title', 'LOA Platform')</title>
    <style>
        :root {
            --ink: #12243b;
            --ink-soft: #37516d;
            --muted: #718198;
            --line: #dce5ef;
            --paper: #ffffff;
            --wash: #f3f7fb;
            --accent: #e36a49;
            --accent-dark: #c95032;
            --success: #176b58;
            --danger: #a43f3d;
            color-scheme: light;
        }

        * { box-sizing: border-box; }

        html, body { min-height: 100%; }

        body {
            margin: 0;
            background: var(--wash);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        a { color: var(--accent-dark); text-decoration: none; }
        a:hover { text-decoration: underline; }

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
            background:
                radial-gradient(circle at 82% 18%, rgba(227, 106, 73, 0.32), transparent 26%),
                linear-gradient(145deg, #132a43 0%, #102238 55%, #0c1b2c 100%);
            color: #f7fbff;
        }

        .brand-panel::before,
        .brand-panel::after {
            position: absolute;
            content: "";
            pointer-events: none;
            border: 1px solid rgba(255, 255, 255, 0.11);
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
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .brand-mark {
            display: grid;
            width: 2.65rem;
            height: 2.65rem;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 0.85rem;
            background: rgba(255, 255, 255, 0.11);
            box-shadow: 0 0.75rem 2rem rgba(0, 0, 0, 0.16);
            font-size: 0.84rem;
            letter-spacing: 0.03em;
        }

        .brand-copy { max-width: 31rem; margin: auto 0; padding: 4rem 0; }

        .brand-kicker {
            margin: 0 0 1.25rem;
            color: #f5ae91;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .brand-copy h2 {
            max-width: 26rem;
            margin: 0;
            font-size: clamp(2.4rem, 4.4vw, 4.8rem);
            line-height: 0.98;
            letter-spacing: -0.065em;
        }

        .brand-copy p {
            max-width: 25rem;
            margin: 1.6rem 0 0;
            color: #b8c9da;
            font-size: 1rem;
            line-height: 1.7;
        }

        .brand-footer {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            color: #9fb4c9;
            font-size: 0.75rem;
        }

        .brand-footer::before {
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 50%;
            background: #5bd0a5;
            box-shadow: 0 0 0 0.3rem rgba(91, 208, 165, 0.13);
            content: "";
        }

        .content-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(1.5rem, 5vw, 5rem);
        }

        .auth-card { width: min(100%, 29rem); }

        .mobile-lockup { display: none; }

        .card-header { margin-bottom: 2rem; }

        .eyebrow {
            margin: 0 0 0.9rem;
            color: var(--accent-dark);
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.17em;
            text-transform: uppercase;
        }

        .card-header h1 {
            margin: 0;
            color: var(--ink);
            font-size: clamp(2rem, 4vw, 3rem);
            line-height: 1.02;
            letter-spacing: -0.055em;
        }

        .card-intro {
            max-width: 26rem;
            margin: 1rem 0 0;
            color: var(--muted);
            font-size: 0.98rem;
            line-height: 1.65;
        }

        .alert {
            margin: 0 0 1.25rem;
            padding: 0.85rem 1rem;
            border: 1px solid;
            border-radius: 0.8rem;
            font-size: 0.86rem;
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

        .auth-form { display: grid; gap: 1.2rem; }

        .field { display: grid; gap: 0.5rem; }

        .field label {
            color: var(--ink-soft);
            font-size: 0.78rem;
            font-weight: 750;
            letter-spacing: 0.02em;
        }

        .field input {
            width: 100%;
            min-height: 3.3rem;
            padding: 0.8rem 0.95rem;
            border: 1px solid var(--line);
            border-radius: 0.75rem;
            outline: none;
            background: var(--paper);
            color: var(--ink);
            font: inherit;
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }

        .field input:hover { border-color: #b9c8d8; }
        .field input:focus { border-color: #6b9abd; box-shadow: 0 0 0 0.23rem rgba(63, 123, 163, 0.14); }
        .field input[readonly] { background: #eef4f8; color: var(--ink-soft); }

        .form-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .field-hint,
        .password-hint {
            color: var(--muted);
            font-size: 0.76rem;
            line-height: 1.5;
        }

        .form-link { font-size: 0.8rem; font-weight: 700; }

        .password-hint { display: block; margin-top: 0.1rem; }

        .account-chip {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 0.2rem;
            padding: 0.75rem 0.85rem;
            border: 1px solid var(--line);
            border-radius: 0.8rem;
            background: rgba(255, 255, 255, 0.72);
        }

        .account-chip-icon {
            display: grid;
            width: 2rem;
            height: 2rem;
            place-items: center;
            border-radius: 0.6rem;
            background: #e4eff6;
            color: #306487;
            font-size: 0.72rem;
            font-weight: 800;
        }

        .account-chip span { display: block; color: var(--muted); font-size: 0.68rem; }
        .account-chip strong { display: block; margin-top: 0.12rem; color: var(--ink-soft); font-size: 0.82rem; font-weight: 700; overflow-wrap: anywhere; }

        .button {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            min-height: 3.3rem;
            padding: 0.8rem 1rem 0.8rem 1.15rem;
            border: 0;
            border-radius: 0.75rem;
            background: var(--accent);
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-size: 0.88rem;
            font-weight: 800;
            box-shadow: 0 0.8rem 1.5rem rgba(227, 106, 73, 0.2);
            transition: background 160ms ease, transform 160ms ease, box-shadow 160ms ease;
        }

        .button:hover { background: var(--accent-dark); box-shadow: 0 1rem 1.8rem rgba(227, 106, 73, 0.27); transform: translateY(-1px); }
        .button:focus-visible { outline: 3px solid rgba(63, 123, 163, 0.35); outline-offset: 3px; }

        .button-arrow {
            display: grid;
            width: 1.8rem;
            height: 1.8rem;
            place-items: center;
            border-radius: 0.5rem;
            background: rgba(255, 255, 255, 0.18);
            font-size: 1rem;
        }

        .back-link { display: inline-block; margin-top: 1.6rem; font-size: 0.82rem; font-weight: 700; }

        .card-footer {
            margin-top: 2.2rem;
            color: #99a7b7;
            font-size: 0.72rem;
            line-height: 1.5;
        }

        @media (max-width: 820px) {
            .auth-shell { grid-template-columns: 1fr; }
            .brand-panel { display: none; }
            .content-panel { align-items: flex-start; padding: 2rem 1.25rem 2.5rem; }
            .auth-card { width: min(100%, 30rem); margin: 0 auto; }
            .mobile-lockup { display: inline-flex; align-items: center; gap: 0.65rem; margin-bottom: 3rem; color: var(--ink); font-size: 0.75rem; font-weight: 850; letter-spacing: 0.15em; text-transform: uppercase; }
            .mobile-lockup .brand-mark { border-color: #c7d5e2; background: #e9f0f5; color: var(--ink); box-shadow: none; }
        }

        @media (max-width: 420px) {
            .content-panel { padding-inline: 1rem; }
            .card-header { margin-bottom: 1.6rem; }
            .card-header h1 { font-size: 2.15rem; }
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
                <h2>Start where your work begins.</h2>
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
