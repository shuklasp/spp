<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pagetitle ?? 'Lekhak CMS' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ $theme_path }}/css/style.css">
</head>
<body class="theme-premium">
    <header class="site-header">
        <div class="container">
            <div class="logo">Lekhak</div>
            <nav class="main-nav">
                {!! $header ?? '' !!}
            </nav>
        </div>
    </header>

    <div class="main-layout container">
        <aside class="sidebar">
            {!! $sidebar ?? '' !!}
        </aside>

        <main class="content-area">
            {!! $content ?? '' !!}
        </main>
    </div>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; {{ date('Y') }} Lekhak CMS. All rights reserved.</p>
            {!! $footer ?? '' !!}
        </div>
    </footer>
</body>
</html>
