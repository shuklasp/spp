<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->title }}</title>
    <link rel="stylesheet" href="/school1/res/spp/css/spp.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --bg: #0f172a;
            --text: #f1f5f9;
        }
        body {
            margin: 0;
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        section {
            padding: 80px 0;
        }
        
        /* Hero Block */
        .hero {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            text-align: center;
            min-height: 60vh;
            display: flex;
            align-items: center;
        }
        .hero h1 {
            font-size: 4rem;
            margin-bottom: 20px;
            background: linear-gradient(to right, #60a5fa, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero p {
            font-size: 1.25rem;
            color: #94a3b8;
            max-width: 600px;
            margin: 0 auto 30px;
        }
        
        /* Features Block */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }
        .feature-card {
            background: #1e293b;
            padding: 30px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        /* Button Style */
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    @foreach($blocks as $block)
        @php $content = $block->getContent(); @endphp
        
        @if($block->block_type == 'hero')
            <section class="hero">
                <div class="container">
                    <h1>{{ $content['title'] ?? '' }}</h1>
                    <p>{{ $content['subtitle'] ?? '' }}</p>
                    <a href="#" class="btn btn-primary">{{ $content['button_text'] ?? 'Learn More' }}</a>
                </div>
            </section>
        @elseif($block->block_type == 'text')
            <section class="text-content">
                <div class="container">
                    {!! $content['content'] ?? '' !!}
                </div>
            </section>
        @elseif($block->block_type == 'features')
            <section class="features">
                <div class="container">
                    <h2 style="text-align:center; margin-bottom: 50px;">{{ $content['title'] ?? 'Our Features' }}</h2>
                    <div class="features-grid">
                        @foreach($content['items'] ?? [] as $item)
                            <div class="feature-card">
                                <div style="font-size: 2rem; margin-bottom: 15px;">{{ $item['icon'] }}</div>
                                <h3>{{ $item['text'] }}</h3>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @elseif($block->block_type == 'dynamic_list')
            <section class="dynamic-list">
                <div class="container">
                    <h2 style="margin-bottom: 30px;">{{ $content['title'] ?? 'Latest Content' }}</h2>
                    @php $entities = $block->resolveEntities(); @endphp
                    <div class="entities-grid">
                        @foreach($entities as $entity)
                            <div class="entity-card glass-panel" style="margin-bottom: 15px; padding: 20px;">
                                <h3>{{ $entity->title ?? ($entity->username ?? 'Untitled') }}</h3>
                                <p style="color: #94a3b8; font-size: 0.9rem;">
                                    Type: {{ $entity->bundle ?? 'Generic' }} | 
                                    Created: {{ $entity->created }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    @endforeach
</body>
</html>
