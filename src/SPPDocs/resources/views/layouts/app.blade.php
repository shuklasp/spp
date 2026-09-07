{{--
================================================================================
Base Layout — SPPDocs
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
    <title>@yield('title', 'SPPDocs')</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="@url('css/app.css')">
    <link rel="stylesheet" href="@url('theme-assets/default/custom.css?v=' . time())">
    @yield('styles')
</head>
<body>
    <nav class="nav">
        <div class="layout-container nav-inner">
            <a href="@url('')" class="nav-brand">🚀 SPPDocs</a>
            <div class="nav-links">
                <a href="@url('home')">Home</a>
                <a href="@url('about')">About</a>
                <a href="@url('dashboard')">Dashboard</a>
                <a href="@url('contact')">Contact</a>
                <a href="@url('app')">SPP-UX App</a>

                {{-- Auth-aware navigation --}}
                @sppauth
                    <a href="@url('auth/logout')" style="color: #ef4444;">Logout</a>
                @endsppauth
                @sppguest
                    <a href="@url('login')">Login</a>
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
            &copy; {{ date('Y') }} SPPDocs &bull; Built with SPP Framework
        </div>
    </footer>

    @yield('scripts')
</body>
</html>