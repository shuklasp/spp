<?php
/**
 * Mobile Studio Pro - Secure Login
 * High-fidelity, isolated authentication portal.
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studio Login | Satya Studio Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #ea580c;
            --primary-glow: rgba(234, 88, 12, 0.4);
            --bg: #06070d;
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            overflow: hidden;
            background-image:
                radial-gradient(circle at 10% 20%, rgba(234, 88, 12, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(249, 115, 22, 0.1) 0%, transparent 40%);
        }

        .login-card {
            background: var(--glass);
            backdrop-filter: blur(24px);
            border: 1px solid var(--glass-border);
            padding: 50px;
            border-radius: 32px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.6);
            animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            width: 100px;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            filter: drop-shadow(0 10px 20px var(--primary-glow));
        }

        .logo img {
            width: 100%;
            height: auto;
            object-fit: contain;
        }

        h1 {
            font-size: 1.8rem;
            text-align: center;
            margin-bottom: 8px;
            font-weight: 800;
        }

        p {
            text-align: center;
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.9rem;
            margin-bottom: 40px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 8px;
            margin-left: 4px;
        }

        input {
            width: 100%;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--glass-border);
            padding: 14px 20px;
            border-radius: 12px;
            color: white;
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.3s;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.06);
            box-shadow: 0 0 0 4px var(--primary-glow);
        }

        .btn {
            width: 100%;
            padding: 16px;
            border-radius: 12px;
            border: none;
            background: linear-gradient(to right, #ea580c, #f97316);
            color: white;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s;
            box-shadow: 0 4px 12px var(--primary-glow);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px var(--primary-glow);
        }

        .btn:active {
            transform: translateY(0);
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.3);
        }

        .error {
            background: rgba(248, 113, 113, 0.1);
            color: #f87171;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            border: 1px solid rgba(248, 113, 113, 0.2);
            display: none;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="logo">
            <img src="api.php?action=asset&name=satya_logo.png" alt="Satya Studio Logo">
        </div>
        <h1>Studio Access</h1>
        <p>Enter your developer credentials</p>

        <div id="error-msg" class="error">Invalid username or password.</div>

        <form id="login-form">
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="username" placeholder="e.g. admin" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" id="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn">Launch Studio</button>
        </form>

        <div class="footer">
            Satya Studio Pro &bull; Enterprise Edition
        </div>
    </div>

    <script>
        document.getElementById('login-form').onsubmit = async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const error = document.getElementById('error-msg');

            btn.disabled = true;
            btn.innerText = 'Authenticating...';
            error.style.display = 'none';

            try {
                const res = await fetch('api.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username: document.getElementById('username').value,
                        password: document.getElementById('password').value
                    })
                });
                const data = await res.json();

                if (data.success) {
                    window.location.href = './';
                } else {
                    error.innerText = data.message || 'Authentication failed.';
                    error.style.display = 'block';
                    btn.disabled = false;
                    btn.innerText = 'Launch Studio';
                }
            } catch (err) {
                error.innerText = 'Server communication error.';
                error.style.display = 'block';
                btn.disabled = false;
                btn.innerText = 'Launch Studio';
            }
        };
    </script>
</body>

</html>