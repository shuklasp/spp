<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <link rel="stylesheet" href="/school1/res/spp/css/spp.css">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; background: #0f172a; color: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .error-container { text-align: center; padding: 40px; background: rgba(255,255,255,0.03); border-radius: 24px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px); max-width: 500px; width: 90%; }
        h1 { font-size: 4rem; margin: 0; background: linear-gradient(to right, #60a5fa, #a855f7); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        h2 { font-size: 1.5rem; margin-top: 10px; color: #94a3b8; }
        p { color: #64748b; margin: 20px 0 30px; }
        .btn { display: inline-block; padding: 12px 24px; background: #2563eb; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: background 0.2s; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>404</h1>
        <h2>{{ $title }}</h2>
        <p>{{ $message }}</p>
        <a href="/school1/lekhak/" class="btn">Back to Home</a>
    </div>
</body>
</html>
