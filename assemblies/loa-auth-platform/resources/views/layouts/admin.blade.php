<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#020618">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin | LOA Platform')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
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
            --brand-500: #fcca13;
            --brand-600: #e0b311;
            --brand-700: #c49a0e;
            --danger: #a43f3d;
            --success: #176b58;
            --slate-950: #020618;
            --slate-800: #1d293d;
            --slate-700: #314158;
            --slate-400: #90a1b9;
            --radius-lg: 0.5rem;
            --radius-xl: 0.75rem;
            --radius-2xl: 1rem;
            --shadow-sm: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px -1px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.04);
            color-scheme: light;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--surface-muted);
            color: var(--text);
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        a { color: var(--brand-600); text-decoration: none; font-weight: 600; }
        a:hover { color: var(--brand-700); text-decoration: underline; }

        .admin-topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.875rem clamp(1.25rem, 4vw, 3rem);
            background: var(--slate-950);
            color: #f8fafc;
        }

        .brand-lockup {
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            color: #fff;
            font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .brand-mark {
            display: grid;
            width: 2.2rem;
            height: 2.2rem;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: var(--radius-xl);
            background: rgba(255, 255, 255, 0.08);
            font-size: 0.75rem;
            letter-spacing: 0.03em;
        }

        .topbar-nav {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .topbar-nav .user-chip {
            color: var(--slate-400);
            font-size: 0.8125rem;
        }

        .topbar-nav form { margin: 0; }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            height: 2.5rem;
            padding: 0 1.1rem;
            border: 1px solid var(--brand-700);
            border-radius: var(--radius-xl);
            background: var(--brand-600);
            color: #fff;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.8125rem;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
            transition: background 160ms ease, transform 160ms ease;
        }

        .button:hover { background: var(--brand-700); }
        .button:active { transform: scale(0.97); }

        .button-ghost {
            background: transparent;
            border-color: rgba(255, 255, 255, 0.25);
            color: #f8fafc;
        }

        .button-ghost:hover { background: rgba(255, 255, 255, 0.1); }

        .button-danger {
            background: var(--danger);
            border-color: var(--danger);
        }

        .admin-container {
            width: min(100% - 2rem, 72rem);
            margin: 2rem auto clamp(2rem, 6vw, 4rem);
        }

        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        .page-header h1 {
            margin: 0;
            font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
            font-size: clamp(1.5rem, 3vw, 2rem);
            letter-spacing: -0.02em;
        }

        .page-header p {
            margin: 0.35rem 0 0;
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .alert {
            margin: 0 0 1.25rem;
            padding: 0.75rem 1rem;
            border: 1px solid;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .alert-success { border-color: #b8e1d5; background: #effaf6; color: var(--success); }
        .alert-error { border-color: #edc5c0; background: #fff5f3; color: var(--danger); }

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .panel-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border);
        }

        .filters {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            flex-wrap: wrap;
        }

        .filters form { display: flex; align-items: center; gap: 0.625rem; flex-wrap: wrap; }

        .field input,
        .field select {
            height: 2.5rem;
            padding: 0.5rem 0.75rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-xl);
            outline: none;
            background: var(--surface-secondary);
            color: var(--text);
            font-family: inherit;
            font-size: 0.875rem;
        }

        .field input:focus,
        .field select:focus {
            border-color: var(--brand-500);
            background: var(--surface);
            box-shadow: 0 0 0 4px rgba(252, 202, 19, 0.15);
        }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        thead th {
            padding: 0.75rem 1.25rem;
            background: var(--surface-secondary);
            color: var(--text-muted);
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            text-align: left;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        tbody td {
            padding: 0.875rem 1.25rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        tbody tr:last-child td { border-bottom: none; }

        .cell-user { min-width: 16rem; }

        .cell-user strong { display: block; color: var(--text); }
        .cell-user span { color: var(--text-muted); font-size: 0.8125rem; }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .badge-active { background: #effaf6; color: var(--success); }
        .badge-disabled { background: #fff5f3; color: var(--danger); }
        .badge-locked { background: #f4f0ff; color: #5b3fb2; }

        .muted { color: var(--text-muted); font-size: 0.8125rem; }

        .row-actions { white-space: nowrap; text-align: right; }

        .row-actions form { display: inline; margin: 0; }

        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            padding: 1rem 1.25rem;
            border-top: 1px solid var(--border);
            font-size: 0.8125rem;
            color: var(--text-muted);
        }

        .pagination nav .pagination-links {
            display: flex;
            gap: 0.375rem;
            flex-wrap: wrap;
        }

        .pagination nav a,
        .pagination nav span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2rem;
            height: 2rem;
            padding: 0 0.5rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            color: var(--text-secondary);
            font-size: 0.8125rem;
            text-decoration: none;
        }

        .pagination nav a:hover { border-color: var(--border-strong); }
        .pagination nav .active span {
            background: var(--slate-950);
            border-color: var(--slate-950);
            color: #fff;
            font-weight: 600;
        }

        .empty-state {
            padding: 3rem 1.5rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .form-grid {
            display: grid;
            gap: 1rem;
            max-width: 40rem;
        }

        .form-row {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .form-row label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .form-row input,
        .form-row textarea,
        .form-row select {
            height: 2.5rem;
            padding: 0.5rem 0.75rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-xl);
            outline: none;
            background: var(--surface-secondary);
            color: var(--text);
            font-family: inherit;
            font-size: 0.875rem;
        }

        .form-row textarea { height: auto; min-height: 4rem; resize: vertical; }

        .form-row input:focus,
        .form-row textarea:focus,
        .form-row select:focus {
            border-color: var(--brand-500);
            background: var(--surface);
            box-shadow: 0 0 0 4px rgba(252, 202, 19, 0.15);
        }

        .form-row .hint { font-size: 0.75rem; color: var(--text-muted); }
        .form-row .error-text { font-size: 0.75rem; color: var(--danger); }

        .detail-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .detail-card h2 {
            margin: 0 0 1rem;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr));
            gap: 0.75rem 1.5rem;
        }

        .detail-field label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.2rem;
        }

        .detail-field span {
            font-size: 0.875rem;
            color: var(--text);
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .section-header h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .inline-form {
            display: flex;
            gap: 0.5rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .inline-form .field { flex: 1; min-width: 12rem; }

        .perm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .perm-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.6rem;
            border-radius: var(--radius-lg);
            font-size: 0.8125rem;
            cursor: pointer;
            transition: background 120ms;
        }

        .perm-check:hover { background: var(--surface-secondary); }
        .perm-check input[type="checkbox"] { accent-color: var(--brand-600); }

        .topbar-link:hover { color: #fff !important; text-decoration: none; }

        @media (max-width: 720px) {
            .admin-container { width: 100%; margin-top: 1rem; }
            .page-header { align-items: flex-start; flex-direction: column; }
            .panel-toolbar { flex-direction: column; align-items: stretch; }
            .filters form { flex-direction: column; align-items: stretch; }
            .filters form .field { flex: 1; }
            .filters form .field input,
            .filters form .field select { width: 100%; }
        }
    </style>
</head>
<body>
    <header class="admin-topbar">
        <a class="brand-lockup" href="{{ route('admin.users') }}">
            <span class="brand-mark" aria-hidden="true">LOA</span>
            <span>LOA Admin</span>
        </a>
        <nav class="topbar-nav" aria-label="Admin">
            <a href="{{ route('admin.users') }}" class="topbar-link" style="color:#f8fafc;font-size:0.8125rem;">Users</a>
            <a href="{{ route('admin.tenants') }}" class="topbar-link" style="color:#f8fafc;font-size:0.8125rem;">Tenants</a>
            <span class="user-chip">{{ Auth::guard('web')->user()?->name ?? 'Admin' }}</span>
            <form method="post" action="{{ route('admin.logout') }}">
                @csrf
                <button class="button button-ghost" type="submit">Sign out</button>
            </form>
        </nav>
    </header>

    <main class="admin-container">
        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-error" role="alert">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>
</body>
</html>
