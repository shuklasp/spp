{{--
================================================================================
Base Layout — Samvaad
================================================================================

HOW TO USE:
In any Blade view, extend this layout:
  @extends('layouts.app')
  @section('title', 'My Page Title')
  @section('content')
    <p>Your page content here</p>
  @endsection

AVAILABLE SECTIONS:
  @section('title')    — Page title (appears in <title> tag)
  @section('styles')   — Extra CSS for this page
  @section('content')  — Main page content
  @section('scripts')  — Extra JS for this page

SPP DIRECTIVES AVAILABLE:
  @sppux('compName', ['prop' => 'val'])  — Mount SPP-UX component
  @sppform('formName')                    — Render YAML-driven form
  @sppauth ... @endsppauth               — Show only if authenticated
  @sppguest ... @endsppguest             — Show only if guest
================================================================================
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Samvaad')</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f8fafc;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #6366f1;
            --primary-light: rgba(99, 102, 241, 0.1);
            --surface: #ffffff;
            --border: #e2e8f0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; }
        .layout-container { max-width: 1200px; margin: 0 auto; padding: 0 2rem; }

        /* Navigation */
        .nav { background: var(--surface); border-bottom: 1px solid var(--border); padding: 1rem 0; }
        .nav-inner { display: flex; align-items: center; justify-content: space-between; }
        .nav-brand { font-weight: 800; font-size: 1.3rem; color: var(--primary); text-decoration: none; }
        .nav-links { display: flex; gap: 0.5rem; }
        .nav-links a { padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; color: var(--muted); font-weight: 500; font-size: 0.9rem; transition: all 0.2s; }
        .nav-links a:hover { background: var(--primary-light); color: var(--primary); }

        /* Main content */
        .main { padding: 2rem 0; }
        .card { background: var(--surface); border-radius: 16px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid var(--border); margin-bottom: 1.5rem; }
        h1 { font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; }
        h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
        h3 { font-size: 1.2rem; font-weight: 600; margin-bottom: 0.5rem; }
        p { color: var(--muted); }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }

        /* Footer */
        .footer { text-align: center; padding: 2rem 0; color: var(--muted); font-size: 0.85rem; border-top: 1px solid var(--border); margin-top: 3rem; }

        /* Badges */
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-primary { background: var(--primary-light); color: var(--primary); }
        .badge-success { background: rgba(34,197,94,0.1); color: #16a34a; }

        /* Buttons */
        .btn { display: inline-block; padding: 0.7rem 1.5rem; border-radius: 10px; border: none; font-weight: 600; font-family: inherit; cursor: pointer; text-decoration: none; font-size: 0.9rem; transition: all 0.2s; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.3); }
        .btn-outline { border: 1px solid var(--border); background: transparent; color: var(--text); }
    </style>
    @yield('styles')
</head>
<body>
    <nav class="nav">
        <div class="layout-container nav-inner">
            <a href="{{ $base_url }}" class="nav-brand">🚀 Samvaad</a>
            <div class="nav-links">
                <a href="{{ $base_url }}?q=home">Home</a>
                <a href="{{ $base_url }}?q=about">About</a>
                <a href="{{ $base_url }}?q=dashboard">Dashboard</a>
                <a href="{{ $base_url }}?q=contact">Contact</a>
                <a href="{{ $base_url }}?q=app">SPP-UX App</a>

                {{-- Auth-aware navigation --}}
                @sppauth
                    <a href="{{ $base_url }}?q=auth/logout" style="color: #ef4444;">Logout</a>
                @endsppauth
                @sppguest
                    <a href="{{ $base_url }}?q=login">Login</a>
                @endsppguest
            </div>
        </div>
    </nav>

    <main class="main">
        <div class="layout-container">
            @yield('content')
        </div>
    </main>

    <footer class="footer">
        <div class="layout-container">
            &copy; {{ date('Y') }} Samvaad &bull; Built with SPP Framework
        </div>
    </footer>

    @yield('scripts')
</body>
</html>