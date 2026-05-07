<?php
error_log("INDEX.PHP LOADED");
/**
 * SPP Admin SPA Entry Point
 * 
 * This file serves the main Single Page Application for framework management.
 * Access is strictly restricted to development environments.
 * 
 * Route: /sppadmin/ -> spp/admin/index.php
 */

if (!defined('SPP_BASE_DIR')) {
    define('SPP_BASE_DIR', dirname(__DIR__));
}

require_once dirname(SPP_BASE_DIR) . '/vendor/autoload.php';
require_once SPP_BASE_DIR . '/sppinit.php';
require_once dirname(SPP_BASE_DIR) . '/global.php';

// Force Admin Context for Session consistency
try { \SPP\Scheduler::getProcObj('sppadmin'); } catch (\Exception $e) { new \SPP\App('sppadmin', false, 3); }
\SPP\Scheduler::setContext('sppadmin');


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
    die("Access Forbidden: SPP Administration Workbench is disabled in the current profile.");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPP Admin | Developer Workbench</title>
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
    <link rel="stylesheet" href="<?php echo \SPPMod\SPPUX\SPPUX::cssPath(); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../res/css/sppforms.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/mobile.css?v=<?php echo time(); ?>">
    
    <!-- SPP Modern Form Engine Master Loader -->
    <script src="../res/js/sppforms.js?v=<?php echo time(); ?>"></script>
</head>

<body data-theme="night">

    <!-- Framework Message Center (SPPError Display) -->
    <div id="toast-container"></div>

    <!-- UI Logic Screens -->

    <div id="login-layer" class="active glass-overlay">
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
                    <input type="password" id="password" placeholder="Enter password..." required autocomplete="current-password">
                </div>
                <button type="submit" class="btn primary-btn shine-effect" style="width:100%; justify-content:center;">
                    <span>Initialize Access</span>
                </button>
            </form>
            <footer>
                <p>SPP Framework Enterprise Edition</p>
                <div class="version-tag">v2.4.0-evolve</div>
            </footer>
        </div>
    </div>

    <!-- 2. Management Workspace Layer -->
    <div id="workspace-layer">

        <!-- Navigation Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <span class="logo-text">SPP <span>Admin</span></span>
                <span class="mode-badge">Dev Mode</span>
            </div>
            <div id="app-selector-container" style="margin-bottom: 2rem;"></div>
            <nav>
                <ul>
                    <li><a href="#system" class="nav-item active" data-view="system">
                            <span class="icon">🖥️</span> System Info
                        </a></li>
                    <li><a href="#apps" class="nav-item" data-view="apps">
                            <span class="icon">📱</span> Applications
                        </a></li>
                    <li><a href="#modules" class="nav-item" data-view="modules">
                            <span class="icon">📦</span> Modules
                        </a></li>
                    <li><a href="#entities" class="nav-item" data-view="entities">
                            <span class="icon">🏗️</span> Entities
                        </a></li>
                    <li><a href="#forms" class="nav-item" data-view="forms">
                            <span class="icon">📝</span> Forms
                        </a></li>
                    <li><a href="#groups" class="nav-item" data-view="groups">
                            <span class="icon">👥</span> Groups
                        </a></li>
                    <li><a href="#access" class="nav-item" data-view="access">
                            <span class="icon">🛡️</span> Access Control
                        </a></li>
                    <li><a href="#routing" class="nav-item" data-view="routing">
                            <span class="icon">🔗</span> Routing
                        </a></li>
                    <li><a href="#mobile" class="nav-item" data-view="mobile">
                            <span class="icon">📱</span> Mobile Studio
                        </a></li>
                    <li><a href="#xdb" class="nav-item" data-view="xdb">
                            <span class="icon">🗄️</span> XML Database
                        </a></li>
                    <li><a href="#interdb" class="nav-item" data-view="interdb">
                            <span class="icon">🕸️</span> InterDB Mesh
                        </a></li>
                    
                    <div class="sidebar-divider" style="height: 1px; background: var(--glass-border); margin: 1rem 0; opacity: 0.5;"></div>
                    <div class="sidebar-section-title" style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; padding: 0 1rem; margin-bottom: 0.5rem; letter-spacing: 0.1em;">Diagnostics</div>

                    <li><a href="#config" class="nav-item" data-view="config">
                            <span class="icon">⚙️</span> Config
                        </a></li>
                    <li><a href="#trace" class="nav-item" data-view="trace">
                            <span class="icon">🛰️</span> Trace
                        </a></li>
                    <li><a href="#services" class="nav-item" data-view="services">
                            <span class="icon">🔌</span> DI Services
                        </a></li>
                    <li><a href="#ajax" class="nav-item" data-view="ajax">
                            <span class="icon">⚡</span> LiveServices
                        </a></li>
                    <li><a href="#lifecycle" class="nav-item" data-view="lifecycle">
                            <span class="icon">🚀</span> Deployment
                        </a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar" id="user-avatar">A</div>
                    <div>
                        <div class="user-name" id="user-display-name">Admin</div>
                        <div class="user-role" id="user-display-role">Developer</div>
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
                <h2 id="view-title"><span class="view-icon">📦</span> System Modules</h2>
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
    <script src="<?php echo \SPPMod\SPPUX\SPPUX::runtimePath(); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo \SPPMod\SPPUX\SPPUX::uiPath(); ?>?v=<?php echo time(); ?>"></script>
    <script src="js/admin.js?v=<?php echo time(); ?>"></script>
    <!-- Schema-based module settings enhancement (overrides openModuleSettings only) -->
    <script src="js/admin-settings.js?v=<?php echo time(); ?>"></script>

    <!-- CSRF Token for Frontend -->
    <script>
        window.SPP_CSRF_TOKEN = '<?php echo \SPP\SPPSession::getCsrfToken(); ?>';
    </script>

    <script>
        setTimeout(() => {
            if (window.admin) {
                console.log("SPP Admin Evolved Framework Loaded.");
            }
        }, 500); 
    </script>
</body>

</html>