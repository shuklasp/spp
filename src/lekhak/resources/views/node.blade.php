<!DOCTYPE html>
@load_node
<html>
<head>
    <title>{{ $node->title ?? 'Node' }} | Lekhak CMS</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #f1f5f9; padding: 2rem; }
        .container { max-width: 800px; margin: 0 auto; background: rgba(30, 41, 59, 0.7); padding: 2rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); }
        h1 { color: #38bdf8; }
        .metadata { font-size: 0.9rem; color: #94a3b8; margin-bottom: 2rem; }
    </style>
</head>
<body>
    <div class="container">
        <a href="{{ $base_url }}" style="color: #38bdf8; text-decoration: none;">&larr; Back Home</a>
        <h1>{{ $node->title ?? 'Untitled Node' }}</h1>
        <div class="metadata">
            Published on {{ $node->created ?? 'Unknown' }}
        </div>
        <div class="content">
            {!! $node->body ?? 'No content available.' !!}
        </div>
    </div>
</body>
</html>
