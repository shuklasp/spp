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

// Force Admin Context for Session consistency
try {
    \SPP\Scheduler::getProcObj('sppadmin');
} catch (\Exception $e) {
    new \SPP\App('sppadmin');
}
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
                <button type="submit" class="btn primary-btn shine-effect" style="width:100%; justify-content:center;">
                    <span>Initialize Access</span>
                </button>
            </form>

            <div id="magic-link-section"
                style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--glass-border); text-align: center;">
                <p style="font-size: 0.85rem; color: var(--text-dim); margin-bottom: 0.5rem;">Or sign in without a
                    password</p>
                <div class="input-group" style="display:none;" id="magic-email-group">
                    <input type="email" id="magic_email" placeholder="Enter your email address...">
                    <button class="btn secondary-btn" style="width:100%; margin-top:0.5rem;"
                        onclick="app.sendMagicLink()">Send Magic Link</button>
                </div>
                <button class="btn secondary-btn" id="btn-show-magic" style="width:100%;"
                    onclick="document.getElementById('magic-email-group').style.display='block'; this.style.display='none';">Use
                    Magic Link</button>
            </div>

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

                    <!-- 1. CORE ARCHITECTURE -->
                    <div class="sidebar-section-title"
                        style="font-size: 0.65rem; color: #38bdf8; text-transform: uppercase; padding: 0 1rem; margin-bottom: 0.5rem; letter-spacing: 0.1em;">
                        Core Architecture</div>
                    <li><a href="#apps" class="nav-item" data-view="apps"
                            data-keywords="app studio applications modules registry scaffold scaffolding"
                            title="Manage your SPP Applications, Modules, and Scaffolding">
                            <span class="icon">📱</span> App Studio
                        </a></li>
                    <li><a href="#entities" class="nav-item" data-view="entities"
                            data-keywords="database entities schema models tables sql"
                            title="Manage DB Schema, Entities, and Magic DB Viewer">
                            <span class="icon">🏗️</span> Database & Entities
                        </a></li>
                    <li><a href="#forms" class="nav-item" data-view="forms"
                            data-keywords="forms html generator builder ui" title="Design and manage HTML forms">
                            <span class="icon">📝</span> Forms
                        </a></li>
                    <li><a href="#routing" class="nav-item" data-view="routing"
                            data-keywords="routing middleware url routes path dispatch"
                            title="Configure URL routing rules and Middleware">
                            <span class="icon">🔗</span> Routing & Middleware
                        </a></li>
                    <li><a href="#docs" class="nav-item" data-view="docs"
                            data-keywords="code explorer docs documentation reflection classes methods"
                            title="Explore SPP and App code via Reflection">
                            <span class="icon">📚</span> Code Explorer
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

                    <!-- 3. DEVELOPER HEAVEN -->
                    <div class="sidebar-section-title"
                        style="font-size: 0.65rem; color: #f43f5e; text-transform: uppercase; padding: 0 1rem; margin-bottom: 0.5rem; letter-spacing: 0.1em;">
                        SPP Studio (Heaven)</div>
                    <li><a href="#ai" class="nav-item" data-view="ai"
                            data-keywords="ai studio copilot chatgpt gemini anthropic playground llm models"
                            title="AI Copilot, Playground, and Provider Management">
                            <span class="icon">🧠</span> AI Studio
                        </a></li>
                    <li><a href="../index.php?__api=1&entity=docs" target="_blank" class="nav-item"
                            data-keywords="api explorer swagger rest endpoints zero-touch documentation"
                            title="Interactive zero-touch Swagger API documentation">
                            <span class="icon">📚</span> API Explorer
                        </a></li>
                    <li><a href="#commands" class="nav-item" data-view="commands"
                            data-keywords="command center cli terminal shell execute"
                            title="Execute SPP CLI commands from the browser">
                            <span class="icon">💻</span> Command Center
                        </a></li>
                    <li><a href="#mobile" class="nav-item" data-view="mobile"
                            data-keywords="mobile studio emulator responsive preview tablet phone"
                            title="Test views in mobile emulator modes">
                            <span class="icon">📱</span> Mobile Studio
                        </a></li>

                    <div class="sidebar-divider"
                        style="height: 1px; background: var(--glass-border); margin: 1rem 0; opacity: 0.5;"></div>

                    <!-- 4. ADVANCED ENGINES -->
                    <div class="sidebar-section-title"
                        style="font-size: 0.65rem; color: #10b981; text-transform: uppercase; padding: 0 1rem; margin-bottom: 0.5rem; letter-spacing: 0.1em;">
                        Advanced Engines</div>
                    <!-- Polyglot moved to System Diagnostics tab -->
                    <!-- <li><a href="#polyglot" class="nav-item" data-view="polyglot" title="Cross-language execution">
                            <span class="icon">🌍</span> Polyglot Engine
                        </a></li> -->
                    <li><a href="#reports" class="nav-item" data-view="reports"
                            data-keywords="report builder bi business intelligence analytics charts graphs"
                            title="Business Intelligence & Dynamic Reports">
                            <span class="icon">📊</span> Report Builder
                        </a></li>
                    <li><a href="#interdb" class="nav-item" data-view="interdb"
                            data-keywords="interdb mesh external db database connections"
                            title="Manage external database connections">
                            <span class="icon">🕸️</span> InterDB Mesh
                        </a></li>
                    <li><a href="#xdb" class="nav-item" data-view="xdb"
                            data-keywords="xml database xdb configs migrations seeders profiler"
                            title="Manage XML-based configuration databases">
                            <span class="icon">🗄️</span> XML Database
                        </a></li>
                    <!-- Queue moved to System Diagnostics tab -->
                    <li><a href="#spplang" class="nav-item" data-view="spplang"
                            data-keywords="translations spplang i18n locale languages multi-lingual"
                            title="Manage application string translations">
                            <span class="icon">🌐</span> Translations
                        </a></li>
                    <!-- Events merged into Trace (via System Diagnostics) -->
                    <li><a href="#services" class="nav-item" data-view="services"
                            data-keywords="services di dependency injection ajax live api backend"
                            title="Dependency Injection services and LiveServices">
                            <span class="icon">🔌</span> Services (DI & AJAX)
                        </a></li>
                    <li><a href="#parikshak" class="nav-item" data-view="parikshak"
                            data-keywords="parikshak testing tests unit assertions qa"
                            title="Run automated application tests">
                            <span class="icon">🧪</span> Parikshak Testing
                        </a></li>
                    <li><a href="#lifecycle" class="nav-item" data-view="lifecycle"
                            data-keywords="deployment lifecycle migrations environments deploy release"
                            title="Manage deployment lifecycles and migrations">
                            <span class="icon">🚀</span> Deployment
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
                console.log("SPP Admin Evolved Framework Loaded.");
            }
        }, 500); 
    </script>
</body>

</html>