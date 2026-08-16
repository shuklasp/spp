<?php
error_log("INDEX.PHP LOADED");
/**
 * SPP Dev SPA Entry Point
 * 
 * This file serves the main Single Page Application for framework management.
 * Access is strictly restricted to development environments.
 * 
 * Route: /sppdev/ -> spp/admin/index.php
 */

if (!defined('SPP_BASE_DIR')) {
    define('SPP_BASE_DIR', dirname(__DIR__));
}

require_once dirname(SPP_BASE_DIR) . '/vendor/autoload.php';
require_once SPP_BASE_DIR . '/sppinit.php';

// Force Admin Context for Session consistency
try {
    \SPP\Scheduler::getProcObj('sppdev');
} catch (\Exception $e) {
    new \SPP\App('sppdev');
}
\SPP\Scheduler::setContext('sppdev');


/**
 * checkDevMode function
 * Redirects or blocks access if the system profile is not 'dev'.
 */
function checkDevMode()
{
    $settingsPath = SPP_BASE_DIR . '/etc/settings.xml';
    if (!file_exists($settingsPath))
        return false;
    $xml = simplexml_load_file($settingsPath);
    return strtolower((string) $xml->profile) === 'dev';
}

if (!checkDevMode()) {
    http_response_code(403);
    die("Access Forbidden: SPP Devistration Workbench is disabled in the current profile.");
}

