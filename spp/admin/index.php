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
define('SPP_NO_AUTO_BOOT', true);
require_once SPP_BASE_DIR . '/sppinit.php';

// Force Admin Context for Session consistency
try {
    \SPP\Scheduler::getProcObj('sppadmin');
} catch (\Exception $e) {
    new \SPP\App('sppadmin');
}
\SPP\Scheduler::setContext('sppadmin');

// Ensure session is started with secure cookie parameters
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Phase 14: Sovereign Seamless Multi-Portal SSO Handover
if (!empty($_GET['sso_token'])) {
    $ssoService = __DIR__ . '/services/SsoAuthService.php';
    if (file_exists($ssoService)) {
        require_once $ssoService;
        $ssoRes = \SPP\Admin\Services\SsoAuthService::validateSsoToken($_GET['sso_token']);
        if (!empty($ssoRes['success'])) {
            $cleanUri = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: 'index.php';
            header('Location: ' . $cleanUri . '#dashboard');
            exit;
        }
    }
}


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

$isLoggedIn = false;
$currentUser = null;
try {
    if (checkDevMode()) {
        $isLoggedIn = true;
        if (empty($_SESSION['spp_admin_user'])) {
            $_SESSION['spp_admin_user'] = 'admin';
            $_SESSION['spp_admin_fallback'] = true;
        }
        $currentUser = ['username' => $_SESSION['spp_admin_user'], 'role' => 'Super Administrator'];
    } elseif (!empty($_SESSION['spp_admin_fallback'])) {
        $isLoggedIn = true;
        $currentUser = ['username' => 'admin', 'role' => 'Super Administrator'];
    } elseif (!empty($_SESSION['spp_admin_user'])) {
        $isLoggedIn = true;
        $currentUser = ['username' => $_SESSION['spp_admin_user'], 'role' => 'Administrator'];
    } elseif (\SPP\SPPSession::sessionExists() && \SPP\SPPSession::sessionVarExists('__sppauth_user__')) {
        $username = \SPP\SPPSession::getSessionVar('__sppauth_user__');
        $isLoggedIn = true;
        $currentUser = ['username' => $username, 'role' => 'Administrator'];
    } elseif (class_exists('\\SPPMod\\SPPAuth\\SPPAuth') && \SPPMod\SPPAuth\SPPAuth::check()) {
        $isLoggedIn = true;
        $user = \SPPMod\SPPAuth\SPPAuth::user();
        $username = $user ? ($user->username ?? (is_object($user) && method_exists($user, 'get') ? $user->get('UserName') : null) ?? 'Administrator') : 'Administrator';
        $currentUser = ['username' => $username, 'role' => 'Administrator'];
    }
} catch (\Throwable $e) {
}

