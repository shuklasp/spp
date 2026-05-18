<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Saffron Aura Sovereign' }}</title>
    <link rel="stylesheet" href="{{ $assets_root ?? '' }}/saffron_minimal/theme.css">
</head>
<body>
    <div class="saffron-wrapper">
        <header style="padding: 2rem; border-bottom: 1px solid var(--glass-border);">
            <h1 style="margin: 0; color: var(--accent-primary);">🪷 {{ $title ?? 'Saffron Sovereign Engine' }}</h1>
        </header>
        <main style="padding: 2rem; max-width: 1200px; margin: 0 auto;">
            @yield('content')
        </main>
    </div>
</body>
</html>
