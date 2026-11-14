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
define('SPP_NO_AUTO_BOOT', true);
require_once SPP_BASE_DIR . '/sppinit.php';

// Force Admin Context for Session consistency
try {
    \SPP\Scheduler::getProcObj('sppdev');
} catch (\Exception $e) {
    new \SPP\App('sppdev');
}
\SPP\Scheduler::setContext('sppdev');

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
    $ssoService = dirname(__DIR__) . '/admin/services/SsoAuthService.php';
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
 * Redirects or blocks access if the system profile is not 'dev' or if running on a live production domain.
 */
function checkDevMode(): bool
{
    // 1. Block in production environments (APP_ENV / SPP_ENV)
    $appEnv = strtolower((string)(getenv('APP_ENV') ?: (getenv('SPP_ENV') ?: '')));
    if (in_array($appEnv, ['prod', 'production', 'live'], true)) {
        return false;
    }

    // 2. Block public production domains (public is strictly forbidden from accessing dev studio on live servers)
    $host = strtolower(trim(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]));
    if ($host !== '' && !in_array($host, ['localhost', '127.0.0.1', '::1'], true) && !str_ends_with($host, '.local') && !str_ends_with($host, '.test')) {
        return false;
    }

    // 3. Check global-settings.yml profile
    if (class_exists('\\SPP\\App')) {
        $profile = \SPP\App::getGlobalSettings('profile');
        if ($profile !== null && !in_array(strtolower((string)$profile), ['dev', 'development', 'local', 'test'], true)) {
            return false;
        }
    }

    // 4. Check legacy settings.xml if present
    $settingsPath = SPP_BASE_DIR . '/etc/settings.xml';
    if (file_exists($settingsPath)) {
        $xml = @simplexml_load_file($settingsPath);
        if ($xml && strtolower((string)$xml->profile) !== 'dev') {
            return false;
        }
    }

    return true;
}

if (!checkDevMode()) {
    http_response_code(403);
    die("Access Forbidden: SPP Devistration Workbench is disabled in the current profile.");
}

