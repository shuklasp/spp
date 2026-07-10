<?php
/**
 * Contact Page — Demonstrates YAML-driven forms in native PHP
 * The form is defined in etc/apps/Samvaad/forms/contact.yml
 */
if (class_exists('\SPPMod\Drishyam\SPPUX')) {
    \SPPMod\Drishyam\SPPUX::boot();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us — Samvaad</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f8fafc;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #6366f1;
            --primary-light: rgba(99, 102, 241, 0.1);
            --surface: #ffffff;
            --border: #e2e8f0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; }
        .layout-container { max-width: 1200px; margin: 0 auto; padding: 0 2rem; }

        /* Navigation */
        .nav { background: var(--surface); border-bottom: 1px solid var(--border); padding: 1rem 0; }
        .nav-inner { display: flex; align-items: center; justify-content: space-between; }
        .nav-brand { font-weight: 800; font-size: 1.3rem; color: var(--primary); text-decoration: none; }
        .nav-links { display: flex; gap: 0.5rem; }
        .nav-links a { padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; color: var(--muted); font-weight: 500; font-size: 0.9rem; transition: all 0.2s; }
        .nav-links a:hover { background: var(--primary-light); color: var(--primary); }

        /* Main content */
        .main { padding: 2rem 0; }
        .card { background: var(--surface); border-radius: 16px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid var(--border); margin-bottom: 1.5rem; }
        h1 { font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; }
        h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
        h3 { font-size: 1.2rem; font-weight: 600; margin-bottom: 0.5rem; }
        p { color: var(--muted); }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }

        /* Footer */
        .footer { text-align: center; padding: 2rem 0; color: var(--muted); font-size: 0.85rem; border-top: 1px solid var(--border); margin-top: 3rem; }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="layout-container nav-inner">
            <a href="<?php echo \SPP\App::url('', 'Samvaad'); ?>" class="nav-brand">🚀 Samvaad</a>
            <div class="nav-links">
                <a href="<?php echo \SPP\App::url('home', 'Samvaad'); ?>">Home</a>
                <a href="<?php echo \SPP\App::url('about', 'Samvaad'); ?>">About</a>
                <a href="<?php echo \SPP\App::url('dashboard', 'Samvaad'); ?>">Dashboard</a>
                <a href="<?php echo \SPP\App::url('contact', 'Samvaad'); ?>">Contact</a>
                <a href="<?php echo \SPP\App::url('app', 'Samvaad'); ?>">SPP-UX App</a>

                <?php if (\SPPMod\SPPAuth\SPPAuth::authSessionExists()): ?>
                    <a href="<?php echo \SPP\App::url('auth/logout', 'Samvaad'); ?>" style="color: #ef4444;">Logout</a>
                <?php else: ?>
                    <a href="<?php echo \SPP\App::url('login', 'Samvaad'); ?>">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="main">
        <div style="max-width: 700px; margin: 2rem auto; padding: 0 1rem;">
            <div style="background: #fff; border-radius: 16px; padding: 2.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #e2e8f0;">
                <span style="display:inline-block; background:#e0e7ff; color:#4f46e5; padding:4px 12px; border-radius:20px; font-size:0.75rem; font-weight:700; margin-bottom:1rem;">YAML FORM</span>
                <h1 style="margin: 0 0 0.5rem;">Contact Us</h1>
                <p style="color: #64748b;">This form is powered by the SPP YAML form engine. Definition: <code>etc/apps/Samvaad/forms/contact.yml</code></p>

                <form method="POST" style="margin-top: 2rem;">
                    <input type="hidden" name="spp_form_id" value="contact">
                    <div style="margin-bottom: 1.2rem;">
                        <label style="display:block; font-weight:600; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.4rem; color:#64748b;">Name</label>
                        <input type="text" name="guest_name" required placeholder="Your name" style="width:100%; padding:0.8rem; border:1px solid #e2e8f0; border-radius:10px; font-family:inherit; font-size:1rem;">
                    </div>
                    <div style="margin-bottom: 1.2rem;">
                        <label style="display:block; font-weight:600; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.4rem; color:#64748b;">Email</label>
                        <input type="email" name="email" placeholder="you@example.com" style="width:100%; padding:0.8rem; border:1px solid #e2e8f0; border-radius:10px; font-family:inherit; font-size:1rem;">
                    </div>
                    <div style="margin-bottom: 1.2rem;">
                        <label style="display:block; font-weight:600; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.4rem; color:#64748b;">Message</label>
                        <textarea name="message" rows="4" placeholder="Your message..." style="width:100%; padding:0.8rem; border:1px solid #e2e8f0; border-radius:10px; font-family:inherit; font-size:1rem;"></textarea>
                    </div>
                    <button type="submit" style="padding:0.8rem 2rem; background:#6366f1; color:#fff; border:none; border-radius:10px; font-weight:600; cursor:pointer;">Send Message</button>
                </form>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="layout-container">
            &copy; <?php echo date('Y'); ?> Samvaad &bull; Built with SPP Framework
        </div>
    </footer>
</body>
</html>