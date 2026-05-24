<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $node->title }} - SPP Framework</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.4);
            --bg-base: #0f172a;
            --bg-surface: #1e293b;
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
        }
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--bg-base);
            color: var(--text-main);
            overflow-x: hidden;
            line-height: 1.7;
        }
        .header {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding: 1.2rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-logo {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            background: linear-gradient(to right, #818cf8, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }
        
        .hero-section {
            padding: 100px 20px 60px;
            text-align: center;
            position: relative;
            background: radial-gradient(circle at 50% 0%, rgba(99,102,241,0.15) 0%, transparent 70%);
        }
        
        .title-badge {
            display: inline-block;
            background: rgba(99,102,241,0.1);
            color: #818cf8;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(99,102,241,0.2);
            animation: fadeIn 1s ease-out;
        }

        .node-title {
            font-family: 'Outfit', sans-serif;
            font-size: 4rem;
            line-height: 1.1;
            margin: 0 auto 1.5rem;
            max-width: 900px;
            font-weight: 700;
            letter-spacing: -1px;
            animation: slideUp 0.8s ease-out;
        }

        .node-meta {
            color: var(--text-dim);
            font-size: 1rem;
            animation: slideUp 1s ease-out;
        }
        
        .node-meta span {
            margin: 0 10px;
        }

        .content-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
            font-size: 1.1rem;
            color: #cbd5e1;
        }

        .content-container h2, .content-container h3 {
            color: #f8fafc;
            font-family: 'Outfit', sans-serif;
            margin-top: 2.5rem;
        }
        
        .content-container p {
            margin-bottom: 1.5rem;
        }

        .content-container a {
            color: #818cf8;
            text-decoration: none;
            border-bottom: 1px solid rgba(129, 140, 248, 0.3);
            transition: all 0.3s ease;
        }

        .content-container a:hover {
            border-bottom-color: #818cf8;
            color: #a5b4fc;
        }

        .content-container img {
            max-width: 100%;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            margin: 2rem 0;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .content-container blockquote {
            border-left: 4px solid var(--primary);
            margin: 2rem 0;
            padding: 1rem 2rem;
            background: rgba(255,255,255,0.02);
            border-radius: 0 12px 12px 0;
            font-style: italic;
            color: #94a3b8;
        }
        
        .content-container pre {
            background: #0f172a;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.1);
            overflow-x: auto;
            font-size: 0.9rem;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        footer {
            border-top: 1px solid rgba(255,255,255,0.05);
            padding: 40px 20px;
            text-align: center;
            color: var(--text-dim);
            font-size: 0.9rem;
            margin-top: 60px;
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="{{ $web_root }}/" class="header-logo">SPP CMS</a>
        <nav style="display: flex; gap: 20px;">
            <a href="{{ $web_root }}/" style="color: var(--text-dim); text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: color 0.3s;">Home</a>
            @if(isset($_SESSION['spp_user']))
                <a href="{{ $admin_root }}/content" style="color: #818cf8; text-decoration: none; font-size: 0.9rem; font-weight: 500;">Admin Console</a>
            @else
                <a href="{{ $web_root }}/lekhak/login" style="color: var(--text-dim); text-decoration: none; font-size: 0.9rem; font-weight: 500;">Login</a>
            @endif
        </nav>
    </header>

    <main>
        <section class="hero-section">
            <div class="title-badge">{{ $node->bundle ?? 'Article' }}</div>
            <h1 class="node-title">{{ $node->title }}</h1>
            <div class="node-meta">
                <span>Published on {{ date('F j, Y', strtotime($node->created)) }}</span>
                <span>•</span>
                <span>{{ str_word_count(strip_tags($node->body)) }} words</span>
            </div>
        </section>

        <section class="content-container">
            {!! $node->body !!}
        </section>
    </main>

    <footer>
        <p>&copy; {{ date('Y') }} SPP Framework Enterprise. Powered by Lekhak Engine.</p>
    </footer>
</body>
</html>
