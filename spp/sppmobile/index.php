<?php
/**
 * SPP Mobile Studio Entry Point
 */

if (!defined('SPP_BASE_DIR')) {
    define('SPP_BASE_DIR', dirname(__DIR__, 2) . '/spp');
}
if (!defined('STUDIO_ROOT')) {
    define('STUDIO_ROOT', __DIR__);
}

require_once dirname(SPP_BASE_DIR) . '/vendor/autoload.php';
require_once SPP_BASE_DIR . '/sppinit.php';

// Force Mobile Studio Context
\SPP\Scheduler::setContext('sppmobile');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPP Mobile Studio | Visual Builder</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <?php $base = dirname($_SERVER['SCRIPT_NAME']); ?>
    <link rel="stylesheet" href="<?php echo $base; ?>/../spp/modules/spp/sppux/css/sppux.css">
    <link rel="stylesheet" href="css/mobile.css">
</head>
<body data-theme="night">
    <div id="toast-container"></div>

    <div id="workspace-layer" style="display: flex; height: 100vh;">
        <!-- Compact Mini-Sidebar -->
        <aside class="sidebar mini-sidebar" style="width: 70px; flex-shrink: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(10px); display: flex; flex-direction: column; align-items: center; padding: 20px 0; border-right: 1px solid var(--glass-border);">
            <div class="logo-circle" style="width: 44px; height: 44px; background: linear-gradient(135deg, var(--primary-color), #4f46e5); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);">M</div>
            <nav style="flex: 1;">
                <ul style="list-style: none; padding: 0; display: flex; flex-direction: column; gap: 20px;">
                    <li><a href="#" id="nav-studio" title="Studio" class="active" style="font-size: 1.5rem; text-decoration: none;">🏗️</a></li>
                    <li><a href="#" id="nav-assets" title="Assets" style="font-size: 1.5rem; text-decoration: none;">📁</a></li>
                    <li><a href="#" id="nav-code" title="Code" style="font-size: 1.5rem; text-decoration: none;">💻</a></li>
                </ul>
            </nav>
            <div class="sidebar-bottom" style="display: flex; flex-direction: column; gap: 15px; align-items: center; padding-bottom: 20px;">
                <div class="theme-switcher" style="display: flex; flex-direction: column; gap: 10px;">
                    <div class="theme-orb" onclick="setTheme('night')" title="Night Mode" style="width: 12px; height: 12px; border-radius: 50%; background: #6366f1; cursor: pointer; border: 2px solid rgba(255,255,255,0.2);"></div>
                    <div class="theme-orb" onclick="setTheme('day')" title="Day Mode" style="width: 12px; height: 12px; border-radius: 50%; background: #fff; cursor: pointer; border: 2px solid rgba(0,0,0,0.1);"></div>
                    <div class="theme-orb" onclick="setTheme('saffron')" title="Saffron Mode" style="width: 12px; height: 12px; border-radius: 50%; background: #ea580c; cursor: pointer; border: 2px solid rgba(255,255,255,0.2);"></div>
                </div>
                <a href="#" title="System Settings" style="font-size: 1.2rem; text-decoration: none; opacity: 0.3;">⚙️</a>
            </div>
            <script>
                function setTheme(theme) {
                    document.body.setAttribute('data-theme', theme);
                    localStorage.setItem('spp_mobile_theme', theme);
                }
                // Initialize theme
                const savedTheme = localStorage.getItem('spp_mobile_theme') || 'night';
                document.body.setAttribute('data-theme', savedTheme);
            </script>
        </aside>

        <!-- Main Studio Area -->
        <main class="main-content" style="flex: 1; display: flex; flex-direction: column;">
            <header class="content-header" style="background: var(--panel-bg); border-bottom: 1px solid var(--glass-border); padding: 12px 25px; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <h2 id="view-title" style="margin: 0; font-size: 1.3rem; font-weight: 800; color: var(--text-bright); letter-spacing: -0.5px;">Mobile Studio <span style="font-weight: 400; color: var(--primary-color);">Pro</span></h2>
                    <span class="badge" style="background: var(--primary-subtle); color: var(--primary-color); font-size: 0.65rem; border: 1px solid var(--primary-glow);">ENTERPRISE</span>
                </div>
                <div class="header-tools" style="display: flex; gap: 10px;">
                    <button class="btn primary-btn btn-sm shine-effect" id="save-project-btn">Save Project</button>
                </div>
            </header>

            <section id="view-container" style="flex: 1; padding: 20px; overflow: hidden;"></section>
        </main>
    </div>

    <!-- SPP-UX Infrastructure -->
    <script src="<?php echo $base; ?>/../spp/modules/spp/sppux/js/sppux.js"></script>
    <script src="<?php echo $base; ?>/../spp/modules/spp/sppux/js/sppux-ui.js"></script>
    <script>
        window.SPP_CONFIG = { apiEndpoint: 'api.php' };
        window.SPP_CSRF_TOKEN = '<?php echo \SPP\SPPSession::getCsrfToken(); ?>';
        
        // Expose SPPUX internals to ES Modules
        if (window.SPPUX) {
            window.BaseComponent = SPPUX.BaseComponent;
            window.html = SPPUX.html;
            window.TrustedHTML = SPPUX.TrustedHTML;
        }
    </script>
    <script src="js/mobile-app.js" type="module"></script>
</body>
</html>