$isLoggedIn = false;
try {
    if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
        $isLoggedIn = \SPPMod\SPPAuth\SPPAuth::check();
    }
} catch (\Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPP Dev | Control Center</title>
    <meta name="description"
        content="SPP Framework Administration Portal — Manage modules, entities, forms and groups.">
    <meta name="robots" content="noindex, nofollow">
    <!-- Modern Typography -->
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="images/spp-logo.jpg">

    <!-- SPP-UX Infrastructure (Centralized) -->
    <link rel="stylesheet" href="<?php echo \SPPMod\Drishyam\SPPUX::cssPath(); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo SPP_CSS_URI; ?>/sppforms.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/admin.css?v=<?php echo time(); ?>">

    <!-- SPP Modern Form Engine Master Loader -->
    <script src="<?php echo SPP_JS_URI; ?>/sppforms.js?v=<?php echo time(); ?>"></script>
</head>

<body data-theme="night">

    <!-- Framework Message Center (SPPError Display) -->
    <div id="toast-container"></div>

    <!-- UI Logic Screens -->

    <div id="login-layer" class="glass-overlay <?php echo !$isLoggedIn ? 'active' : ''; ?>">
        <div class="login-background"></div>
        <div class="login-card glass-panel">
            <header>
                <div class="logo-container">
                    <img src="images/spp-logo.jpg" alt="SPP Logo" class="brand-logo">
                    <div class="logo-glow"></div>
                </div>
                <h1>Workbench</h1>
                <p>Framework Control Center</p>
            </header>
            <form id="login-form">
                <div class="input-group">
                    <label for="username">Identity</label>
                    <input type="text" id="username" placeholder="Enter username..." required autocomplete="username">
                </div>
                <div class="input-group">
                    <label for="password">Secret Key</label>
                    <input type="password" id="password" placeholder="Enter password..." required
                        autocomplete="current-password">
                </div>
                <div id="mfa-section" style="display:none;">
                    <div class="input-group">
                        <label for="mfa_code">Authenticator Code</label>
                        <input type="text" id="mfa_code" placeholder="123456" autocomplete="one-time-code" maxlength="6"
                            pattern="[0-9]*">
                    </div>
                </div>
                <button type="submit" class="btn primary-btn shine-effect" style="width:100%; justify-content:center; margin-top: 1.5rem;">
                    <span>Initialize Access</span>
                </button>
            </form>


            <footer>
                <p>SPP Framework Enterprise Edition</p>
                <div class="version-tag">v2.5.0-secure</div>
            </footer>
        </div>
    </div>

    <!-- 2. Management Workspace Layer -->
    <div id="workspace-layer" class="<?php echo $isLoggedIn ? 'active' : ''; ?>">

        <!-- Navigation Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <span class="logo-text">SPP <span>Admin</span></span>
                <span class="mode-badge">Dev Mode</span>
            </div>
            <div id="app-selector-container" style="margin-bottom: 1rem;"></div>
            <div class="sidebar-search" style="padding: 0 1rem; margin-bottom: 1rem;">
                <input type="text" id="sidebar-search" class="spp-element" placeholder="Search workbench..."
                    style="width: 100%; font-size: 0.8rem; padding: 8px 10px; border-radius: 6px; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: var(--text-bright);">
            </div>
            <nav id="sidebar-nav">
                <ul>
                    <li><a href="#dashboard" class="nav-item active" data-view="dashboard"
                            data-keywords="welcome dashboard home" title="Welcome to Developer Heaven">
                            <span class="icon">👋</span> Welcome Dashboard
                        </a></li>




                    <div class="sidebar-divider"
                        style="height: 1px; background: var(--glass-border); margin: 1rem 0; opacity: 0.5;"></div>

                    <!-- 2. ACCESS & SECURITY -->
                    <div class="sidebar-section-title"
                        style="font-size: 0.65rem; color: #f59e0b; text-transform: uppercase; padding: 0 1rem; margin-bottom: 0.5rem; letter-spacing: 0.1em;">
                        Security & Access</div>
                    <li><a href="#identity" class="nav-item" data-view="identity"
                            data-keywords="identity access users roles permissions groups security login auth"
                            title="Manage users, roles, permissions and groups">
                            <span class="icon">🛡️</span> Identity & Access
                        </a></li>
                    <li><a href="#api_keys" class="nav-item" data-view="api_keys"
                            data-keywords="api keys tokens authentication rest oauth"
                            title="Manage API Keys for external services">
                            <span class="icon">🔑</span> API Keys
                        </a></li>

                    <div class="sidebar-divider"
                        style="height: 1px; background: var(--glass-border); margin: 1rem 0; opacity: 0.5;"></div>



                    <div class="sidebar-divider"
                        style="height: 1px; background: var(--glass-border); margin: 1rem 0; opacity: 0.5;"></div>

                    <!-- 4. ADVANCED ENGINES -->
                    <div class="sidebar-section-title"
                        style="font-size: 0.65rem; color: #10b981; text-transform: uppercase; padding: 0 1rem; margin-bottom: 0.5rem; letter-spacing: 0.1em;">
                        Advanced Engines</div>
                    <li><a href="#reports" class="nav-item" data-view="reports"
                            data-keywords="report builder bi business intelligence analytics charts graphs"
                            title="Business Intelligence & Dynamic Reports">
                            <span class="icon">📊</span> Report Builder
                        </a></li>

                    <div class="sidebar-divider"
                        style="height: 1px; background: var(--glass-border); margin: 1rem 0; opacity: 0.5;"></div>

                    <!-- 5. DIAGNOSTICS -->
                    <div class="sidebar-section-title"
                        style="font-size: 0.65rem; color: #8b5cf6; text-transform: uppercase; padding: 0 1rem; margin-bottom: 0.5rem; letter-spacing: 0.1em;">
                        Diagnostics</div>
                    <li><a href="#system" class="nav-item" data-view="system"
                            data-keywords="diagnostics system server phpinfo traces logs errors"
                            title="View Server environment, PHP info, and Framework Traces">
                            <span class="icon">🖥️</span> Diagnostics
                        </a></li>
                </ul>
                <div id="app-specific-menu-container"></div>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar" id="user-avatar">A</div>
                    <div>
                        <div class="user-name" id="user-display-name">Admin</div>
                        <div class="user-role" id="user-display-role">Administrator</div>
                    </div>
                </div>

                <div class="theme-switcher">
                    <button class="theme-btn" onclick="admin.setTheme('day')" title="Day Mode">☀️</button>
                    <button class="theme-btn" onclick="admin.setTheme('night')" title="Night Mode">🌙</button>
                    <button class="theme-btn" onclick="admin.setTheme('saffron')" title="Saffron Mode">🌅</button>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <header class="content-header">
                <h2 id="view-title"><span class="view-icon">⏳</span> Loading Workspace...</h2>
                <div class="content-header-tools">
                    <div class="header-actions" id="header-actions">
                        <!-- Contextual buttons injected here -->
                    </div>
                    <div class="global-actions">
                        <button id="logout-btn" class="btn ghost-btn btn-sm logout-btn-top">
                            <span style="font-size: 1.1rem; margin-right: 0.4rem;">🚪</span> Logout
                        </button>
                    </div>
                </div>
            </header>

            <section class="content-body">
                <!-- Data injection point -->
                <div id="view-container"></div>
            </section>
        </main>

    </div>

    <!-- 3. Global Modal System (Glassmorphism) -->
    <div id="modal-container" class="glass-overlay">
        <div class="glass-panel modal-box">
            <h3 id="modal-title">Editor</h3>
            <div id="modal-body"></div>
            <div id="modal-footer" class="modal-footer">
                <button class="btn secondary-btn" id="modal-close">Cancel</button>
                <button class="btn primary-btn" id="modal-save">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Global Portal for dynamic overlays (avoids modal stacking context issues) -->
    <div id="global-suggestions" class="suggestions-list"></div>

    <!-- Framework Infrastructure (Centralized) -->
    <script type="module" src="<?php echo \SPPMod\Drishyam\SPPUX::runtimePath(); ?>?v=<?php echo time(); ?>"></script>
    <script type="module" src="<?php echo \SPPMod\Drishyam\SPPUX::uiPath(); ?>?v=<?php echo time(); ?>"></script>
    <script type="module" src="js/admin.js?v=<?php echo time(); ?>"></script>
    <!-- Schema-based module settings enhancement (overrides openModuleSettings only) -->
    <script type="module" src="js/admin-settings.js?v=<?php echo time(); ?>"></script>

    <!-- CSRF Token for Frontend -->
    <script>
        window.SPP_CSRF_TOKEN = '<?php echo \SPP\SPPSession::getCsrfToken(); ?>';
    </script>

    <script>
        setTimeout(() => {
            if (window.admin) {
                console.log("SPP Dev Evolved Framework Loaded.");
            }
        }, 500); 
    </script>
</body>

</html>