$isLoggedIn = false;
$currentUser = null;
try {
    if (!empty($_SESSION['spp_admin_fallback'])) {
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
    <!-- Favicon & PWA Manifest -->
    <link rel="icon" type="image/png" href="images/spp-logo.jpg">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#161b22">

    <!-- CSRF Token & Auth State for Frontend -->
    <script>
        window.SPP_CSRF_TOKEN = '<?php echo \SPP\SPPSession::getCsrfToken(); ?>';
        window.SPP_AUTH_STATE = <?php echo json_encode([
            'authenticated' => (bool)$isLoggedIn,
            'user' => $currentUser,
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
    <link rel="stylesheet" href="<?php echo \SPPMod\Drishyam\SPPUX::cssPath(); ?>?v=<?php echo defined('SPP_VER') ? SPP_VER : '0.5'; ?>">
    <link rel="stylesheet" href="<?php echo SPP_CSS_URI; ?>/sppforms.css?v=<?php echo defined('SPP_VER') ? SPP_VER : '0.5'; ?>">
    <link rel="stylesheet" href="css/admin.css?v=<?php echo defined('SPP_VER') ? SPP_VER : '0.5'; ?>">
    <link rel="stylesheet" href="css/spp-suite.css?v=<?php echo defined('SPP_VER') ? SPP_VER : '0.5'; ?>">
    <link rel="stylesheet" href="css/sppcoder.css?v=<?php echo defined('SPP_VER') ? SPP_VER : '0.5'; ?>">
    <link rel="stylesheet" href="css/drishyam-studio.css?v=<?php echo defined('SPP_VER') ? SPP_VER : '0.5'; ?>">
    <link rel="stylesheet" href="css/spp-command-palette.css?v=<?php echo defined('SPP_VER') ? SPP_VER : '0.5'; ?>">

    <!-- SPP Modern Form Engine Master Loader -->
    <script src="<?php echo SPP_JS_URI; ?>/sppforms.js?v=<?php echo defined('SPP_VER') ? SPP_VER : '0.5'; ?>"></script>
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
                <span class="logo-text">SPP <span>Dev</span></span>
                <span class="mode-badge" style="background: linear-gradient(135deg, #0ea5e9, #6366f1); color: #fff;">Studio</span>
            </div>
            <div id="app-selector-container" style="margin-bottom: 0.5rem;"></div>
            <div class="sidebar-search" style="padding: 0; margin-bottom: 0.5rem;">
                <input type="text" id="sidebar-search" class="spp-element" placeholder="Search workbench... (Ctrl+K)"
                    style="width: 100%; font-size: 0.8rem; padding: 6px 10px; border-radius: 6px;">
            </div>
            <nav id="sidebar-nav">
                <ul>
                    <!-- 1. DEVELOPER STUDIO & CODE -->
                    <div class="sidebar-section-title"
                        style="font-size: 0.65rem; color: #38bdf8; text-transform: uppercase; padding: 0 1rem; margin-bottom: 0.5rem; letter-spacing: 0.1em;">
                        Developer Studio & Code</div>
                    <li><a href="#dashboard" class="nav-item" data-view="dashboard"
                            data-keywords="welcome dashboard home workbench studio telemetry" title="Developer Studio Hub & Workspace">
                            <span class="icon">👋</span> Studio Dashboard
                        </a></li>
                    <li><a href="#coder" class="nav-item" data-view="coder"
                            data-keywords="code editor ide files source file browser scaffold apps docs manuals linting syntax symbols outline minimap"
                            title="In-Browser IDE, App Scaffolder & Docs">
                            <span class="icon">💻</span> Code Studio & Scaffolder
                        </a></li>
                    <li><a href="#tinker" class="nav-item" data-view="tinker"
                            data-keywords="tinker repl php eval execute console interact sandbox"
                            title="Interactive PHP Shell & Expression REPL">
                            <span class="icon">💬</span> Web Tinker REPL
                        </a></li>
                    <li><a href="#terminal" class="nav-item" data-view="terminal"
                            data-keywords="terminal web terminal shell console bash cli spp artisan runner ssh execute"
                            title="Interactive Developer Web Terminal & CLI Shell">
                            <span class="icon">⬛</span> Web Terminal
                        </a></li>
                    <li><a href="#files" class="nav-item" data-view="files"
                            data-keywords="files storage explorer monaco editor code zip unpack upload download disk quota directory treemap error pages mime indexing logs"
                            title="File Manager, Storage Treemap, Quotas & Zip Extraction">
                            <span class="icon">📁</span> File Manager & Storage
                        </a></li>
                    <li><a href="#deploy" class="nav-item" data-view="deploy"
                            data-keywords="deploy deployment push bundle package upload differential sync rollback maintenance backups cloud store repository"
                            title="Deployments, Packaging & Cloud Sync">
                            <span class="icon">🚀</span> Deploy & Cloud Sync
                        </a></li>

                    <!-- 2. DECLARATIVE ARCHITECTURE & DATA -->
                    <div class="sidebar-section-title"
                        style="font-size: 0.65rem; color: #a855f7; text-transform: uppercase; padding: 0 1rem; margin-top: 1rem; margin-bottom: 0.5rem; letter-spacing: 0.1em;">
                        Declarative Architecture & Data</div>
                    <li><a href="#pages" class="nav-item" data-view="pages"
                            data-keywords="pages visual builder designer spp studio drag drop canvas layout app maker low code htmx partials transitions lifecycle"
                            title="SPP Studio — Visual App Lifecycle & HTMX Builder">
                            <span class="icon">📐</span> SPP Studio
                        </a></li>
                    <li><a href="#workflows" class="nav-item" data-view="workflows"
                            data-keywords="workflows state machine lifecycle transitions saga sla timeout status"
                            title="Visual Workflow & State Machine Designer">
                            <span class="icon">🔄</span> Workflows & States
                        </a></li>
                    <li><a href="#database" class="nav-item" data-view="database"
                            data-keywords="database tables sql editor console phpmyadmin postgres sqlite xdb nosql migrations seeders export dump import erd timemachine entities"
                            title="Unified Developer Data Plane (Tables, SQL Console, Entities, ERD, XDB & Migrations)">
                            <span class="icon">🗄️</span> Database & Schemas
                        </a></li>
                    <li><a href="#forms" class="nav-item" data-view="forms"
                            data-keywords="forms form engine designer validation fields spplang i18n mobile responsive"
                            title="Visual Form Builder, Locales & Mobile Preview">
                            <span class="icon">📝</span> Forms & Locales
                        </a></li>
                    <li><a href="#routing" class="nav-item" data-view="routing"
                            data-keywords="routing routes url middleware controllers dispatch services polyglot rpc di container"
                            title="Routing Table, Middleware & Polyglot Services">
                            <span class="icon">🛤️</span> Routing & Endpoints
                        </a></li>
                    <li><a href="#polyglot" class="nav-item" data-view="polyglot"
                            data-keywords="polyglot python node rust go java c# formula dsl languages compilers runtimes toggle execution"
                            title="Universal Polyglot Studio & Multi-Language Orchestrator">
                            <span class="icon">🌐</span> Polyglot Studio
                        </a></li>
                    <li><a href="#reports" class="nav-item" data-view="reports"
                            data-keywords="reports analytics visual designer sql olap pivot charts bi business intelligence exports csv excel"
                            title="Interactive Visual Report Studio, Pivot OLAP & BI">
                            <span class="icon">📊</span> Reports & Analytics
                        </a></li>
                    <li><a href="#manifests" class="nav-item" data-view="manifests"
                            data-keywords="manifests topological graph dependency layer governance partials playground contracts"
                            title="Declarative Manifest OS & Architectural Dependency Graph">
                            <span class="icon">🛡️</span> Manifest OS & Graph
                        </a></li>

                    <!-- 3. API & WEBHOOK ENGINEERING -->
                    <div class="sidebar-section-title"
                        style="font-size: 0.65rem; color: #f59e0b; text-transform: uppercase; padding: 0 1rem; margin-top: 1rem; margin-bottom: 0.5rem; letter-spacing: 0.1em;">
                        API & Webhook Engineering</div>
                    <li><a href="#apistudio" class="nav-item" data-view="apistudio"
                            data-keywords="api studio openapi swagger swagger-ui postman insomnia rest endpoints latency curl client test parikshak"
                            title="Interactive API Studio & OpenAPI 3.1 Workbench">
                            <span class="icon">🚀</span> API Studio & OpenAPI
                        </a></li>
                    <li><a href="#webhooks" class="nav-item" data-view="webhooks"
                            data-keywords="webhooks relay tunnel ngrok stripe github shopify payloads hmac signatures inspect replay"
                            title="Sovereign Inbound Webhook Relay & Real-Time Payload Inspector">
                            <span class="icon">⚡</span> Webhook Relay & Tunnel
                        </a></li>
                    <li><a href="#mesh" class="nav-item" data-view="mesh"
                            data-keywords="mesh integration container rpc services interprocess"
                            title="Integration Container Mesh & RPC Sandbox">
                            <span class="icon">🕸️</span> Integration Mesh
                        </a></li>

                    <!-- 4. RUNTIME, PROFILING & QUALITY -->
                    <div class="sidebar-section-title"
                        style="font-size: 0.65rem; color: #22c55e; text-transform: uppercase; padding: 0 1rem; margin-top: 1rem; margin-bottom: 0.5rem; letter-spacing: 0.1em;">
                        Runtime, Profiling & Quality</div>
                    <li><a href="#ai" class="nav-item" data-view="ai"
                            data-keywords="ai copilot prompt playground assistant generator llm sppai"
                            title="AI Copilot, Prompt Playground & SPPAI Gateway">
                            <span class="icon">🤖</span> AI Copilot & Playground
                        </a></li>
                    <li><a href="#apm" class="nav-item" data-view="apm"
                            data-keywords="apm flame graph speedscope latency p95 p99 profiler spans transactions performance bottlenecks"
                            title="Native In-Panel APM & Flame Graph Profiler">
                            <span class="icon">🔥</span> APM & Flame Graphs
                        </a></li>
                    <li><a href="#parikshak" class="nav-item" data-view="parikshak"
                            data-keywords="parikshak testing tests unit automated suite runner trace telemetry audit"
                            title="Parikshak Automated Test Studio & Assertions">
                            <span class="icon">🧪</span> Parikshak Test Studio
                        </a></li>
                    <li><a href="#cron" class="nav-item" data-view="cron"
                            data-keywords="cron scheduler jobs crontab background queue dag worker task orchestrator periodic"
                            title="Cron Schedules, Background Queue & DAG Orchestration">
                            <span class="icon">⏱️</span> Scheduled Tasks & Queue
                        </a></li>
                    <li><a href="#system" class="nav-item" data-view="system"
                            data-keywords="diagnostics system server phpinfo traces logs health errors cache queue polyglot telemetry opcache"
                            title="Server Environment, OPcache, Diagnostics & Live Logs">
                            <span class="icon">🖥️</span> Observability & Logs
                        </a></li>
                    <li><a href="#commands" class="nav-item" data-view="commands"
                            data-keywords="cli commands console terminal runner execute spp artisan cron schedule"
                            title="Interactive CLI Console & Command Engine">
                            <span class="icon">⚡</span> CLI Command Center
                        </a></li>
                    <li><a href="#docs" class="nav-item" data-view="docs"
                            data-keywords="docs manual handbook guides architecture tutorials api specs"
                            title="Live Framework Architecture & Documentation Handbook">
                            <span class="icon">📖</span> Framework Docs
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
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <button type="button" id="global-sidebar-toggle-btn" class="sidebar-toggle-btn"
                        onclick="window.admin && window.admin.toggleSidebar()" title="Toggle Sidebar (Expand/Collapse)">
                        <span id="global-sidebar-icon">☰</span>
                    </button>
                    <h2 id="view-title"><span class="view-icon">⏳</span> Loading Workspace...</h2>
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
                </div>
                <div class="content-header-tools">
                    <div class="header-actions" id="header-actions">
                        <!-- Contextual buttons injected here -->
                    </div>
                    <div class="global-actions">
                        <a href="../sppadmin/#dashboard" onclick="event.preventDefault(); typeof window.switchPortalWithSso === 'function' ? window.switchPortalWithSso('sppadmin') : (window.location.href='../sppadmin/#dashboard');" class="btn ghost-btn btn-sm" title="Switch to Hosting & Server Operations Suite (Seamless SSO)" style="display:inline-flex; align-items:center; gap:0.4rem; text-decoration:none; margin-right:0.5rem;">
                            <span style="font-size:1rem;">🖥️</span> <span class="global-action-text">Server Ops</span>
                        </a>
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
    <div id="sppcoder-modal-portal"></div>

    <!-- Framework Infrastructure (Centralized) -->
    <script type="module" src="<?php echo \SPPMod\Drishyam\SPPUX::runtimePath(); ?>?v=<?php echo defined('SPP_VER') ? SPP_VER : '0.5'; ?>"></script>
    <script type="module" src="<?php echo \SPPMod\Drishyam\SPPUX::uiPath(); ?>?v=<?php echo defined('SPP_VER') ? SPP_VER : '0.5'; ?>"></script>
    <script type="module" src="js/sppcoder.js?v=<?php echo defined('SPP_VER') ? SPP_VER : '0.5'; ?>"></script>
    <script src="js/spp-command-palette.js?v=<?php echo defined('SPP_VER') ? SPP_VER : '0.5'; ?>"></script>
    <script type="module" src="js/admin.js?v=<?php echo defined('SPP_VER') ? SPP_VER . '.' . @filemtime(__DIR__ . '/js/admin.js') : time(); ?>"></script>
    <!-- Schema-based module settings enhancement (overrides openModuleSettings only) -->
    <script type="module" src="js/admin-settings.js?v=<?php echo defined('SPP_VER') ? SPP_VER . '.' . @filemtime(__DIR__ . '/js/admin-settings.js') : time(); ?>"></script>

    <!-- Local-First Storage Engine & Service Worker Registration -->
    <script type="module" src="js/lib/SPPLocalVault.js?v=<?php echo defined('SPP_VER') ? SPP_VER : '0.5'; ?>"></script>
    <script>
        if ('serviceWorker' in navigator && (window.location.protocol === 'https:' || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('./spp-dev-sw.js').then((reg) => {
                    console.log('[SPP-SW] Service Worker registered in scope:', reg.scope);
                }).catch((err) => {
                    console.warn('[SPP-SW] Service Worker registration failed (non-fatal):', err);
                });
            });
        }
    </script>
</body>

</html>
