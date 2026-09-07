<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $project['title'] }} | SatyaLab</title>
    <style>
        :root { --primary: #10b981; --bg: #f8fafc; --text: #0f172a; --text-light: #475569; }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 0; }
        .nav { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 3rem; background: #fff; border-bottom: 1px solid #e2e8f0; }
        .nav .logo { font-weight: 800; font-size: 1.25rem; text-decoration: none; color: var(--text); display: flex; align-items: center; gap: 0.5rem; }
        .nav .logo span { color: var(--primary); }
        .nav-links a { text-decoration: none; color: var(--text-light); font-weight: 500; }
        .hero { padding: 8rem 2rem; text-align: center; background: #fff; border-bottom: 1px solid #e2e8f0; }
        .hero h1 { font-size: 3.5rem; margin: 0 0 1rem; color: var(--text); }
        .hero p { font-size: 1.25rem; color: var(--text-light); max-width: 600px; margin: 0 auto 2rem; }
        .btn { display: inline-flex; align-items: center; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; text-decoration: none; background: var(--primary); color: #fff; }
    </style>
</head>
<body>
    <nav class="nav">
        <a href="{{ \SPP\App::getBaseUrl() }}/" class="logo">Satya<span>Lab</span></a>
        <div class="nav-links">
            <a href="{{ \SPP\App::getBaseUrl() }}/docs/{{ $project_id }}/{{ $project['default_version'] }}">Documentation</a>
        </div>
    </nav>
    <main class="hero">
        <h1>{{ $project['title'] }}</h1>
        <p>{{ $project['description'] }}</p>
        <a href="{{ \SPP\App::getBaseUrl() }}/docs/{{ $project_id }}/{{ $project['default_version'] }}" class="btn">Read Documentation</a>
    </main>
</body>
</html>
