<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premium Application Workspace - testapp</title>
    <!-- Built-in rich modern styles -->
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            --card-bg: rgba(255, 255, 255, 0.05);
            --card-border: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --accent: #6366f1;
        }
        body {
            margin: 0; padding: 0; font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg-gradient); color: var(--text-main);
            min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .container {
            width: 90%; max-width: 800px; padding: 3rem;
            background: var(--card-bg); backdrop-filter: blur(16px);
            border: 1px solid var(--card-border); border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3); text-align: center;
        }
        h1 { font-size: 2.5rem; margin-bottom: 0.5rem; font-weight: 800; letter-spacing: -0.05em; }
        .badge {
            display: inline-block; padding: 0.35rem 1rem; font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.1em; border-radius: 999px;
            background: rgba(99,102,241,0.2); color: #a5b4fc; margin-bottom: 1.5rem;
        }
        .btn {
            display: inline-block; margin-top: 2rem; padding: 0.85rem 2rem; font-size: 1rem; font-weight: 600;
            background: var(--accent); color: white; border: none; border-radius: 12px; cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(99,102,241,0.3); }
    </style>
</head>
<body data-spp-navigation="spa">

    <div class="container">
        <div class="badge">Self-Contained Mode: strtoupper($mode)</div>
        <h1>Welcome to testapp</h1>
        <p style="color: var(--text-dim); line-height: 1.6; margin-top: 1rem;">
            This entire application layer operates within isolated containment boundaries. 
            Local routing rules and service structures reside dynamically inside 
            <code style="color: #cbd5e1;">/src/testapp/etc/</code> seamlessly.
        </p>
        
        <!-- Demonstrates zero-JS declarative action calling instantly -->
        <button class="btn" onclick="alert('Workspace instance launched cleanly!')">Explore Environment</button>
    </div>

</body>
</html>