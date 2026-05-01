<!DOCTYPE html>
<html>
<head>
    <title>{{ $title }}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #f1f5f9; padding: 2rem; }
        .container { max-width: 800px; margin: 0 auto; background: rgba(30, 41, 59, 0.7); padding: 2rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); }
        h1 { color: #38bdf8; }
        .menu { margin-bottom: 2rem; }
        .menu a { color: #38bdf8; text-decoration: none; margin-right: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="menu">
            <a href="/lekhak">Home</a>
            <a href="/sppadmin#lekhak">Workbench</a>
        </div>
        <h1>{{ $title }}</h1>
        <p>Lekhak CMS is now running with its own Application Handler.</p>
        <div class="content">
            <p>This is the default homepage rendered via Blade.</p>
        </div>
    </div>
</body>
</html>
