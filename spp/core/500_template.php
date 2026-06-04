<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>500 Internal Server Error</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --bg: #09090b; 
            --surface: rgba(24, 24, 27, 0.7); 
            --primary: #f43f5e; 
            --text: #f4f4f5; 
            --muted: #a1a1aa; 
            --border: rgba(255,255,255,0.08);
            --glow: rgba(244, 63, 94, 0.15);
        }
        body { 
            margin: 0; 
            background: var(--bg); 
            background-image: radial-gradient(circle at center, var(--glow), transparent 600px);
            color: var(--text); 
            font-family: 'Inter', sans-serif; 
            line-height: 1.6; 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .container { 
            max-width: 600px; 
            padding: 50px; 
            background: var(--surface); 
            backdrop-filter: blur(12px);
            border-radius: 20px; 
            border: 1px solid var(--border);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); 
            animation: fadeUp 0.6s ease-out forwards; 
            opacity: 0; 
            transform: translateY(20px); 
        }
        .error-code { font-size: 80px; font-weight: 700; margin: 0; color: var(--primary); line-height: 1; letter-spacing: -2px; }
        .message { font-size: 24px; font-weight: 600; margin: 20px 0; }
        .desc { color: var(--muted); margin-bottom: 30px; font-size: 16px; }
        .btn { 
            display: inline-block; 
            background: var(--primary); 
            color: #fff; 
            padding: 12px 24px; 
            border-radius: 8px; 
            text-decoration: none; 
            font-weight: 600; 
            transition: all 0.2s; 
            border: none;
            cursor: pointer;
        }
        .btn:hover { background: #e11d48; transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(225, 29, 72, 0.3); }
        @keyframes fadeUp {
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="error-code">500</h1>
        <div class="message">Internal Server Error</div>
        <p class="desc">Oops! Something went wrong on our end. We're currently looking into it.</p>
        <a href="/" class="btn">Return Home</a>
    </div>
</body>
</html>