$servingDir = str_replace('\\', '/', realpath(SPP_APP_DIR) ?: SPP_APP_DIR);
$sppBaseDir = str_replace('\\', '/', realpath(SPP_BASE_DIR) ?: SPP_BASE_DIR);
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ($_SERVER['DOCUMENT_ROOT'] ?? ''));
$instanceName = basename($servingDir);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPP Admin | Control Center</title>
    <meta name="description"
        content="SPP Framework Administration Portal — Manage modules, entities, forms and groups.">
    <meta name="robots" content="noindex, nofollow">
    <!-- Modern Typography -->
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="images/spp-logo.jpg">

    <!-- CSRF Token & Auth State for Frontend -->
    <script>
        window.SPP_CSRF_TOKEN = '<?php echo \SPP\SPPSession::getCsrfToken(); ?>';
        window.SPP_AUTH_STATE = <?php echo json_encode([
            'authenticated' => (bool)$isLoggedIn,
            'user' => $currentUser,
        ]); ?>;
        window.SPP_SYSTEM_ENV = <?php echo json_encode([
            'serving_dir' => $servingDir,
            'instance_name' => $instanceName,
            'spp_base_dir' => $sppBaseDir,
            'doc_root' => $docRoot,
            'server_name' => $_SERVER['SERVER_NAME'] ?? '',
            'http_host' => $_SERVER['HTTP_HOST'] ?? '',
        ]); ?>;
        // Pre-activate matching navigation item immediately to eliminate visual route lag
        (function() {
            const hash = (location.hash || '').replace('#', '').split('?')[0].trim() || 'dashboard';
            document.addEventListener('DOMContentLoaded', () => {
                const nav = document.querySelector(`.nav-item[data-view="${hash}"]`);
                if (nav) {
                    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
                    nav.classList.add('active');
                }
            });
        })();
    </script>

    <!-- SPP-UX Infrastructure (Centralized) -->
    <link rel="stylesheet" href="<?php echo \SPPMod\Drishyam\SPPUX::cssPath(); ?>?v=<?php echo defined('SPP_VER') ? SPP_VER . '.' . @filemtime(SPP_BASE_DIR . '/modules/spp/drishyam/js/sppux-ui.css') : time(); ?>">
    <link rel="stylesheet" href="<?php echo SPP_CSS_URI; ?>/sppforms.css?v=<?php echo defined('SPP_VER') ? SPP_VER . '.' . @filemtime(SPP_BASE_DIR . '/public/css/sppforms.css') : time(); ?>">
    <link rel="stylesheet" href="css/admin.css?v=<?php echo defined('SPP_VER') ? SPP_VER . '.' . @filemtime(__DIR__ . '/css/admin.css') : time(); ?>">
    <link rel="stylesheet" href="css/spp-suite.css?v=<?php echo defined('SPP_VER') ? SPP_VER . '.' . @filemtime(__DIR__ . '/css/spp-suite.css') : time(); ?>">
    <link rel="stylesheet" href="css/sppcoder.css?v=<?php echo defined('SPP_VER') ? SPP_VER . '.' . @filemtime(__DIR__ . '/css/sppcoder.css') : time(); ?>">
    <link rel="stylesheet" href="css/drishyam-studio.css?v=<?php echo defined('SPP_VER') ? SPP_VER . '.' . @filemtime(__DIR__ . '/css/drishyam-studio.css') : time(); ?>">
    <link rel="stylesheet" href="css/spp-command-palette.css?v=<?php echo defined('SPP_VER') ? SPP_VER . '.' . @filemtime(__DIR__ . '/css/spp-command-palette.css') : time(); ?>">

    <!-- SPP Modern Form Engine Master Loader -->
    <script src="<?php echo SPP_JS_URI; ?>/sppforms.js?v=<?php echo defined('SPP_VER') ? SPP_VER . '.' . @filemtime(SPP_BASE_DIR . '/public/js/sppforms.js') : time(); ?>"></script>
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
                <p>Satya Portal Platform</p>
            </footer>
        </div>
    </div>

    <!-- 2. Management Workspace Layer -->
    <div id="workspace-layer" class="<?php echo $isLoggedIn ? 'active' : ''; ?>">

        <!-- Navigation Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <span class="logo-text">SPP <span>Admin</span></span>
                <div class="ui-mode-switcher" id="ui-mode-switcher" title="Toggle between Normal (Site) and Expert (Server) mode">
                    <button type="button" class="ui-mode-pill active-mode" id="btn-mode-normal" onclick="admin.setUIMode('normal')" title="Normal Mode: Site & App Management">
                        <span>🌐</span> Normal
                    </button>
                    <button type="button" class="ui-mode-pill" id="btn-mode-expert" onclick="admin.setUIMode('expert')" title="Expert Mode: Full Sovereign Server Operations">
                        <span>⚡</span> Expert
                    </button>
                </div>
            </div>
            <!-- Sovereign Instance Serving Directory Identifier -->
            <div class="sidebar-instance-card" title="Serving Directory: <?php echo htmlspecialchars($servingDir); ?>&#10;Identifies this active SPP copy on the server. Click to copy path.">
                <div class="instance-card-header">
                    <span class="instance-label"><span class="instance-icon">📁</span> Serving Instance</span>
                    <button type="button" class="instance-copy-btn" onclick="window.admin && window.admin.copyServingDir()" title="Copy full directory path">📋 Copy</button>
                </div>
                <div class="instance-path-box" onclick="window.admin && window.admin.copyServingDir()">
                    <span class="instance-path-text" id="sidebar-serving-dir-text"><?php echo htmlspecialchars($servingDir); ?></span>
                </div>
            </div>
            <div id="app-selector-container" style="margin-bottom: 0.5rem;"></div>
            <div class="sidebar-search" style="padding: 0; margin-bottom: 0.5rem;">
                <input type="text" id="sidebar-search" class="spp-element" placeholder="Quick find (domain, database, port, docker)... (Ctrl+K)"
                    style="width: 100%; font-size: 0.8rem; padding: 6px 10px; border-radius: 6px;">
            </div>
            <nav id="sidebar-nav">
                <ul>
                    <!-- 1. HOSTING & DOMAINS -->
                    <div class="sidebar-section-title">Hosting & Domains</div>
                    <li><a href="#dashboard" class="nav-item" data-view="dashboard"
                            data-keywords="welcome dashboard home metrics overview operational health cloud hosting quick start beginner guide" title="Operational Overview & Health">
                            <span class="icon">👋</span> Operations Dashboard
                        </a></li>
                    <li><a href="#domains" class="nav-item" data-view="domains"
                            data-keywords="domains website site web server webserver nginx apache hybrid fastcgi cache virtual hosts vhosts ssl tls aliases redirect https canonical routing multi-tenant proxy microservices dns"
                            title="Websites, Virtual Hosts, Nginx/Apache Engines & SSL">
                            <span class="icon">🌐</span> Virtual Hosts & Domains
                        </a></li>
                    <li data-mode="expert"><a href="#fleet" class="nav-item" data-view="fleet" data-mode="expert"
                            data-keywords="fleet cluster nodes bind dns replication multi-server orchestrator broadcast nameservers"
                            title="Multi-Server Fleet Orchestrator & BIND DNS Cluster">
                            <span class="icon">🌐</span> Fleet & DNS Cluster
                        </a></li>
                    <li data-mode="expert"><a href="#terminal" class="nav-item" data-view="terminal" data-mode="expert"
                            data-keywords="terminal web terminal shell console bash cli spp artisan runner ssh execute diagnostics tests"
                            title="Interactive Cloud Web Terminal & CLI Shell">
                            <span class="icon">⬛</span> Web Terminal
                        </a></li>

                    <!-- 2. FILES & STORAGE -->
                    <div class="sidebar-section-title">Files & Storage</div>
                    <li><a href="#files" class="nav-item" data-view="files"
                            data-keywords="files file manager upload code zip unpack extract download storage explorer monaco editor disk quota directory treemap error pages mime indexing logs"
                            title="File Manager, Code Editor, Uploads & Storage">
                            <span class="icon">📁</span> File Manager & Storage
                        </a></li>
                    <li><a href="#deploy" class="nav-item" data-view="deploy"
                            data-keywords="deploy releases git vcs branches mutex rollback backups restore snapshots archive zip"
                            title="Git Releases, Deployments & Snapshot Backups">
                            <span class="icon">🚀</span> Deployments & Backups
                        </a></li>

                    <!-- 3. COMMUNICATIONS -->
                    <div class="sidebar-section-title">Communications</div>
                    <li><a href="#mail" class="nav-item" data-view="mail"
                            data-keywords="mail email smtp dkim spf dmarc gateway deliverability queue spool relay mx evaarta server"
                            title="e-Vaarta Sovereign Mail Server, Virtual Mailboxes, Quotas & RFC 6376 DKIM">
                            <span class="icon">📬</span> e-Vaarta Mail Server
                        </a></li>

                    <!-- 4. DATABASES -->
                    <div class="sidebar-section-title">Databases</div>
                    <li><a href="#database" class="nav-item" data-view="database"
                            data-keywords="database tables mysql postgres sqlite redis cache memory resp socket sql editor console phpmyadmin xdb remote host access nosql migrations seeders export dump import"
                            title="Databases (MySQL, Postgres, SQLite), Users & Redis Cache">
                            <span class="icon">🗄️</span> Database Studio
                        </a></li>

                    <!-- 5. SECURITY & ACCESS -->
                    <div class="sidebar-section-title" data-mode="expert">Security & Access</div>
                    <li data-mode="expert"><a href="#identity" class="nav-item" data-view="identity" data-mode="expert"
                            data-keywords="identity access users roles permissions groups security login auth iam api keys tokens mfa totp webauthn passkeys fido2"
                            title="IAM Users, Roles, Groups, and FIDO2 Passkeys">
                            <span class="icon">🛡️</span> Identity & Access (IAM)
                        </a></li>
                    <li data-mode="expert"><a href="#shield" class="nav-item" data-view="shield" data-mode="expert"
                            data-keywords="shield security firewall ports open port ufw netsh fail2ban unban jail ssh hardening ip blocker ban rate limit threat waf ddos protection"
                            title="Firewall Ports, Fail2ban Jails, SSH Hardening & WAF">
                            <span class="icon">🛡️</span> Security & Firewall
                        </a></li>

                    <!-- 6. SERVER & PERFORMANCE -->
                    <div class="sidebar-section-title" data-mode="expert">Server & Performance</div>
                    <li data-mode="expert"><a href="#server" class="nav-item" data-view="server" data-mode="expert"
                            data-keywords="server docker containers swap memory packages updates watchdog alerts self healing hardware workstation load cpu ram disk gauges process manager"
                            title="Server Workstation, Docker Containers, Swap & Watchdog">
                            <span class="icon">🖥️</span> Server Workstation
                        </a></li>
                    <li data-mode="expert"><a href="#runtime" class="nav-item" data-view="runtime" data-mode="expert"
                            data-keywords="runtime php version extensions opcache php.ini memory limit tuning multiphp"
                            title="PHP Runtimes, Extensions, OPcache & php.ini Configuration">
                            <span class="icon">⚙️</span> PHP Runtimes & OPcache
                        </a></li>
                    <li data-mode="expert"><a href="#cron" class="nav-item" data-view="cron" data-mode="expert"
                            data-keywords="cron scheduler jobs crontab background queue dag worker task orchestrator periodic"
                            title="Cron Schedules, Background Queue & DAG Orchestration">
                            <span class="icon">⏱️</span> Scheduled Tasks & Queue
                        </a></li>
                    <li data-mode="expert"><a href="#metrics" class="nav-item" data-view="metrics" data-mode="expert"
                            data-keywords="metrics analytics bandwidth traffic visitors requests speed latency charts"
                            title="Real-Time Metrics, Bandwidth & Traffic Analytics">
                            <span class="icon">📊</span> Metrics & Analytics
                        </a></li>
                    <li data-mode="expert"><a href="#system" class="nav-item" data-view="system" data-mode="expert"
                            data-keywords="diagnostics system server phpinfo traces logs health errors cache telemetry"
                            title="Server Environment, Diagnostics & Live Logs">
                            <span class="icon">🖥️</span> Observability & Logs
                        </a></li>

                    <!-- 7. APPLICATIONS & TOOLS -->
                    <div class="sidebar-section-title">Applications & Tools</div>
                    <li><a href="#apps" class="nav-item" data-view="apps"
                            data-keywords="apps applications modules multi-app tenant switch marketplace lifecycle packages"
                            title="Multi-App Management & Modules">
                            <span class="icon">📱</span> Applications & Modules
                        </a></li>
                    <li data-mode="expert"><a href="#reseller" class="nav-item" data-view="reseller" data-mode="expert"
                            data-keywords="reseller multi-tenancy accounts clients packages quotas billing hosting"
                            title="Reseller Management & Multi-Tenant Hosting">
                            <span class="icon">👥</span> Resellers & Multi-Tenancy
                        </a></li>
                    <li><a href="#apptoolkit" class="nav-item" data-view="apptoolkit"
                            data-keywords="marketplace apptoolkit 1-click install wordpress cms templates softaculous"
                            title="CMS & App Marketplace (1-Click Installer)">
                            <span class="icon">📦</span> App Marketplace
                        </a></li>
                </ul>
                <div class="sidebar-mode-footer" data-mode="normal-only">
                    <span class="mode-footer-text">Looking for Docker, Firewall or Terminal?</span>
                    <button type="button" class="mode-switch-link" onclick="admin.setUIMode('expert')">
                        ⚡ Switch to Expert Mode
                    </button>
                </div>
                <div class="sidebar-mode-footer" data-mode="expert">
                    <span class="mode-footer-text">Full Server Operations Active</span>
                    <button type="button" class="mode-switch-link" onclick="admin.setUIMode('normal')">
                        🌐 Switch to Normal Mode
                    </button>
                </div>
                <div id="app-specific-menu-container"></div>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar" id="user-avatar">A</div>
                    <div>
                        <div class="user-name" id="user-display-name">Admin</div>
                        <div class="user-role" id="user-display-role">Administrator</div>
                        <div class="sidebar-footer-instance" title="Serving Directory: <?php echo htmlspecialchars($servingDir); ?> (Click to copy)" onclick="window.admin && window.admin.copyServingDir()">
                            <span class="footer-instance-icon">📍</span> <span class="footer-instance-text"><?php echo htmlspecialchars(basename($servingDir)); ?></span>
                        </div>
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
                <div class="content-header-title-block">
                    <button type="button" id="global-sidebar-toggle-btn" class="sidebar-toggle-btn"
                        onclick="window.admin && window.admin.toggleSidebar()" title="Toggle Sidebar (Expand/Collapse)">
                        <span id="global-sidebar-icon">☰</span>
                    </button>
                    <h2 id="view-title"><span class="view-icon">⏳</span> Loading Workspace...</h2>
                    <div class="header-serving-chip" onclick="window.admin && window.admin.copyServingDir()" title="Active SPP Serving Directory: <?php echo htmlspecialchars($servingDir); ?>&#10;Differentiates multiple copies of SPP. Click to copy path.">
                        <span class="chip-icon">📁</span>
                        <span class="chip-label"><?php echo htmlspecialchars($servingDir); ?></span>
                        <span class="chip-copy" title="Copy to clipboard">📋</span>
                    </div>
                </div>
                <div class="adm-header-search-wrap">
                    <div class="adm-header-search-box" onclick="window.SPPCommandPalette && window.SPPCommandPalette.open()" title="Search workbench, views, commands... (Ctrl+K)">
                        <span class="adm-search-icon">🔍</span>
                        <input type="text" class="adm-header-search-input" placeholder="Search..." readonly onclick="window.SPPCommandPalette && window.SPPCommandPalette.open()" onfocus="this.blur(); window.SPPCommandPalette && window.SPPCommandPalette.open();">
                        <kbd class="adm-search-kbd">Ctrl+K</kbd>
                    </div>
                    <button type="button" class="adm-header-search-box adm-cheatsheet-btn" onclick="window.SPPCommandPalette && window.SPPCommandPalette.openCheatsheet()" title="Workbench Keyboard Accelerators Cheatsheet (Ctrl+/)">
                        <span style="font-size: 0.95rem;">⌨️</span>
                        <kbd class="adm-search-kbd" style="font-size: 0.65rem;">Ctrl+/</kbd>
                    </button>
                    <button type="button" class="adm-header-search-box adm-cheatsheet-btn" onclick="window.admin && window.admin.openJargonBuster()" title="Jargon Buster &amp; Glossary (Ctrl+H) — Explain abbreviations and options" style="display:inline-flex; align-items:center; gap:0.35rem; cursor:pointer;">
                        <span style="font-size: 0.95rem;">❓</span>
                        <span class="global-action-text" style="font-size:0.75rem; font-weight:600; color:var(--text-bright);">Jargon Buster</span>
                        <kbd class="adm-search-kbd" style="font-size: 0.65rem;">Ctrl+H</kbd>
                    </button>
                </div>
                <div class="content-header-tools">
                    <div class="header-actions" id="header-actions">
                        <!-- Contextual buttons injected here -->
                    </div>
                    <div class="global-actions">
                        <button type="button" id="header-ui-mode-pill" class="btn btn-sm primary-btn header-mode-btn"
                            onclick="admin.setUIMode(admin.getUIMode() === 'normal' ? 'expert' : 'normal')"
                            title="Switch between Normal (Site) and Expert (Server) mode">
                            <span>🌐</span> Normal Mode
                        </button>
                        <a href="../sppdev/#dashboard" onclick="event.preventDefault(); typeof window.switchPortalWithSso === 'function' ? window.switchPortalWithSso('sppdev') : (window.location.href='../sppdev/#dashboard');" class="btn ghost-btn btn-sm" title="Switch to Developer Intelligence Workbench (Seamless SSO)" style="display:inline-flex; align-items:center; gap:0.4rem; text-decoration:none; margin-right:0.5rem;">
                            <span style="font-size:1rem;">💻</span> <span class="global-action-text">Dev Studio</span>
                        </a>
                        <a href="#terminal" class="btn secondary-btn btn-sm" data-mode="expert" title="Interactive Cloud Web Terminal" style="text-decoration: none;">
                            <span>⬛</span> <span class="global-action-text">Terminal</span>
                        </a>
                        <a href="#domains" class="btn secondary-btn btn-sm" title="Virtual Hosts & Microservice Proxy" style="text-decoration: none;">
                            <span>🌐</span> <span class="global-action-text">Domains</span>
                        </a>
                        <a href="#files" class="btn secondary-btn btn-sm" title="Storage Treemap, Quotas & Web Tools" style="text-decoration: none;">
                            <span>📊</span> <span class="global-action-text">Storage</span>
                        </a>
                        <button id="logout-btn" class="btn ghost-btn btn-sm logout-btn-top">
                            <span style="font-size: 1.1rem; margin-right: 0.4rem;">🚪</span> <span class="global-action-text">Logout</span>
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
    <div id="sppcoder-modal-portal"></div>

    <!-- Framework Infrastructure (Centralized) -->
    <script type="module" src="<?php echo \SPPMod\Drishyam\SPPUX::runtimePath(); ?>?v=<?php echo defined('SPP_VER') ? SPP_VER . '.' . @filemtime(SPP_BASE_DIR . '/modules/spp/drishyam/js/sppux.js') : time(); ?>"></script>
    <script type="module" src="<?php echo \SPPMod\Drishyam\SPPUX::uiPath(); ?>?v=<?php echo defined('SPP_VER') ? SPP_VER . '.' . @filemtime(SPP_BASE_DIR . '/modules/spp/drishyam/js/sppux-ui.js') : time(); ?>"></script>
    <script type="module" src="js/sppcoder.js?v=<?php echo defined('SPP_VER') ? SPP_VER . '.' . @filemtime(__DIR__ . '/js/sppcoder.js') : time(); ?>"></script>
    <script src="js/spp-command-palette.js?v=<?php echo defined('SPP_VER') ? SPP_VER . '.' . @filemtime(__DIR__ . '/js/spp-command-palette.js') : time(); ?>"></script>
    <script type="module" src="js/admin.js?v=<?php echo defined('SPP_VER') ? SPP_VER . '.' . @filemtime(__DIR__ . '/js/admin.js') : time(); ?>"></script>
    <!-- Schema-based module settings enhancement (overrides openModuleSettings only) -->
    <script type="module" src="js/admin-settings.js?v=<?php echo defined('SPP_VER') ? SPP_VER . '.' . @filemtime(__DIR__ . '/js/admin-settings.js') : time(); ?>"></script>

    <script>
        setTimeout(() => {
            if (window.admin) {
                console.log("SPP Admin Evolved Framework Loaded.");
            }
        }, 500); 
    </script>
</body>

</html>