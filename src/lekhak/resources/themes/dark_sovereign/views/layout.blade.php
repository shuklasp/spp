<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Deep Slate Terminal' }}</title>
    <link rel="stylesheet" href="{{ $assets_root ?? '' }}/dark_sovereign/theme.css">
</head>
<body>
    <div class="terminal-wrapper">
        <header style="padding: 1.5rem; border-bottom: 1px solid var(--accent-primary); background: #020617;">
            <h1 style="margin: 0; color: var(--accent-primary); font-size: 1.5rem;">💻 [root@lekhak-admin ~]# {{ $title ?? 'Diagnostic Workspace' }}</h1>
        </header>
        <main style="padding: 2rem; max-width: 1400px; margin: 0 auto;">
            @yield('content')
        </main>
    </div>
</body>
</html>
