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
            padding: 60px 0;
        }
        
        .main-layout {
            display: flex;
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        main {
            flex: 1;
        }
        aside {
            width: 300px;
            flex-shrink: 0;
        }

        /* Hero Block */
        .hero {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            text-align: center;
            min-height: 40vh;
            display: flex;
            align-items: center;
        }
        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            background: linear-gradient(to right, #60a5fa, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero p {
            font-size: 1.2rem;
            color: #94a3b8;
            max-width: 600px;
            margin: 0 auto 30px;
        }
        
        /* Features Block */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .feature-card {
            background: rgba(255,255,255,0.03);
            padding: 25px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        header, footer {
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        footer {
            border-top: 1px solid rgba(255,255,255,0.05);
            border-bottom: none;
            padding: 40px 0;
        }

        /* Button Style */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 6px;
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
            transform: translateY(-1px);
        }
        /* Section Themes */
        .section-glass { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px); border-top: 1px solid rgba(255,255,255,0.1); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .section-dark { background: #020617; }
        .section-primary { background: var(--primary); }
        .section-gradient { background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%); }
        
        .padding-none { padding: 0 !important; }
        .padding-small { padding: 30px 0 !important; }
        .padding-medium { padding: 60px 0 !important; }
        .padding-large { padding: 100px 0 !important; }
        
        .align-left { text-align: left; }
        .align-center { text-align: center; }
        .align-right { text-align: right; }
        
        .animate-fade { animation: fadeIn 1s ease-out; }
        .animate-slide-up { animation: slideUp 0.8s ease-out; }
        .animate-zoom { animation: zoomIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1); }
        
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes zoomIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
    </style>
</head>
<body>
    @if(isset($regions['header']))
        <header>
            @foreach($regions['header'] as $block)
                @include('partials.block', ['block' => $block])
            @endforeach
        </header>
    @endif

    <div class="main-layout">
        <main>
            @if(isset($regions['main']))
                @foreach($regions['main'] as $block)
                    @include('partials.block', ['block' => $block])
                @endforeach
            @endif
        </main>
        
        @if(isset($regions['sidebar']))
            <aside>
                @foreach($regions['sidebar'] as $block)
                    @include('partials.block', ['block' => $block])
                @endforeach
            </aside>
        @endif
    </div>

    @if(isset($regions['footer']))
        <footer>
            <div class="container">
                @foreach($regions['footer'] as $block)
                    @include('partials.block', ['block' => $block])
                @endforeach
            </div>
        </footer>
    @endif
</body>
</html>
