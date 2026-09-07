<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $project['title'] }} News</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; margin: 0; padding: 2rem; color: #1e293b; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .post { border-bottom: 1px solid #e2e8f0; padding-bottom: 2rem; margin-bottom: 2rem; }
        .post h2 { margin: 0 0 0.5rem 0; color: #4f46e5; }
        .post .date { color: #64748b; font-size: 0.9rem; margin-bottom: 1rem; }
        .nav { margin-bottom: 2rem; }
        .nav a { text-decoration: none; color: #4f46e5; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="{{ \SPP\App::getBaseUrl() }}/project/{{ $project_id }}">&larr; Back to Project</a>
        </div>
        <h1>{{ $project['title'] }} News & Updates</h1>
        @if(isset($_GET['tag']))
            <p style="background: #e0e7ff; padding: 0.5rem 1rem; border-radius: 4px; display: inline-block;">Showing posts tagged: <strong>{{ htmlspecialchars($_GET['tag']) }}</strong> <a href="{{ \SPP\App::getBaseUrl() }}/blog/{{ $project_id }}" style="text-decoration:none; margin-left:10px;">&times; Clear</a></p>
        @endif
        @if(isset($_GET['category']))
            <p style="background: #e0e7ff; padding: 0.5rem 1rem; border-radius: 4px; display: inline-block;">Showing posts in category: <strong>{{ htmlspecialchars($_GET['category']) }}</strong> <a href="{{ \SPP\App::getBaseUrl() }}/blog/{{ $project_id }}" style="text-decoration:none; margin-left:10px;">&times; Clear</a></p>
        @endif
        
        @if(empty($posts))
            <p>No news posts available yet.</p>
        @else
            @foreach($posts as $post)
            <div class="post">
                <h2><a href="{{ \SPP\App::getBaseUrl() }}/blog/{{ $project_id }}/{{ $post['slug'] }}" style="text-decoration: none; color: inherit;">{{ $post['title'] }}</a></h2>
                <div class="date">
                    {{ is_numeric($post['date']) ? date('F j, Y', $post['date']) : date('F j, Y', strtotime($post['date'])) }} 
                    &bull; By {{ $post['author'] }}
                    @if(!empty($post['category']))
                        &bull; <a href="{{ \SPP\App::getBaseUrl() }}/blog/{{ $project_id }}?category={{ urlencode($post['category']) }}">{{ $post['category'] }}</a>
                    @endif
                    @if(!empty($post['tags']))
                        &bull; Tags: 
                        @foreach((is_array($post['tags']) ? $post['tags'] : explode(',', $post['tags'])) as $tag)
                            <a href="{{ \SPP\App::getBaseUrl() }}/blog/{{ $project_id }}?tag={{ urlencode(trim($tag)) }}" style="background:#e0e7ff; padding:2px 6px; border-radius:4px; font-size:0.8rem; text-decoration:none;">{{ trim($tag) }}</a>
                        @endforeach
                    @endif
                </div>
                <p>{!! $post['excerpt'] !!}</p>
                <a href="{{ \SPP\App::getBaseUrl() }}/blog/{{ $project_id }}/{{ $post['slug'] }}" style="color: #4f46e5; font-weight: 500;">Read More &rarr;</a>
            </div>
            @endforeach
        @endif
    </div>
</body>
</html>


