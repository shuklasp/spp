<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $project['title'] }} | SatyaLab</title>
    <style>
        :root { --primary: #3b82f6; --primary-hover: #2563eb; --bg: #ffffff; --text: #1e293b; --text-light: #64748b; }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 0; line-height: 1.6; }
        .nav { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 3rem; border-bottom: 1px solid #f1f5f9; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(8px); position: sticky; top: 0; z-index: 50; }
        .nav .logo { font-weight: 800; font-size: 1.25rem; text-decoration: none; color: var(--text); display: flex; align-items: center; gap: 0.5rem; }
        .nav .logo span { color: var(--primary); }
        .nav-links { display: flex; gap: 2rem; align-items: center; }
        .nav-links a { text-decoration: none; color: var(--text-light); font-weight: 500; font-size: 0.95rem; transition: color 0.2s; }
        .nav-links a:hover { color: var(--primary); }
        
        .hero { padding: 8rem 2rem; text-align: center; position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(59,130,246,0.1) 0%, rgba(255,255,255,0) 50%); z-index: -1; }
        .hero h1 { font-size: 4.5rem; font-weight: 800; letter-spacing: -0.05em; margin: 0 0 1.5rem; line-height: 1.1; background: linear-gradient(135deg, #1e293b, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero p { font-size: 1.5rem; color: var(--text-light); max-width: 700px; margin: 0 auto 3rem; }
        .hero-actions { display: flex; gap: 1rem; justify-content: center; }
        .btn { display: inline-flex; align-items: center; padding: 0.875rem 1.75rem; border-radius: 99px; font-weight: 600; text-decoration: none; font-size: 1.1rem; transition: all 0.2s; }
        .btn-primary { background: var(--primary); color: white; box-shadow: 0 4px 14px 0 rgba(59, 130, 246, 0.39); }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-2px); }
        .btn-secondary { background: #f1f5f9; color: var(--text); }
        .btn-secondary:hover { background: #e2e8f0; }

        .features { padding: 6rem 2rem; max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem; }
        .feature-card { padding: 2rem; background: #f8fafc; border-radius: 16px; border: 1px solid #f1f5f9; }
        .feature-card h3 { margin-top: 0; font-size: 1.25rem; color: var(--text); }
        .feature-card p { color: var(--text-light); margin-bottom: 0; }
    </style>
</head>
<body>
    <nav class="nav">
        <a href="{{ \SPP\App::getBaseUrl() }}/" class="logo">Satya<span>Lab</span></a>
        <div class="nav-links">
            <a href="{{ \SPP\App::getBaseUrl() }}/docs/{{ $project_id }}/{{ $project['default_version'] }}">Documentation</a>
            @if(isset($project['news_feed']))
                <a href="{{ \SPP\App::getBaseUrl() }}/{{ $project['news_feed'] }}">Blog</a>
            @endif
        </div>
    </nav>
    
    <main>
        <section class="hero">
            <h1>{{ $project['title'] }}</h1>
            <p>{{ $project['description'] }}</p>
            <div class="hero-actions">
                <a href="{{ \SPP\App::getBaseUrl() }}/docs/{{ $project_id }}/{{ $project['default_version'] }}" class="btn btn-primary">Get Started</a>
                <a href="#features" class="btn btn-secondary">Why SPP?</a>
            </div>
        </section>

        <section id="features" class="features">
            <div class="feature-card">
                <h3>Polyglot Kernel</h3>
                <p>Build applications natively integrating PHP, Node.js, Python, and C++ through a unified, high-performance event loop.</p>
            </div>
            <div class="feature-card">
                <h3>LiveComponent Architecture</h3>
                <p>Develop rich, interactive frontends without writing JavaScript. Real-time DOM diffing directly from your backend controllers.</p>
            </div>
            <div class="feature-card">
                <h3>Built-in Workflow Engine</h3>
                <p>Orchestrate complex business logic with native support for state machines, saga patterns, and resilient compensating transactions.</p>
            </div>
        </section>
    </main>
</body>
</html>
