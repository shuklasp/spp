<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SatyaLab Network</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #050505;
            --card-bg: rgba(255, 255, 255, 0.03);
            --card-border: rgba(255, 255, 255, 0.08);
            --card-hover-border: rgba(255, 255, 255, 0.2);
            --text-primary: #ededed;
            --text-secondary: #a1a1aa;
            --accent-glow: rgba(249, 115, 22, 0.15); /* Saffron Glow */
            --accent-color: #f97316; /* Saffron Orange */
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(249, 115, 22, 0.08), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(234, 88, 12, 0.08), transparent 25%);
            margin: 0;
            padding: 0;
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .navbar {
            width: 100%;
            padding: 1.5rem 3rem;
            display: flex;
            justify-content: flex-start;
            box-sizing: border-box;
            border-bottom: 1px solid var(--card-border);
            backdrop-filter: blur(12px);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .navbar .brand {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .navbar .brand span {
            color: var(--accent-color);
        }

        .hero {
            text-align: center;
            padding: 6rem 2rem 4rem;
            max-width: 800px;
        }
        .hero h1 {
            font-size: 4.5rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            margin: 0 0 1.5rem;
            line-height: 1.1;
            /* Metallic Saffron Gradient */
            background: linear-gradient(135deg, #fff 0%, #ffedd5 30%, #f97316 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero p {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin: 0 auto;
            line-height: 1.6;
            max-width: 600px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
            max-width: 1200px;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 4rem;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 2rem;
            text-align: left;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(249, 115, 22, 0.4), transparent);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .card:hover {
            transform: translateY(-4px);
            border-color: rgba(249, 115, 22, 0.3);
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.5), 0 0 25px -5px var(--accent-glow);
        }
        
        .card:hover::before {
            opacity: 1;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .badge {
            display: inline-block;
            background: rgba(249, 115, 22, 0.1);
            border: 1px solid rgba(249, 115, 22, 0.2);
            color: #fed7aa;
            padding: 0.35rem 0.75rem;
            border-radius: 99px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .card h2 {
            margin: 0 0 0.75rem;
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: -0.02em;
        }

        .card p {
            color: var(--text-secondary);
            line-height: 1.6;
            margin: 0 0 2rem;
            flex-grow: 1;
            font-size: 0.95rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            color: var(--text-primary);
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all 0.2s ease;
            width: fit-content;
        }

        .btn:hover {
            background: var(--accent-color);
            color: #fff;
            border-color: var(--accent-color);
            box-shadow: 0 4px 15px rgba(249, 115, 22, 0.3);
        }
        
        .card:hover .btn {
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* Subtle animated grid background */
        .bg-grid {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-size: 40px 40px;
            background-image: linear-gradient(to right, rgba(255, 255, 255, 0.02) 1px, transparent 1px),
                              linear-gradient(to bottom, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
            z-index: -1;
            pointer-events: none;
            mask-image: radial-gradient(circle at center, black 0%, transparent 80%);
            -webkit-mask-image: radial-gradient(circle at center, black 0%, transparent 80%);
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>

    <nav class="navbar">
        <div class="brand">Satya<span>Lab</span></div>
    </nav>

    <div class="hero">
        <h1>Building the Future</h1>
        <p>Explore the flagship frameworks, educational platforms, and knowledge repositories engineered by SatyaLab.</p>
    </div>
    
    <div class="grid">
        @foreach($projects as $id => $project)
        <div class="card">
            <div class="card-header">
                @if($id === 'spp') <span class="badge">Flagship</span> @endif
                @if($id === 'lekhak') <span class="badge">CMS</span> @endif
                @if($id === 'vshiksha') <span class="badge">EdTech</span> @endif
                @if($id === 'indiwiki') <span class="badge">Wiki</span> @endif
                @if($id === 'sppdocs') <span class="badge">Docs</span> @endif
            </div>
            <h2>{{ $project['title'] }}</h2>
            <p>{{ $project['description'] }}</p>
            <a href="{{ \SPP\App::getBaseUrl() }}/project/{{ $id }}" class="btn">Explore {{ $project['title'] }}</a>
        </div>
        @endforeach
    </div>
</body>
</html>
