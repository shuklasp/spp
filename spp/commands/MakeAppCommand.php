<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Class MakeAppCommand
 *
 * Creates a new SPP application context with comprehensive, tutorial-style scaffolding.
 * Each generated file includes detailed comments explaining:
 *   - WHAT the code does
 *   - WHY it exists in the framework
 *   - HOW to modify it for custom workflows
 *
 * Available modes:
 *   mixed  — Flagship. Combines SPP-UX + Blade + Native PHP + REST API in one app.
 *   sppux  — Reactive SPA using SPP-UX components, stores, themes, UI library.
 *   blade  — Server-rendered with Blade templates, controllers, and directives.
 *   native — Raw PHP pages with ViewPage augmentation, forms, and component mounts.
 *   api    — Headless REST API with entity CRUD, auth, and documentation.
 *   dropin — Low-code drop-in HTML/PHP with YAML form processing.
 *
 * @author SPP Framework
 */
class MakeAppCommand extends BaseMakeCommand
{
    protected string $name = 'make:app';
    protected string $description = 'Create a new SPP application context';

    public function execute(array $args): void
    {
        $appName = $args[2] ?? null;
        if (!$appName) {
            $appName = $this->prompt("Enter application name");
            if (!$appName) {
                echo "App name is required.\n";
                return;
            }
        }

        $appType = $args[3] ?? null;
        if (!$appType) {
            $types = ['mixed', 'sppux', 'blade', 'native', 'api', 'dropin'];
            echo "Available app types:\n";
            echo "  mixed  — Flagship: SPP-UX + Blade + PHP + REST API (recommended)\n";
            echo "  sppux  — Reactive SPA with SPP-UX components\n";
            echo "  blade  — Server-rendered with Blade templates\n";
            echo "  native — Raw PHP pages with augmentation pipeline\n";
            echo "  api    — Headless REST API backend\n";
            echo "  dropin — Low-code drop-in HTML/PHP files\n";
            $typeInput = $this->prompt("Enter app type", "mixed");
            if (!empty($typeInput) && in_array(strtolower($typeInput), $types)) {
                $appType = strtolower($typeInput);
            } else {
                $appType = 'mixed';
            }
        } else {
            $appType = strtolower($appType);
        }

        $baseUrl = $args[4] ?? null;
        if (!$baseUrl) {
            $baseUrlInput = $this->prompt("Enter base URL", "/" . $appName);
            $baseUrl = !empty($baseUrlInput) ? $baseUrlInput : "/" . $appName;
        }

        $tablePrefix = $args[5] ?? null;
        if (!$tablePrefix) {
            $tablePrefixInput = $this->prompt("Enter table prefix", $appName . "_");
            $tablePrefix = !empty($tablePrefixInput) ? $tablePrefixInput : $appName . "_";
        }

        $isEnterprise = in_array('--enterprise', $args);
        if (!$isEnterprise && count($args) <= 2) {
            $entInput = $this->prompt("Enable Enterprise Mode (Redis Cache & Session)? (y/N)", "N");
            if (strtolower($entInput) === 'y' || strtolower($entInput) === 'yes') {
                $isEnterprise = true;
            }
        }

        // 1. Create base directories
        $dirs = [
            SPP_APP_DIR . "/etc/apps/{$appName}",
            SPP_APP_DIR . "/etc/apps/{$appName}/forms",
            SPP_APP_DIR . "/src/{$appName}",
            SPP_APP_DIR . "/src/{$appName}/etc",
            SPP_APP_DIR . "/src/{$appName}/events",
            SPP_APP_DIR . "/src/{$appName}/serv",
            SPP_APP_DIR . "/src/{$appName}/entities",
            SPP_APP_DIR . "/src/{$appName}/middleware",
            SPP_APP_DIR . "/resources/{$appName}/views",
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }
        echo "Created base directory structure.\n";

        // 2. Update global-settings.yml
        $settingsPath = SPP_APP_DIR . "/spp/etc/global-settings.yml";
        if (file_exists($settingsPath)) {
            $settings = Yaml::parseFile($settingsPath);
            if (!isset($settings['apps'][$appName])) {
                $appConfig = [
                    'base_url' => $baseUrl,
                    'table_prefix' => $tablePrefix,
                    'type' => $appType,
                    'shared_group' => 'core',
                    'etc_path' => "etc/apps/{$appName}",
                    'src_path' => "src/{$appName}"
                ];

                if ($isEnterprise) {
                    $appConfig['session_handler'] = 'redis';
                    $appConfig['session_save_path'] = 'tcp://127.0.0.1:6379';
                    $appConfig['cache_driver'] = 'redis';
                    $appConfig['cache_host'] = '127.0.0.1';
                    $appConfig['cache_port'] = 6379;
                }

                $settings['apps'][$appName] = $appConfig;
                file_put_contents($settingsPath, Yaml::dump($settings, 10, 2));
                echo "Registered '{$appName}' in global-settings.yml (Type: {$appType}" . ($isEnterprise ? ', Enterprise Mode' : '') . ").\n";

                // Invalidate config cache
                $cacheFile = SPP_APP_DIR . '/var/cache/system/config.php';
                if (file_exists($cacheFile)) {
                    @unlink($cacheFile);
                    echo "Cleared config cache.\n";
                }
            } else {
                echo "Warning: App '{$appName}' already exists in global-settings.yml.\n";
            }
        }

        // 3. Generate events.yml
        $this->writeFile(
            "src/{$appName}/etc/events.yml",
            <<<YAML
################################################################################
# Event Listeners for {$appName}
#
# HOW TO USE:
# Register event handlers that run when framework events fire.
# Format:  event_name:  [\\Namespace\\ClassName, methodName]
#
# AVAILABLE EVENTS:
#   event_spp_view_render_theme — Fired before theme rendering (inject HTML)
#   event_spp_page_render       — Fired during page rendering
#   PageNotFound                — Fired when no route matches
#   app.boot                    — Custom app boot event
#
# EXAMPLE:
#   app.boot:
#     - \\App\\{$appName}\\Events\\AppBootHandler
################################################################################

events:
  # app.boot:
  #   - \\App\\{$appName}\\Events\\AppBootHandler
YAML
        );

        // 4. Scaffold mode-specific files
        echo "\nScaffolding '{$appType}' mode...\n";
        $this->scaffoldByType($appName, $appType);

        // 5. Generate common structure shared by ALL modes
        $this->scaffoldCommonStructure($appName, $appType);

        // 6. Auto-run database migrations if DB is enabled
        if (\SPP\Module::isEnabled('sppdb')) {
            echo "\nRunning database migrations (App: {$appName} and modules)...\n";
            $migrateCmd = sprintf('php spp.php migrate --app=%s', escapeshellarg($appName));
            passthru($migrateCmd);
        }

        echo "\n✅ Application '{$appName}' created successfully!\n";
        echo "   Mode:   {$appType}\n";
        echo "   Source: src/{$appName}/\n";
        echo "   Config: etc/apps/{$appName}/\n";
        echo "   URL:    {$baseUrl}\n";
    }

    // =========================================================================
    // Mode Dispatcher
    // =========================================================================

    private function scaffoldByType(string $appName, string $appType): void
    {
        $method = 'scaffold' . ucfirst($appType) . 'Mode';
        if (method_exists($this, $method)) {
            $this->$method($appName);
        } else {
            echo "  Unknown type '{$appType}', falling back to 'mixed' scaffold.\n";
            $this->scaffoldMixedMode($appName);
        }
    }

    // =========================================================================
    // Helper: Write file with placeholder replacement
    // =========================================================================

    private function writeFile(string $relativePath, string $content, string $appName = ''): void
    {
        $path = SPP_APP_DIR . '/' . $relativePath;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (!file_exists($path)) {
            if ($appName !== '') {
                $content = str_replace('{{APP_NAME}}', $appName, $content);
            }
            file_put_contents($path, $content);
            echo "  ✓ " . $relativePath . "\n";
        }
    }

    private function writePagesYml(string $appName, array $config): void
    {
        $pagesFile = SPP_APP_DIR . "/etc/apps/{$appName}/pages.yml";
        // Only write if doesn't exist
        if (!file_exists($pagesFile)) {
            file_put_contents($pagesFile, Yaml::dump($config, 10, 2));
            echo "  ✓ etc/apps/{$appName}/pages.yml\n";
        }
    }

    // =========================================================================
    //  MIXED MODE — The Flagship (All Paradigms Combined)
    // =========================================================================

    private function scaffoldMixedMode(string $appName): void
    {
        echo "  ── Mixed Mode: SPP-UX + Blade + Native PHP + REST API ──\n";

        // ── pages.yml ──
        $this->writePagesYml($appName, [
            'defaults' => [
                'home' => 'home',
                'pagedir' => "/src/{$appName}",
            ],
            'specials' => [
                ['name' => 'resources', 'method' => 'serveResource'],
            ],
            'pages' => [
                // SPP-UX SPA entry (standalone HTML document)
                'app' => ['url' => 'index.php', 'special' => 1],
                // Controller routes (Blade rendering)
                'home' => ['controller' => "\\App\\{$appName}\\Serv\\HomeController@index"],
                'about' => ['controller' => "\\App\\{$appName}\\Serv\\HomeController@about"],
                'guide' => ['controller' => "\\App\\{$appName}\\Serv\\HomeController@guide"],
                'dashboard' => ['controller' => "\\App\\{$appName}\\Serv\\DashboardController@index"],
                'login' => ['controller' => "\\App\\{$appName}\\Serv\\AuthController@loginForm"],
                'auth/login' => ['controller' => "\\App\\{$appName}\\Serv\\AuthController@login"],
                'auth/logout' => ['controller' => "\\App\\{$appName}\\Serv\\AuthController@logout"],
                // REST API
                'api/v1/items' => ['controller' => "\\App\\{$appName}\\Serv\\ApiController@index"],
                'api/v1/items/{id}' => ['controller' => "\\App\\{$appName}\\Serv\\ApiController@show"],
                // Native PHP pages (augmented with JS/CSS injection)
                'contact' => ['url' => 'pages/contact.php'],
                'guide' => ['url' => 'pages/guide.php'],
                // Error pages
                'error/404' => ['url' => 'pages/errors/404.php'],
                'error/500' => ['url' => 'pages/errors/500.php'],
                // Asset routes
                'assets' => ['assets' => 'assets'],
                'theme-assets' => ['assets' => 'resources/themes'],
                'comp-assets' => ['assets' => 'comp'],
            ],
        ]);

        // ── SPP-UX entry point ──
        $this->writeSppuxEntryPoint($appName);

        // ── SPP-UX components ──
        $this->writeSppuxComponents($appName);

        // ── Controllers ──
        $this->writeHomeController($appName);
        $this->writeDashboardController($appName);
        $this->writeApiController($appName);
        $this->writeAttributeController($appName);

        // ── Blade templates ──
        $this->writeBladeLayout($appName);
        $this->writeBladeHome($appName);
        $this->writeBladeAbout($appName);
        $this->writeBladeDashboard($appName);

        // ── Native PHP pages ──
        $this->writeNativeContactPage($appName);
        $this->writeNativeGuidePage($appName);

        // ── YAML forms ──
        $this->writeContactForm($appName);

        // ── Custom error pages ──
        $this->writeCustomErrorPages($appName);

        // ── Parikshak tests ──
        $this->writeParikshakTests($appName);
    }

    // =========================================================================
    //  SPP-UX MODE — Reactive SPA Focus
    // =========================================================================

    private function scaffoldSppuxMode(string $appName): void
    {
        echo "  ── SPP-UX Mode: Reactive Component SPA ──\n";

        $this->writePagesYml($appName, [
            'defaults' => [
                'home' => 'index',
                'pagedir' => "/src/{$appName}",
            ],
            'pages' => [
                'index' => ['url' => 'index.php', 'special' => 1],
                'assets' => ['assets' => 'assets'],
                'comp-assets' => ['assets' => 'comp'],
            ],
        ]);

        $this->writeSppuxEntryPoint($appName);
        $this->writeSppuxComponents($appName);
        $this->writeSppuxThemePicker($appName);
        $this->writeSppuxFormDemo($appName);
        $this->writeSppuxGuidePage($appName);
        $this->writeCustomErrorPages($appName);
        $this->writeParikshakTests($appName);
    }

    // =========================================================================
    //  BLADE MODE — Server-Rendered Template Focus
    // =========================================================================

    private function scaffoldBladeMode(string $appName): void
    {
        echo "  ── Blade Mode: Server-Rendered Templates ──\n";

        $this->writePagesYml($appName, [
            'defaults' => [
                'home' => 'home',
                'pagedir' => "/src/{$appName}",
            ],
            'pages' => [
                'home' => ['controller' => "\\App\\{$appName}\\Serv\\HomeController@index"],
                'about' => ['controller' => "\\App\\{$appName}\\Serv\\HomeController@about"],
                'guide' => ['controller' => "\\App\\{$appName}\\Serv\\HomeController@guide"],
                'dashboard' => ['controller' => "\\App\\{$appName}\\Serv\\DashboardController@index"],
                'login' => ['controller' => "\\App\\{$appName}\\Serv\\AuthController@loginForm"],
                'auth/login' => ['controller' => "\\App\\{$appName}\\Serv\\AuthController@login"],
                'auth/logout' => ['controller' => "\\App\\{$appName}\\Serv\\AuthController@logout"],
                'error/404' => ['url' => 'pages/errors/404.php'],
                'error/500' => ['url' => 'pages/errors/500.php'],
                'assets' => ['assets' => 'assets'],
                'comp-assets' => ['assets' => 'comp'],
            ],
        ]);

        $this->writeHomeController($appName);
        $this->writeDashboardController($appName);
        $this->writeBladeLayout($appName);
        $this->writeBladeHome($appName);
        $this->writeBladeAbout($appName);
        $this->writeBladeDashboard($appName);
        $this->writeSppuxComponents($appName); // Blade can mount SPP-UX via @sppux
        $this->writeAttributeController($appName);
        $this->writeBladeGuidePage($appName);
        $this->writeContactForm($appName);
        $this->writeCustomErrorPages($appName);
        $this->writeParikshakTests($appName);
    }

    // =========================================================================
    //  NATIVE MODE — Raw PHP Pages with Augmentation
    // =========================================================================

    private function scaffoldNativeMode(string $appName): void
    {
        echo "  ── Native Mode: PHP Pages with Augmentation ──\n";

        $this->writePagesYml($appName, [
            'defaults' => [
                'home' => 'index',
                'pagedir' => "/src/{$appName}",
            ],
            'pages' => [
                'index' => ['url' => 'pages/index.php'],
                'contact' => ['url' => 'pages/contact.php'],
                'guide' => ['url' => 'pages/guide.php'],
                'error/404' => ['url' => 'pages/errors/404.php'],
                'error/500' => ['url' => 'pages/errors/500.php'],
                'dashboard' => ['controller' => "\\App\\{$appName}\\Serv\\DashboardController@index"],
                'login' => ['controller' => "\\App\\{$appName}\\Serv\\AuthController@loginForm"],
                'auth/login' => ['controller' => "\\App\\{$appName}\\Serv\\AuthController@login"],
                'auth/logout' => ['controller' => "\\App\\{$appName}\\Serv\\AuthController@logout"],
                'assets' => ['assets' => 'assets'],
                'comp-assets' => ['assets' => 'comp'],
            ],
        ]);

        $this->writeNativeIndexPage($appName);
        $this->writeNativeContactPage($appName);
        $this->writeNativeGuidePage($appName);
        $this->writeDashboardController($appName);
        $this->writeSppuxComponents($appName); // Native pages can mount UX via SPPUX::render()
        $this->writeContactForm($appName);
        $this->writeCustomErrorPages($appName);
        $this->writeParikshakTests($appName);
    }

    // =========================================================================
    //  API MODE — Headless REST Backend
    // =========================================================================

    private function scaffoldApiMode(string $appName): void
    {
        echo "  ── API Mode: Headless REST Backend ──\n";

        $this->writePagesYml($appName, [
            'defaults' => [
                'home' => 'api/v1',
            ],
            'pages' => [
                'api/v1' => ['controller' => "\\App\\{$appName}\\Serv\\ApiController@index"],
                'api/v1/items' => ['controller' => "\\App\\{$appName}\\Serv\\ApiController@index"],
                'api/v1/items/{id}' => ['controller' => "\\App\\{$appName}\\Serv\\ApiController@show"],
                'api/auth/login' => ['controller' => "\\App\\{$appName}\\Serv\\AuthController@apiLogin"],
                'api/docs' => ['controller' => "\\App\\{$appName}\\Serv\\ApiController@docs"],
                'api/docs/json' => ['controller' => "\\App\\{$appName}\\Serv\\ApiDocsController@index"],
                'api/docs/html' => ['url' => 'pages/api-docs.php', 'special' => 1],
                'error/404' => ['url' => 'pages/errors/404.php'],
                'error/500' => ['url' => 'pages/errors/500.php'],
            ],
        ]);

        $this->writeApiController($appName);
        $this->writeApiGuidePage($appName);
        $this->writeCustomErrorPages($appName);
        $this->writeParikshakTests($appName);
    }

    // =========================================================================
    //  DROPIN MODE — Low-Code Drop-in HTML/PHP
    // =========================================================================

    private function scaffoldDropinMode(string $appName): void
    {
        echo "  ── Drop-in Mode: Low-Code HTML/PHP ──\n";

        $this->writePagesYml($appName, [
            'defaults' => [
                'home' => 'index',
                'pagedir' => "/src/{$appName}",
            ],
            'pages' => [
                'index' => ['url' => 'index.php', 'special' => 1],
                'assets' => ['assets' => 'assets'],
            ],
        ]);

        // Drop-in entry point with its own mini-router
        $this->writeFile(
            "src/{$appName}/index.php",
            <<<'PHP'
<?php
/**
 * ============================================================================
 * {{APP_NAME}} — Drop-in Mode Entry Point
 * ============================================================================
 *
 * HOW THIS WORKS:
 * This file is included by the SPP ViewRouter when a request matches the
 * 'index' route (special: 1). It acts as a self-contained mini-application
 * with its own simple router that serves files from the resources/views/ dir.
 *
 * HOW TO ADD A NEW PAGE:
 * 1. Create a file `contact.php` in `src/{{APP_NAME}}/pages/`
 * 2. Access it at: /{base_url}/index?page=contact
 * 3. Add to navigation in `index.php`
 *
 * HOW TO UPGRADE:
 * To use the full SPP routing pipeline instead, switch to 'native' or 'mixed'
 * mode by editing etc/apps/{{APP_NAME}}/pages.yml and removing 'special: 1'.
 * ============================================================================
 */

// The SPP framework is already booted — do NOT require sppinit.php again.
// The app context is already set — do NOT call App::getApp() again.

// ── Process YAML-driven forms if the SPPView module is available ──
if (class_exists('\SPPMod\SPPView\ViewPage')) {
    \SPPMod\SPPView\ViewPage::processForms();
}

// ── Simple page router ──
$page = $_GET['page'] ?? 'index';
$page = preg_replace('/[^a-zA-Z0-9_\-]/', '', $page); // Sanitize

$viewsDir = SPP_APP_DIR . '/resources/{{APP_NAME}}/views/';

if (file_exists($viewsDir . $page . '.php')) {
    include $viewsDir . $page . '.php';
} elseif (file_exists($viewsDir . $page . '.html')) {
    echo file_get_contents($viewsDir . $page . '.html');
} else {
    echo "<h1>404 — Page Not Found</h1>";
    echo "<p>Create <code>resources/{{APP_NAME}}/views/{$page}.php</code> to add this page.</p>";
}
PHP
            ,
            $appName
        );

        // Drop-in sample view
        $viewsDir = SPP_APP_DIR . "/resources/{$appName}/views";
        if (!is_dir($viewsDir))
            mkdir($viewsDir, 0777, true);

        $this->writeFile(
            "resources/{$appName}/views/index.html",
            <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{APP_NAME}} — Drop-in App</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f8fafc; --primary: #6366f1; --text: #0f172a; --muted: #64748b; }
        body { margin: 0; font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text); }
        .container { max-width: 800px; margin: 3rem auto; padding: 0 2rem; }
        .card { background: #fff; border-radius: 20px; padding: 2.5rem; box-shadow: 0 15px 40px rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.04); }
        h1 { font-size: 2.2rem; margin: 0 0 0.5rem 0; font-weight: 800; }
        .badge { display: inline-block; background: #e0e7ff; color: #4f46e5; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; margin-bottom: 1.5rem; }
        p { color: var(--muted); line-height: 1.7; }
        code { background: #f1f5f9; padding: 2px 8px; border-radius: 6px; font-size: 0.9em; }
        h3 { color: var(--primary); margin-top: 2rem; }
        ul { color: var(--muted); line-height: 2; }
        .form-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #f1f5f9; }
        .form-group { margin-bottom: 1.2rem; }
        label { display: block; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.4rem; color: var(--muted); }
        input, textarea { width: 100%; padding: 0.8rem; border-radius: 10px; border: 1px solid #e2e8f0; font-family: inherit; font-size: 1rem; }
        input:focus, textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        .btn { background: var(--primary); color: #fff; border: none; padding: 0.9rem 2rem; border-radius: 10px; font-weight: 600; cursor: pointer; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(99,102,241,0.25); }
        footer { text-align: center; margin-top: 3rem; font-size: 0.85rem; color: var(--muted); }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="badge">DROP-IN MODE</div>
            <h1>{{APP_NAME}}</h1>
            <p>Welcome to your Drop-in application. This is the simplest SPP app mode — just add HTML or PHP files to <code>resources/{{APP_NAME}}/views/</code> and they're instantly accessible.</p>

            <h3>📂 How Drop-in Mode Works</h3>
            <ul>
                <li><b>Create pages:</b> Add `.php` or `.html` files in <code>src/{{APP_NAME}}/pages/</code></li>
                <li><b>Access them:</b> Visit <code>/index?page=filename</code> (without extension)</li>
                <li><b>Include UI components:</b> Use <code><?php include __DIR__ . '/../ui/navbar.php'; ?></code></li>
                <li><b>Upgrade:</b> Switch to <code>native</code> or <code>mixed</code> mode for full framework features</li>
            </ul>

            <div class="form-section">
                <h3>📝 Sample Form</h3>
                <p>This form uses the SPP YAML form engine. Define forms in <code>etc/apps/{{APP_NAME}}/forms/contact.yml</code>.</p>
                <form method="POST">
                    <input type="hidden" name="spp_form_id" value="contact">
                    <div class="form-group">
                        <label>Your Name</label>
                        <input type="text" name="guest_name" placeholder="John Doe" required>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" placeholder="How can we help?" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn">Submit</button>
                </form>
            </div>
        </div>
        <footer>&copy; <?php echo date('Y'); ?> {{APP_NAME}} • Powered by SPP Framework</footer>
    </div>
</body>
</html>
HTML
            ,
            $appName
        );

        $this->writeDropinGuidePage($appName);
        $this->writeContactForm($appName);
    }

    // =========================================================================
    //  SHARED FILE GENERATORS
    // =========================================================================

    // ── SPP-UX Entry Point (used by mixed + sppux modes) ──

    private function writeSppuxEntryPoint(string $appName): void
    {
        $this->writeFile(
            "src/{$appName}/index.php",
            <<<'PHP'
<?php
/**
 * ============================================================================
 * {{APP_NAME}} — SPP-UX Single Page Application Entry Point
 * ============================================================================
 *
 * HOW THIS WORKS:
 * This file is included by the SPP ViewRouter as a "special: 1" page.
 * Special pages bypass the augmentation pipeline (no auto JS/CSS injection)
 * because they provide their own complete HTML document.
 *
 * The SPP framework is ALREADY BOOTED when this file runs:
 *   - Do NOT add: require_once 'sppinit.php'   (already loaded)
 *   - Do NOT add: \SPP\App::getApp()            (context already set)
 *
 * SPP-UX RUNTIME ASSETS (loaded via PHP helpers):
 *   - SPPUX::runtimePath()  → Core reactive engine (sppux.js)
 *   - SPPUX::uiPath()       → UI Library: Modal, Toast, Drawer, Spotlight
 *   - SPPUX::cssPath()      → Glassmorphic CSS with 7 built-in themes
 *   - SPPUX::loaderPath()   → Auto-mounts components with data-spp-component
 *   - SPPUX::bridgePath()   → PHP↔JS bridge for API/service calls
 *
 * HOW TO MODIFY:
 *   - Change the mounted component: edit data-spp-path attribute below
 *   - Add more components: add more data-spp-component divs
 *   - Switch theme: SPPUX.Theme.set('midnight'|'emerald'|'cyberpunk'|'ocean'|'saffron'|'day')
 * ============================================================================
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo '{{APP_NAME}}'; ?> — SPP-UX Application</title>

    <!-- SPP-UX Glassmorphic CSS (includes all theme presets) -->
    <link rel="stylesheet" href="<?php echo \SPPMod\Drishyam\SPPUX::cssPath(); ?>">

    <!-- Google Fonts for premium typography -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ── App-level CSS overrides ─────────────────────────────────
         * Override SPP-UX CSS variables here for custom branding.
         * See the full list in spp/modules/spp/drishyam/css/sppux.css
         */
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        /* Loading state while SPP-UX runtime boots */
        .spp-app-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: var(--sppux-bg, #0f0f23);
            color: var(--sppux-text, #e2e8f0);
            font-family: 'Inter', sans-serif;
        }
        .spp-app-loading .spinner {
            width: 40px; height: 40px;
            border: 3px solid rgba(99,102,241,0.2);
            border-top-color: #6366f1;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: 1rem;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <!--
        ═══════════════════════════════════════════════════════════════
        SPP-UX Component Mount Point

        HOW IT WORKS:
        The SPP-UX loader (spp-loader.js) scans the DOM for elements
        with data-spp-component="1" and auto-mounts them.

        ATTRIBUTES:
          data-spp-component="1"  — Marks this as a mount point
          data-spp-type="ux"      — Component type (always "ux" for SPP-UX)
          data-spp-path="..."     — Path to the component JS file
          data-spp-props='...'    — JSON-encoded props passed to the component

        TO ADD MORE COMPONENTS:
        Just add more divs with these attributes anywhere in the HTML.
        ═══════════════════════════════════════════════════════════════
    -->
    <div data-spp-component="1"
         data-spp-type="ux"
         data-spp-path="<?php echo \SPPMod\Drishyam\SPPUX::componentPath('main'); ?>"
         data-spp-props='{"appName":"<?php echo '{{APP_NAME}}'; ?>", "appRoot":"<?php echo \SPP\App::getBaseUrl(); ?>"}'>
        <!-- This content shows while the component loads -->
        <div class="spp-app-loading">
            <div class="spinner"></div>
            <span>Loading <?php echo '{{APP_NAME}}'; ?>...</span>
        </div>
    </div>

    <!-- ═══ SPP-UX Runtime Scripts ═══ -->

    <!-- Core reactive engine: BaseComponent, html``, setState, render cycle -->
    <script type="module" src="<?php echo \SPPMod\Drishyam\SPPUX::runtimePath(); ?>"></script>

    <!-- UI Library: SPPUX.Modal, SPPUX.Notify, SPPUX.Confirm, SPPUX.Theme, etc. -->
    <script type="module" src="<?php echo \SPPMod\Drishyam\SPPUX::uiPath(); ?>"></script>

    <!-- PHP↔JS Bridge: spp_admin.api(), spp_admin.callAppService(), etc. -->
    <script type="module" src="<?php echo \SPPMod\Drishyam\SPPUX::bridgePath(); ?>"></script>

    <!-- Auto-mounter: scans DOM for data-spp-component and instantiates them -->
    <script type="module" src="<?php echo \SPPMod\Drishyam\SPPUX::loaderPath(); ?>"></script>

    <!-- SPPLive: LiveComponent reactivity engine (wire:click, wire:model) -->
    <script src="<?php echo \SPP\App::getAssetUrl('core', 'admin_js', 'spplive.min.js'); ?>"></script>
</body>
</html>
PHP
            ,
            $appName
        );
    }

    // ── SPP-UX Components ──

    private function writeSppuxComponents(string $appName): void
    {
        $compDir = SPP_APP_DIR . "/src/{$appName}/comp";
        if (!is_dir($compDir))
            mkdir($compDir, 0777, true);

        // ── main.js: Full dashboard component ──
        $this->writeFile(
            "src/{$appName}/comp/main.js",
            <<<'JS'
/**
 * ============================================================================
 * Main Dashboard Component — {{APP_NAME}}
 * ============================================================================
 *
 * This is the root SPP-UX component for your application. It demonstrates
 * ALL major SPP-UX features in one working example.
 *
 * SPP-UX COMPONENT LIFECYCLE:
 *   1. constructor()  — Component instantiated (don't override directly)
 *   2. onInit()       — Set initial state, register stores (async)
 *   3. render()       — Return HTML via html`` tagged template
 *   4. onMount()      — DOM is ready, fetch data, start timers (async)
 *   5. afterUpdate()  — Called after every re-render (state change)
 *   6. onDestroy()    — Cleanup: unsubscribe stores, clear timers
 *
 * STATE MANAGEMENT:
 *   this.state         — Current state object (read-only outside setState)
 *   this.setState({})  — Merge new state → triggers re-render
 *   this.props         — Read-only props from parent or data-spp-props
 *
 * API CALLS:
 *   this.service('name', params)          — Call a registered PHP service (works everywhere)
 *   this.serv['service.name'](params)     — Proxy shorthand for service calls
 *   this.api('action', data)              — API call via SPPUX.api()
 *   this.apiPost(formData)                — POST API call with FormData
 *
 * UI HELPERS:
 *   this.notify('msg', 'success')       — Toast notification
 *   this.confirm('Are you sure?')       — Confirmation dialog (returns Promise)
 *   this.prompt('Enter value:')         — Input prompt (returns Promise)
 *   SPPUX.Modal.open('Title', content)  — Open modal
 *   SPPUX.Theme.set('midnight')         — Switch theme
 *   SPPUX.Notify.show('msg', 'info')    — Global notification
 *
 * HOW TO MODIFY:
 *   - Edit render() to change the UI layout
 *   - Edit onInit() to change initial state
 *   - Add new methods for business logic
 *   - Import sub-components by adding more data-spp-component divs in render()
 * ============================================================================
 */
export default class Main extends BaseComponent {

    /**
     * Called once before first render. Set up initial state here.
     * This is async — you can await API calls.
     */
    async onInit() {
        this.setState({
            appName: this.props.appName || '{{APP_NAME}}',
            activeTab: 'welcome',
            items: [],
            loading: false,
            theme: 'midnight',
            counter: 0,
            stats: [
                { label: 'Components', value: '5', icon: '🧩' },
                { label: 'Routes', value: '12', icon: '🗺️' },
                { label: 'Services', value: '3', icon: '⚡' }
            ]
        });
    }

    /**
     * Called after the first render when DOM is available.
     * Fetch initial data, set up event listeners, etc.
     */
    async onMount() {
        // Example: Load items from the API service
        // Uncomment when you have a working backend:
        // await this.loadItems();
    }

    /**
     * Called when this component is removed from the DOM.
     * Clean up timers, event listeners, store subscriptions here.
     */
    onDestroy() {
        // Example: clearInterval(this.timer);
    }

    // ── Business Logic Methods ──────────────────────────────────

    async loadItems() {
        this.setState({ loading: true });
        try {
            // Call a registered PHP service (defined in etc/services.yml)
            // this.service() works in ALL contexts (admin panel + standalone)
            // Alternative shorthand: this.serv['task.create']({...})
            const result = await this.service('task.create', {
                taskTitle: 'Sample Item',
                taskPriority: 'High'
            });
            this.notify('Service called successfully!', 'success');
            this.setState({ loading: false });
        } catch (e) {
            this.notify('Failed to load: ' + e.message, 'error');
            this.setState({ loading: false });
        }
    }

    async showModal() {
        // SPPUX.Modal — built-in modal dialog
        SPPUX.Modal.open('Framework Info', `
            <div style="padding: 1rem;">
                <h3>SPP-UX Component System</h3>
                <p>This modal was opened from a component method using:</p>
                <pre>SPPUX.Modal.open('Title', content, actions)</pre>
                <p>You can pass action buttons as the 3rd argument.</p>
            </div>
        `, [
            { label: 'Got it!', type: 'primary', fn: (m) => m.close() }
        ]);
    }

    async showConfirm() {
        // this.confirm() — returns a Promise<boolean>
        const confirmed = await this.confirm('Do you want to proceed?');
        this.notify(confirmed ? 'You confirmed!' : 'You cancelled.', confirmed ? 'success' : 'info');
    }

    async showPrompt() {
        // this.prompt() — returns a Promise<string|null>
        const name = await this.prompt('What is your name?', 'Developer');
        if (name) {
            this.notify(`Hello, ${name}! 👋`, 'success');
        }
    }

    switchTheme(name) {
        // SPPUX.Theme.set() — switch between 7 built-in themes
        // Available: midnight, emerald, royal, cyberpunk, ocean, saffron, day
        SPPUX.Theme.set(name);
        this.setState({ theme: name });
        this.notify(`Theme switched to ${name}`, 'success');
    }

    increment() {
        this.setState({ counter: this.state.counter + 1 });
    }

    switchTab(tab) {
        this.setState({ activeTab: tab });
    }

    // ── Render Method ───────────────────────────────────────────
    // Must return html`` tagged template literal.
    // Use ${expression} for dynamic values.
    // Use @click, @input, @change for event binding.

    render() {
        return html`
            <div style="min-height: 100vh; padding: 2rem;">
                <!-- Navigation Bar -->
                <nav style="display:flex; align-items:center; justify-content:space-between; margin-bottom:2rem; padding:1rem 1.5rem; background:var(--sppux-panel, rgba(30,30,60,0.8)); border-radius:16px; backdrop-filter:blur(20px);">
                    <div style="display:flex; align-items:center; gap:0.8rem;">
                        <span style="font-size:1.5rem;">🚀</span>
                        <span style="font-weight:700; font-size:1.2rem;">${this.state.appName}</span>
                        <span style="font-size:0.75rem; opacity:0.5; padding:2px 8px; background:rgba(99,102,241,0.2); border-radius:8px;">SPP-UX</span>
                    </div>
                    <div style="display:flex; gap:0.5rem;">
                        ${['welcome', 'features', 'themes', 'api'].map(tab => html`
                            <button @click="${() => this.switchTab(tab)}"
                                    style="padding:0.5rem 1rem; border:none; border-radius:8px; cursor:pointer; font-family:inherit; font-weight:${this.state.activeTab === tab ? '600' : '400'}; background:${this.state.activeTab === tab ? 'var(--sppux-primary, #6366f1)' : 'transparent'}; color:${this.state.activeTab === tab ? '#fff' : 'inherit'};">
                                ${tab.charAt(0).toUpperCase() + tab.slice(1)}
                            </button>
                        `)}
                    </div>
                </nav>

                <!-- Stats Grid -->
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; margin-bottom:2rem;">
                    ${this.state.stats.map(s => html`
                        <div style="padding:1.5rem; background:var(--sppux-panel, rgba(30,30,60,0.6)); border-radius:14px; backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,0.05);">
                            <span style="font-size:1.5rem;">${s.icon}</span>
                            <div style="font-size:0.8rem; opacity:0.6; margin-top:0.5rem;">${s.label}</div>
                            <div style="font-size:1.4rem; font-weight:700;">${s.value}</div>
                        </div>
                    `)}
                </div>

                <!-- Tab Content -->
                <div style="background:var(--sppux-panel, rgba(30,30,60,0.6)); border-radius:20px; padding:2rem; backdrop-filter:blur(20px); border:1px solid rgba(255,255,255,0.05);">
                    ${this.state.activeTab === 'welcome' ? this.renderWelcome() : ''}
                    ${this.state.activeTab === 'features' ? this.renderFeatures() : ''}
                    ${this.state.activeTab === 'themes' ? this.renderThemes() : ''}
                    ${this.state.activeTab === 'api' ? this.renderApi() : ''}
                </div>

                <!-- Footer -->
                <footer style="text-align:center; margin-top:2rem; opacity:0.4; font-size:0.85rem;">
                    &copy; ${new Date().getFullYear()} ${this.state.appName} • Powered by SPP-UX Framework
                </footer>
            </div>
        `;
    }

    renderWelcome() {
        return html`
            <div>
                <h2 style="margin-top:0;">👋 Welcome to ${this.state.appName}</h2>
                <p style="opacity:0.7; line-height:1.7;">
                    This is your SPP-UX application scaffold. It's a <b>live, interactive tutorial</b>
                    that demonstrates every feature of the SPP-UX component system.
                </p>
                <p style="opacity:0.7; line-height:1.7;">
                    <b>What you can do:</b>
                </p>
                <ul style="opacity:0.7; line-height:2;">
                    <li>Edit <code>comp/main.js</code> to modify this component</li>
                    <li>Create new components in <code>comp/</code> directory</li>
                    <li>Mount sub-components using <code>data-spp-component</code> divs in render()</li>
                    <li>Call PHP services via <code>this.service('name', params)</code></li>
                    <li>Use 7 built-in themes via <code>SPPUX.Theme.set()</code></li>
                </ul>

                <h3>🧮 Interactive Counter Demo</h3>
                <p style="opacity:0.5; font-size:0.9rem;">
                    Demonstrates <code>this.setState()</code> → automatic re-render
                </p>
                <div style="display:flex; align-items:center; gap:1rem; margin-top:1rem;">
                    <button @click="${() => this.increment()}"
                            style="padding:0.7rem 1.5rem; background:var(--sppux-primary, #6366f1); color:#fff; border:none; border-radius:10px; cursor:pointer; font-weight:600;">
                        Count: ${this.state.counter}
                    </button>
                    <button @click="${() => this.showModal()}"
                            style="padding:0.7rem 1.5rem; background:rgba(99,102,241,0.15); color:var(--sppux-primary, #6366f1); border:1px solid var(--sppux-primary, #6366f1); border-radius:10px; cursor:pointer; font-weight:600;">
                        Open Modal
                    </button>
                    <button @click="${() => this.showConfirm()}"
                            style="padding:0.7rem 1.5rem; background:rgba(16,185,129,0.15); color:#10b981; border:1px solid #10b981; border-radius:10px; cursor:pointer; font-weight:600;">
                        Confirm Dialog
                    </button>
                    <button @click="${() => this.showPrompt()}"
                            style="padding:0.7rem 1.5rem; background:rgba(245,158,11,0.15); color:#f59e0b; border:1px solid #f59e0b; border-radius:10px; cursor:pointer; font-weight:600;">
                        Prompt Input
                    </button>
                </div>
            </div>
        `;
    }

    renderFeatures() {
        const features = [
            { icon: '⚡', title: 'Reactive State', desc: 'this.setState({key: value}) triggers automatic re-renders.' },
            { icon: '🎨', title: 'Tagged Templates', desc: 'html`` literal for safe, efficient DOM rendering.' },
            { icon: '🔄', title: 'Lifecycle Hooks', desc: 'onInit → render → onMount → afterUpdate → onDestroy' },
            { icon: '📦', title: 'Component Composition', desc: 'Mount sub-components via data-spp-component divs.' },
            { icon: '🌐', title: 'Service Bridge', desc: 'this.serv[name]() calls PHP services from JavaScript.' },
            { icon: '💬', title: 'UI Helpers', desc: 'Modal, Toast, Confirm, Prompt, Drawer, Spotlight built-in.' },
            { icon: '🎭', title: '7 Themes', desc: 'midnight, emerald, royal, cyberpunk, ocean, saffron, day.' },
            { icon: '📊', title: 'SPPStore', desc: 'Shared state across components with subscribe/notify.' },
        ];
        return html`
            <div>
                <h2 style="margin-top:0;">🚀 SPP-UX Features</h2>
                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:1rem;">
                    ${features.map(f => html`
                        <div style="padding:1.2rem; background:rgba(255,255,255,0.03); border-radius:12px; border:1px solid rgba(255,255,255,0.06);">
                            <span style="font-size:1.5rem;">${f.icon}</span>
                            <h4 style="margin:0.5rem 0 0.3rem;">${f.title}</h4>
                            <p style="margin:0; font-size:0.85rem; opacity:0.6;">${f.desc}</p>
                        </div>
                    `)}
                </div>
            </div>
        `;
    }

    renderThemes() {
        const themes = ['midnight', 'emerald', 'royal', 'cyberpunk', 'ocean', 'saffron', 'day'];
        return html`
            <div>
                <h2 style="margin-top:0;">🎨 Theme Switcher</h2>
                <p style="opacity:0.6;">Click a theme to switch instantly. Themes use CSS variables — override them in your app CSS for custom branding.</p>
                <div style="display:flex; flex-wrap:wrap; gap:0.8rem; margin-top:1rem;">
                    ${themes.map(t => html`
                        <button @click="${() => this.switchTheme(t)}"
                                style="padding:0.8rem 1.5rem; border:2px solid ${this.state.theme === t ? 'var(--sppux-primary, #6366f1)' : 'rgba(255,255,255,0.1)'}; background:${this.state.theme === t ? 'var(--sppux-primary, #6366f1)' : 'rgba(255,255,255,0.05)'}; color:${this.state.theme === t ? '#fff' : 'inherit'}; border-radius:10px; cursor:pointer; font-weight:600; font-family:inherit; text-transform:capitalize;">
                            ${t}
                        </button>
                    `)}
                </div>
                <div style="margin-top:1.5rem; padding:1rem; background:rgba(255,255,255,0.03); border-radius:10px; font-family:monospace; font-size:0.85rem;">
                    <span style="opacity:0.5;">// Switch theme from JavaScript:</span><br>
                    SPPUX.Theme.set('${this.state.theme}');
                </div>
            </div>
        `;
    }

    renderApi() {
        return html`
            <div>
                <h2 style="margin-top:0;">🌐 Service & API Integration</h2>
                <p style="opacity:0.6; line-height:1.7;">
                    SPP-UX components can call PHP services and REST APIs. Services are defined in
                    <code>etc/services.yml</code> and called via the bridge.
                </p>

                <h3>Service Call Example</h3>
                <div style="padding:1rem; background:rgba(255,255,255,0.03); border-radius:10px; font-family:monospace; font-size:0.85rem; line-height:1.8; overflow-x:auto;">
                    <span style="opacity:0.5;">// Call a PHP service from JavaScript</span><br>
                    <span style="color:#c084fc;">const</span> result = <span style="color:#c084fc;">await</span> this.service(<span style="color:#a5f3fc;">'task.create'</span>, {<br>
                    &nbsp;&nbsp;taskTitle: <span style="color:#a5f3fc;">'My Task'</span>,<br>
                    &nbsp;&nbsp;taskPriority: <span style="color:#a5f3fc;">'High'</span><br>
                    });<br><br>
                    <span style="opacity:0.5;">// The PHP service is at: src/{{APP_NAME}}/serv/task_create.php</span><br>
                    <span style="opacity:0.5;">// Registered in: src/{{APP_NAME}}/etc/services.yml</span>
                </div>

                <div style="margin-top:1.5rem;">
                    <button @click="${() => this.loadItems()}"
                            style="padding:0.7rem 1.5rem; background:var(--sppux-primary, #6366f1); color:#fff; border:none; border-radius:10px; cursor:pointer; font-weight:600;">
                        ⚡ Try Service Call
                    </button>
                </div>
            </div>
        `;
    }
}
JS
            ,
            $appName
        );

        // ── counter.js: Sub-component with props ──
        $this->writeFile(
            "src/{$appName}/comp/counter.js",
            <<<'JS'
/**
 * ============================================================================
 * Counter Sub-Component — {{APP_NAME}}
 * ============================================================================
 *
 * Demonstrates:
 *   - Props: receive data from parent via data-spp-props
 *   - Local state: independent state management
 *   - Lifecycle: onInit, onMount, onDestroy, afterUpdate
 *   - Events: communicating with parent components
 *
 * HOW TO MOUNT THIS COMPONENT:
 *
 *   From SPP-UX (in another component's render()):
 *     html`<div data-spp-component="1" data-spp-type="ux"
 *               data-spp-path="${SPPUX.componentBase + '/counter.js'}"
 *               data-spp-props='{"initialCount": 5}'></div>`
 *
 *   From PHP (in a Blade template or PHP page):
 *     <?php \SPPMod\Drishyam\SPPUX::render('counter', ['initialCount' => 5]); ?>
 *
 *   From Blade:
 *     @sppux('counter', ['initialCount' => 5])
 * ============================================================================
 */
export default class Counter extends BaseComponent {

    async onInit() {
        // Props are available as this.props (set via data-spp-props or PHP)
        this.setState({
            count: this.props.initialCount || 0,
            history: []
        });
    }

    onMount() {
        // DOM is ready — you can access this.container here
        console.log('[Counter] Mounted with initial count:', this.state.count);
    }

    afterUpdate() {
        // Called after every re-render triggered by setState
        // Useful for DOM measurements, scroll restoration, etc.
    }

    onDestroy() {
        // Cleanup: clear timers, remove event listeners, etc.
        console.log('[Counter] Destroyed');
    }

    increment() {
        const newCount = this.state.count + 1;
        this.setState({
            count: newCount,
            history: [...this.state.history, { action: '+1', value: newCount, time: new Date().toLocaleTimeString() }]
        });
    }

    decrement() {
        const newCount = this.state.count - 1;
        this.setState({
            count: newCount,
            history: [...this.state.history, { action: '-1', value: newCount, time: new Date().toLocaleTimeString() }]
        });
    }

    reset() {
        this.setState({ count: 0, history: [] });
        this.notify('Counter reset!', 'info');
    }

    render() {
        return html`
            <div style="padding:1.5rem; background:rgba(255,255,255,0.03); border-radius:14px; border:1px solid rgba(255,255,255,0.06);">
                <h3 style="margin-top:0;">🧮 Counter Component</h3>
                <p style="opacity:0.5; font-size:0.85rem;">Props: initialCount=${this.props.initialCount || 0}</p>

                <div style="display:flex; align-items:center; gap:1rem; margin:1rem 0;">
                    <button @click="${() => this.decrement()}" style="width:40px;height:40px;border-radius:10px;border:1px solid rgba(255,255,255,0.1);background:rgba(239,68,68,0.15);color:#ef4444;cursor:pointer;font-size:1.2rem;">−</button>
                    <span style="font-size:2rem; font-weight:800; min-width:60px; text-align:center;">${this.state.count}</span>
                    <button @click="${() => this.increment()}" style="width:40px;height:40px;border-radius:10px;border:1px solid rgba(255,255,255,0.1);background:rgba(34,197,94,0.15);color:#22c55e;cursor:pointer;font-size:1.2rem;">+</button>
                    <button @click="${() => this.reset()}" style="padding:0.5rem 1rem;border-radius:8px;border:1px solid rgba(255,255,255,0.1);background:transparent;cursor:pointer;font-family:inherit;color:inherit;opacity:0.6;">Reset</button>
                </div>

                ${this.state.history.length > 0 ? html`
                    <details style="margin-top:1rem;">
                        <summary style="cursor:pointer; opacity:0.5; font-size:0.85rem;">History (${this.state.history.length} actions)</summary>
                        <div style="margin-top:0.5rem; font-size:0.8rem; font-family:monospace; opacity:0.5;">
                            ${this.state.history.slice(-5).map(h => html`
                                <div>${h.time}: ${h.action} → ${h.value}</div>
                            `)}
                        </div>
                    </details>
                ` : ''}
            </div>
        `;
    }
}
JS
            ,
            $appName
        );

        // ── app-store.js: Shared state store ──
        $this->writeFile(
            "src/{$appName}/comp/app-store.js",
            <<<'JS'
/**
 * ============================================================================
 * App Store — Shared State Across Components
 * ============================================================================
 *
 * SPPStore provides a simple, reactive shared state container.
 * Multiple components can subscribe to the same store and react to changes.
 *
 * HOW TO USE IN A COMPONENT:
 *
 *   import AppStore from './app-store.js';
 *
 *   class MyComponent extends BaseComponent {
 *     onInit() {
 *       // Subscribe to store changes
 *       this.unsubscribe = this.bindStore(AppStore, (state) => {
 *         this.setState({ user: state.user });
 *       });
 *     }
 *
 *     onDestroy() {
 *       // Always unsubscribe to prevent memory leaks
 *       this.unsubscribe();
 *     }
 *
 *     login(username) {
 *       // Update store — all subscribers are notified
 *       AppStore.set({ user: { name: username }, loggedIn: true });
 *     }
 *   }
 *
 * METHODS:
 *   AppStore.get()            — Get current state snapshot
 *   AppStore.set({ ... })     — Merge partial state (notifies subscribers)
 *   AppStore.subscribe(fn)    — Listen for changes (returns unsubscribe fn)
 *   AppStore.notify()         — Force notify all subscribers
 * ============================================================================
 */
const AppStore = new SPPStore({
    user: null,
    loggedIn: false,
    notifications: [],
    preferences: {
        theme: 'midnight',
        language: 'en'
    }
});

export default AppStore;
JS
            ,
            $appName
        );
    }

    // ── SPP-UX extra components for sppux mode ──

    private function writeSppuxThemePicker(string $appName): void
    {
        $this->writeFile(
            "src/{$appName}/comp/theme-picker.js",
            <<<'JS'
/**
 * Theme Picker Component
 * Demonstrates: SPPUX.Theme API, dynamic styling, CSS variable overrides
 */
export default class ThemePicker extends BaseComponent {
    async onInit() {
        this.setState({
            current: SPPUX.Theme.current || 'midnight',
            themes: ['midnight', 'emerald', 'royal', 'cyberpunk', 'ocean', 'saffron', 'day'],
            customVars: { '--sppux-primary': '#6366f1' }
        });
    }

    select(theme) {
        SPPUX.Theme.set(theme);
        this.setState({ current: theme });
        this.notify(`Theme: ${theme}`, 'success');
    }

    render() {
        return html`
            <div style="padding:1.5rem; background:var(--sppux-panel); border-radius:14px;">
                <h3 style="margin-top:0;">🎨 Theme Picker</h3>
                <div style="display:flex; flex-wrap:wrap; gap:0.6rem;">
                    ${this.state.themes.map(t => html`
                        <button @click="${() => this.select(t)}"
                                style="padding:0.6rem 1.2rem; border-radius:8px; cursor:pointer; border:2px solid ${this.state.current === t ? 'var(--sppux-primary)' : 'rgba(255,255,255,0.1)'}; background:${this.state.current === t ? 'var(--sppux-primary)' : 'transparent'}; color:${this.state.current === t ? '#fff' : 'inherit'}; font-family:inherit; text-transform:capitalize;">
                            ${t}
                        </button>
                    `)}
                </div>
                <p style="margin-top:1rem; opacity:0.5; font-size:0.85rem;">
                    <b>Custom branding:</b> Override CSS variables in your app stylesheet.
                    See <code>spp/modules/spp/drishyam/css/sppux.css</code> for all variables.
                </p>
            </div>
        `;
    }
}
JS
            ,
            $appName
        );
    }

    private function writeSppuxFormDemo(string $appName): void
    {
        $this->writeFile(
            "src/{$appName}/comp/form-demo.js",
            <<<'JS'
/**
 * Form Demo Component
 * Demonstrates: client-side form handling, validation, API submission, SPPUX.Busy
 */
export default class FormDemo extends BaseComponent {
    async onInit() {
        this.setState({
            formData: { name: '', email: '', message: '' },
            errors: {},
            submitting: false,
            submitted: false
        });
    }

    updateField(field, value) {
        const formData = { ...this.state.formData, [field]: value };
        this.setState({ formData, errors: { ...this.state.errors, [field]: '' } });
    }

    validate() {
        const errors = {};
        const { name, email, message } = this.state.formData;
        if (!name.trim()) errors.name = 'Name is required';
        if (!email.trim()) errors.email = 'Email is required';
        else if (!/\S+@\S+\.\S+/.test(email)) errors.email = 'Invalid email format';
        if (!message.trim()) errors.message = 'Message is required';
        this.setState({ errors });
        return Object.keys(errors).length === 0;
    }

    async submit() {
        if (!this.validate()) return;
        this.setState({ submitting: true });
        SPPUX.Busy.start(); // Show global loading indicator
        try {
            // Simulate API call — replace with real service call:
            // const result = await this.service('form.submit', this.state.formData);
            await new Promise(r => setTimeout(r, 1500));
            this.setState({ submitted: true, submitting: false });
            SPPUX.Busy.stop();
            this.notify('Form submitted successfully!', 'success');
        } catch (e) {
            this.setState({ submitting: false });
            SPPUX.Busy.stop();
            this.notify('Submission failed: ' + e.message, 'error');
        }
    }

    render() {
        if (this.state.submitted) {
            return html`
                <div style="padding:2rem; text-align:center; background:rgba(34,197,94,0.1); border-radius:14px;">
                    <span style="font-size:3rem;">✅</span>
                    <h3>Form Submitted!</h3>
                    <p style="opacity:0.6;">Data: ${JSON.stringify(this.state.formData)}</p>
                    <button @click="${() => this.setState({ submitted: false, formData: { name:'', email:'', message:'' } })}"
                            style="margin-top:1rem; padding:0.7rem 1.5rem; background:var(--sppux-primary); color:#fff; border:none; border-radius:10px; cursor:pointer;">
                        Submit Another
                    </button>
                </div>
            `;
        }
        const { formData, errors } = this.state;
        const inputStyle = 'width:100%;padding:0.8rem;border-radius:10px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.05);color:inherit;font-family:inherit;font-size:1rem;';
        return html`
            <div style="padding:1.5rem; background:var(--sppux-panel); border-radius:14px;">
                <h3 style="margin-top:0;">📝 Form Demo</h3>
                <p style="opacity:0.5; font-size:0.85rem;">Client-side validation + SPPUX.Busy loading indicator</p>
                <div style="margin-bottom:1rem;">
                    <label style="display:block;margin-bottom:0.3rem;font-size:0.85rem;opacity:0.6;">Name</label>
                    <input style="${inputStyle}" value="${formData.name}" @input="${(e) => this.updateField('name', e.target.value)}" placeholder="Your name">
                    ${errors.name ? html`<div style="color:#ef4444;font-size:0.8rem;margin-top:0.3rem;">${errors.name}</div>` : ''}
                </div>
                <div style="margin-bottom:1rem;">
                    <label style="display:block;margin-bottom:0.3rem;font-size:0.85rem;opacity:0.6;">Email</label>
                    <input style="${inputStyle}" value="${formData.email}" @input="${(e) => this.updateField('email', e.target.value)}" placeholder="you@example.com">
                    ${errors.email ? html`<div style="color:#ef4444;font-size:0.8rem;margin-top:0.3rem;">${errors.email}</div>` : ''}
                </div>
                <div style="margin-bottom:1rem;">
                    <label style="display:block;margin-bottom:0.3rem;font-size:0.85rem;opacity:0.6;">Message</label>
                    <textarea style="${inputStyle}" rows="3" @input="${(e) => this.updateField('message', e.target.value)}" placeholder="Your message">${formData.message}</textarea>
                    ${errors.message ? html`<div style="color:#ef4444;font-size:0.8rem;margin-top:0.3rem;">${errors.message}</div>` : ''}
                </div>
                <button @click="${() => this.submit()}"
                        style="padding:0.8rem 2rem;background:var(--sppux-primary);color:#fff;border:none;border-radius:10px;cursor:pointer;font-weight:600;opacity:${this.state.submitting ? '0.5' : '1'};"
                        ${this.state.submitting ? 'disabled' : ''}>
                    ${this.state.submitting ? 'Submitting...' : 'Submit Form'}
                </button>
            </div>
        `;
    }
}
JS
            ,
            $appName
        );
    }

    // ── Controllers ──

    private function writeHomeController(string $appName): void
    {
        $ns = $appName;
        $this->writeFile(
            "src/{$appName}/serv/HomeController.php",
            <<<PHP
<?php
namespace App\\{$ns}\\Serv;

/**
 * ============================================================================
 * HomeController — Blade View Rendering
 * ============================================================================
 *
 * HOW THIS WORKS:
 * Controllers are referenced in pages.yml as:
 *   home:
 *     controller: \\App\\{$ns}\\Serv\\HomeController@index
 *
 * When SPPRouter matches the route, ViewRouter calls this method.
 * The method should return rendered HTML (string).
 *
 * RENDERING BLADE VIEWS:
 * SPPBlade looks for templates in: src/{$ns}/resources/views/
 * Template names use dot notation: 'home' → home.blade.php
 * Layouts use @extends('layouts.app') → layouts/app.blade.php
 *
 * SPP BLADE DIRECTIVES:
 *   @sppux('component', ['prop' => 'val'])  — Mount SPP-UX component
 *   @sppform('formName')                     — Render YAML form
 *   @sppauth ... @endsppauth                — Show only if logged in
 *   @sppguest ... @endsppguest              — Show only if NOT logged in
 *   @sppbind(\$entity)                       — Bind entity to form
 *   @sppoffline('key') ... @endsppoffline   — Offline cache template
 *
 * HOW TO ADD A NEW PAGE:
 *   1. Create resources/views/mypage.blade.php
 *   2. Add method: public function mypage() { return \$this->render('mypage'); }
 *   3. Add route in pages.yml: mypage: { controller: \\App\\{$ns}\\Serv\\HomeController@mypage }
 * ============================================================================
 */
class HomeController
{
    public function index()
    {
        // Boot SPP-UX assets so Blade views can use @sppux directive
        if (class_exists('\\SPPMod\\Drishyam\\SPPUX')) {
            \\SPPMod\\Drishyam\\SPPUX::boot();
        }

        return \$this->render('home', [
            'title' => 'Welcome to {$ns}',
            'features' => [
                ['icon' => '🚀', 'title' => 'SPP-UX Components', 'desc' => 'Reactive UI with zero dependencies'],
                ['icon' => '🎨', 'title' => 'Blade Templates', 'desc' => 'Server-rendered with custom directives'],
                ['icon' => '⚡', 'title' => 'REST API', 'desc' => 'JSON endpoints with entity CRUD'],
                ['icon' => '🔒', 'title' => 'Authentication', 'desc' => 'Session guards with SPPAuth'],
            ]
        ]);
    }

    public function about()
    {
        return \$this->render('about', [
            'title' => 'About {$ns}',
        ]);
    }

    /**
     * Guide page — renders the comprehensive Blade mode tutorial.
     * Route: guide => HomeController@guide (in pages.yml)
     */
    public function guide()
    {
        return \$this->render('guide', [
            'title' => '{$ns} Developer Guide',
        ]);
    }

    /**
     * Helper: Render a Blade template with data.
     */
    protected function render(string \$view, array \$data = []): string
    {
        \$blade = \\SPPMod\\Drishyam\\SPPBlade::getInstance();
        \$data['app_name'] = '{$ns}';
        \$data['base_url'] = \\SPP\\App::getBaseUrl('{$ns}');
        return \$blade->run(\$view, \$data);
    }
}
PHP
        );
    }

    private function writeDashboardController(string $appName): void
    {
        $ns = $appName;
        $this->writeFile(
            "src/{$appName}/serv/DashboardController.php",
            <<<PHP
<?php
namespace App\\{$ns}\\Serv;

/**
 * ============================================================================
 * DashboardController — Data Display with Auth
 * ============================================================================
 *
 * Demonstrates:
 *   - Rendering Blade views with dynamic data
 *   - Authentication checking before rendering
 *   - Database entity queries (when SPPDB is available)
 *   - Passing data to Blade templates
 * ============================================================================
 */
class DashboardController
{
    public function index()
    {
        // Boot SPP-UX for @sppux directive
        if (class_exists('\\SPPMod\\Drishyam\\SPPUX')) {
            \\SPPMod\\Drishyam\\SPPUX::boot();
        }

        // Check authentication (optional — uncomment to require login)
        // if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth') && !\\SPPMod\\SPPAuth\\SPPAuth::authSessionExists()) {
        //     header('Location: ' . \\SPP\\App::getBaseUrl('{$ns}') . '/login');
        //     exit;
        // }

        // Example: Query database entities
        \$items = [];
        // Uncomment when SPPDB is configured:
        // \$db = new \\SPPMod\\SPPDB\\SPPDB();
        // \$items = \$db->execute_query('SELECT * FROM {$ns}_items ORDER BY id DESC LIMIT 10');

        \$blade = \\SPPMod\\Drishyam\\SPPBlade::getInstance();
        return \$blade->run('dashboard', [
            'app_name' => '{$ns}',
            'base_url' => \\SPP\\App::getBaseUrl('{$ns}'),
            'items' => \$items,
            'stats' => [
                'total_items' => count(\$items),
                'active' => 0,
                'completed' => 0,
            ]
        ]);
    }
}
PHP
        );
    }

    private function writeApiController(string $appName): void
    {
        $ns = $appName;
        $this->writeFile(
            "src/{$appName}/serv/ApiController.php",
            <<<PHP
<?php
namespace App\\{$ns}\\Serv;

/**
 * ============================================================================
 * ApiController — REST API Endpoints (JSON)
 * ============================================================================
 *
 * HOW THIS WORKS:
 * Controllers returning JSON should set the Content-Type header and
 * echo the JSON response. The framework does NOT auto-detect JSON.
 *
 * ROUTES (defined in pages.yml):
 *   GET  /api/v1/items      → index()     List all items
 *   GET  /api/v1/items/{id} → show(\$id)   Get single item
 *   POST /api/v1/items      → create()    Create new item
 *
 * AUTHENTICATION:
 * Use SPPAuth to validate API tokens or session cookies.
 * Set an auth validator via SPPAPI::setAuthValidator() in init.php.
 *
 * HOW TO ADD NEW ENDPOINTS:
 *   1. Add method to this class
 *   2. Add route in pages.yml:
 *      api/v1/newroute:
 *        controller: \\App\\{$ns}\\Serv\\ApiController@newMethod
 * ============================================================================
 */
class ApiController
{
    /**
     * GET /api/v1/items — List all items
     */
    public function index()
    {
        header('Content-Type: application/json');

        // Example: Query items from database
        \$items = [
            ['id' => 1, 'name' => 'Sample Item 1', 'status' => 'active', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 2, 'name' => 'Sample Item 2', 'status' => 'completed', 'created_at' => date('Y-m-d H:i:s')],
        ];

        // Uncomment for real database queries:
        // \$db = new \\SPPMod\\SPPDB\\SPPDB();
        // \$items = \$db->execute_query('SELECT * FROM {$ns}_items ORDER BY id DESC');

        echo json_encode([
            'status' => 'ok',
            'data' => \$items,
            'meta' => [
                'total' => count(\$items),
                'page' => 1,
                'per_page' => 25,
            ]
        ]);
    }

    /**
     * GET /api/v1/items/{id} — Get single item
     */
    public function show()
    {
        header('Content-Type: application/json');

        // Route parameters are available via the page data
        \$pageData = \\SPPMod\\SPPView\\SPPGlobal::get('page');
        \$id = \$pageData['params'][0] ?? null;

        if (!\$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Item ID required']);
            return;
        }

        // Example: Return mock data
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'id' => (int)\$id,
                'name' => 'Item #' . \$id,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * API Documentation endpoint
     */
    public function docs()
    {
        header('Content-Type: application/json');
        echo json_encode([
            'name' => '{$ns} API',
            'version' => 'v1',
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/v1/items', 'description' => 'List all items'],
                ['method' => 'GET', 'path' => '/api/v1/items/{id}', 'description' => 'Get single item'],
                ['method' => 'POST', 'path' => '/api/v1/items', 'description' => 'Create new item'],
            ]
        ]);
    }
}
PHP
        );
    }

    // ── Blade Templates ──

    private function writeBladeLayout(string $appName): void
    {
        $viewsDir = SPP_APP_DIR . "/src/{$appName}/resources/views/layouts";
        if (!is_dir($viewsDir))
            mkdir($viewsDir, 0777, true);

        $this->writeFile(
            "src/{$appName}/resources/views/layouts/app.blade.php",
            <<<'BLADE'
{{--
================================================================================
Base Layout — {{APP_NAME}}
================================================================================

HOW TO USE:
In any Blade view, extend this layout:
  @extends('layouts.app')
  @section('title', 'My Page Title')
  @section('content')
    <p>Your page content here</p>
  @endsection

AVAILABLE SECTIONS:
  @section('title')    — Page title (appears in <title> tag)
  @section('styles')   — Extra CSS for this page
  @section('content')  — Main page content
  @section('scripts')  — Extra JS for this page

SPP DIRECTIVES AVAILABLE:
  @sppux('compName', ['prop' => 'val'])  — Mount SPP-UX component
  @sppform('formName')                    — Render YAML-driven form
  @sppauth ... @endsppauth               — Show only if authenticated
  @sppguest ... @endsppguest             — Show only if guest
================================================================================
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '{{APP_NAME}}')</title>
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

        /* Badges */
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-primary { background: var(--primary-light); color: var(--primary); }
        .badge-success { background: rgba(34,197,94,0.1); color: #16a34a; }

        /* Buttons */
        .btn { display: inline-block; padding: 0.7rem 1.5rem; border-radius: 10px; border: none; font-weight: 600; font-family: inherit; cursor: pointer; text-decoration: none; font-size: 0.9rem; transition: all 0.2s; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.3); }
        .btn-outline { border: 1px solid var(--border); background: transparent; color: var(--text); }
    </style>
    @yield('styles')
</head>
<body>
    <nav class="nav">
        <div class="layout-container nav-inner">
            <a href="@url('')" class="nav-brand">🚀 {{APP_NAME}}</a>
            <div class="nav-links">
                <a href="@url('home')">Home</a>
                <a href="@url('about')">About</a>
                <a href="@url('dashboard')">Dashboard</a>
                <a href="@url('contact')">Contact</a>
                <a href="@url('app')">SPP-UX App</a>

                {{-- Auth-aware navigation --}}
                @sppauth
                    <a href="@url('auth/logout')" style="color: #ef4444;">Logout</a>
                @endsppauth
                @sppguest
                    <a href="@url('login')">Login</a>
                @endsppguest
            </div>
        </div>
    </nav>

    <main class="main">
        <div class="layout-container">
            @yield('content')
        </div>
    </main>

    <footer class="footer">
        <div class="layout-container">
            &copy; {{ date('Y') }} {{APP_NAME}} &bull; Built with SPP Framework
        </div>
    </footer>

    @yield('scripts')
</body>
</html>
BLADE
            ,
            $appName
        );
    }

    private function writeBladeHome(string $appName): void
    {
        $this->writeFile(
            "src/{$appName}/resources/views/home.blade.php",
            <<<'BLADE'
{{--
  Home Page — Demonstrates Blade + SPP-UX + YAML Forms + Auth Directives
  Edit this file: src/{{APP_NAME}}/resources/views/home.blade.php
--}}
@extends('layouts.app')

@section('title', $title ?? 'Home')

@section('content')
    <div class="card">
        <span class="badge badge-primary">BLADE + SPP-UX + FORMS</span>
        <h1>{{ $title ?? 'Welcome' }}</h1>
        <p>This page is rendered by <code>HomeController@index</code> using a <b>Blade template</b>.
        It demonstrates how Blade, SPP-UX components, and YAML forms work together.</p>
    </div>

    {{-- Feature cards --}}
    @if(!empty($features))
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem;">
        @foreach($features as $feature)
        <div class="card" style="text-align: center;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">{{ $feature['icon'] }}</div>
            <h3>{{ $feature['title'] }}</h3>
            <p>{{ $feature['desc'] }}</p>
        </div>
        @endforeach
    </div>
    @endif

    {{-- SPP-UX Component Mount (embedded in Blade) --}}
    <div class="card">
        <h2>🧩 SPP-UX Component in Blade</h2>
        <p>The counter below is an SPP-UX component mounted via the <code>@sppux</code> Blade directive:</p>
        <div style="margin-top: 1rem;">
            @sppux('counter', ['initialCount' => 42])
        </div>
    </div>

    {{-- YAML Form --}}
    <div class="card">
        <h2>📝 YAML-Driven Form</h2>
        <p>This form is defined in <code>etc/apps/{{APP_NAME}}/forms/contact.yml</code> and rendered via <code>@sppform</code>:</p>
        @sppform('contact')
    </div>

    {{-- Auth-gated content --}}
    <div class="card">
        <h2>🔒 Auth-Gated Content</h2>
        @sppauth
            <div class="badge badge-success">AUTHENTICATED</div>
            <p style="margin-top: 0.5rem;">You are logged in. This content is only visible to authenticated users.</p>
            <p>Use <code>@sppauth</code> and <code>@endsppauth</code> in Blade templates.</p>
        @endsppauth
        @sppguest
            <p>You are viewing as a guest. <a href="{{ $base_url }}/login" class="btn btn-outline" style="margin-left: 0.5rem;">Login</a></p>
            <p style="margin-top: 0.5rem;">Use <code>@sppguest</code> and <code>@endsppguest</code> to show guest-only content.</p>
        @endsppguest
    </div>
@endsection
BLADE
            ,
            $appName
        );
    }

    private function writeBladeAbout(string $appName): void
    {
        $this->writeFile(
            "src/{$appName}/resources/views/about.blade.php",
            <<<'BLADE'
@extends('layouts.app')
@section('title', $title ?? 'About')
@section('content')
    <div class="card">
        <span class="badge badge-primary">FRAMEWORK GUIDE</span>
        <h1>{{ $title ?? 'About' }} — Architecture Guide</h1>
        <p>This page explains how the SPP framework is structured and how each part works.</p>
    </div>

    <div class="card">
        <h2>📂 Directory Structure</h2>
        <table style="width:100%; border-collapse:collapse;">
            <thead><tr style="text-align:left; border-bottom:2px solid var(--border);">
                <th style="padding:0.8rem;">Directory</th><th style="padding:0.8rem;">Purpose</th><th style="padding:0.8rem;">Modify When</th>
            </tr></thead>
            <tbody>
                <tr style="border-bottom:1px solid var(--border);"><td style="padding:0.8rem;"><code>comp/</code></td><td style="padding:0.8rem;">SPP-UX components (JavaScript)</td><td style="padding:0.8rem;">Building reactive UI</td></tr>
                <tr style="border-bottom:1px solid var(--border);"><td style="padding:0.8rem;"><code>pages/</code></td><td style="padding:0.8rem;">Native PHP pages (augmented)</td><td style="padding:0.8rem;">Simple server-rendered pages</td></tr>
                <tr style="border-bottom:1px solid var(--border);"><td style="padding:0.8rem;"><code>serv/</code></td><td style="padding:0.8rem;">Controllers & services</td><td style="padding:0.8rem;">Business logic, API endpoints</td></tr>
                <tr style="border-bottom:1px solid var(--border);"><td style="padding:0.8rem;"><code>resources/views/</code></td><td style="padding:0.8rem;">Blade templates</td><td style="padding:0.8rem;">Server-rendered HTML with directives</td></tr>
                <tr style="border-bottom:1px solid var(--border);"><td style="padding:0.8rem;"><code>entities/</code></td><td style="padding:0.8rem;">Database entity definitions</td><td style="padding:0.8rem;">Data models with ORM</td></tr>
                <tr style="border-bottom:1px solid var(--border);"><td style="padding:0.8rem;"><code>events/</code></td><td style="padding:0.8rem;">Event handlers</td><td style="padding:0.8rem;">Reacting to framework events</td></tr>
                <tr style="border-bottom:1px solid var(--border);"><td style="padding:0.8rem;"><code>middleware/</code></td><td style="padding:0.8rem;">Route middleware</td><td style="padding:0.8rem;">Auth checks, rate limiting</td></tr>
                <tr><td style="padding:0.8rem;"><code>etc/</code></td><td style="padding:0.8rem;">App config files</td><td style="padding:0.8rem;">Routes, services, forms, settings</td></tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>🔄 Request Lifecycle</h2>
        <ol style="line-height:2.2; color:var(--muted);">
            <li><b>Request arrives</b> → Apache routes to SPP via <code>.htaccess</code></li>
            <li><b>sppinit.php</b> → Boots modules, sets app context via <code>Scheduler::setContext()</code></li>
            <li><b>SPPRouter</b> → Loads <code>pages.yml</code>, resolves route to page/controller</li>
            <li><b>ViewRouter</b> → Dispatches: includes page file OR calls controller method</li>
            <li><b>Augmentation</b> → Injects JS/CSS from <code>ViewPage</code>, applies theme, fires events</li>
            <li><b>Response</b> → Final HTML sent to browser</li>
        </ol>
    </div>

    <div class="card">
        <h2>🌍 Polyglot Architecture</h2>
        <p>SPP supports multiple rendering paradigms in the same app:</p>
        <ul style="line-height:2; color:var(--muted);">
            <li><b>Blade Templates:</b> <code>@extends</code>, <code>@section</code>, <code>@sppux</code>, <code>@sppform</code></li>
            <li><b>Twig Templates:</b> <code>{{ "{{ var }}" }}</code>, <code>{{ "{% block %}" }}</code> — via <code>SPPTwig</code></li>
            <li><b>Native PHP:</b> Direct PHP output with <code>ViewPage</code> augmentation</li>
            <li><b>SPP-UX:</b> Reactive components with <code>BaseComponent</code>, <code>html``</code>, <code>setState</code></li>
            <li><b>REST API:</b> Controllers returning JSON with <code>header('Content-Type: application/json')</code></li>
        </ul>
    </div>
@endsection
BLADE
            ,
            $appName
        );
    }

    private function writeBladeDashboard(string $appName): void
    {
        $this->writeFile(
            "src/{$appName}/resources/views/dashboard.blade.php",
            <<<'BLADE'
@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
    <div class="card">
        <h1>📊 Dashboard</h1>
        <p>Authenticated view with data display. Modify <code>DashboardController@index</code> to fetch real data.</p>
    </div>

    {{-- Stats --}}
    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:1rem; margin-bottom:1.5rem;">
        <div class="card" style="text-align:center;">
            <div style="font-size:2rem; font-weight:800; color:var(--primary);">{{ $stats['total_items'] ?? 0 }}</div>
            <p>Total Items</p>
        </div>
        <div class="card" style="text-align:center;">
            <div style="font-size:2rem; font-weight:800; color:#16a34a;">{{ $stats['active'] ?? 0 }}</div>
            <p>Active</p>
        </div>
        <div class="card" style="text-align:center;">
            <div style="font-size:2rem; font-weight:800; color:#64748b;">{{ $stats['completed'] ?? 0 }}</div>
            <p>Completed</p>
        </div>
    </div>

    {{-- Data table --}}
    <div class="card">
        <h2>📋 Items</h2>
        @if(!empty($items))
            <table style="width:100%; border-collapse:collapse; margin-top:1rem;">
                <thead><tr style="border-bottom:2px solid var(--border); text-align:left;">
                    <th style="padding:0.8rem;">ID</th><th style="padding:0.8rem;">Name</th><th style="padding:0.8rem;">Status</th>
                </tr></thead>
                <tbody>
                @foreach($items as $item)
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:0.8rem;">{{ $item['id'] ?? '-' }}</td>
                        <td style="padding:0.8rem;">{{ $item['name'] ?? '-' }}</td>
                        <td style="padding:0.8rem;"><span class="badge badge-success">{{ $item['status'] ?? '-' }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p style="margin-top:1rem; opacity:0.5;">No items yet. Configure SPPDB and create entities to see data here.</p>
        @endif
    </div>

    {{-- SPP-UX interactive widget --}}
    <div class="card">
        <h2>🧩 Interactive SPP-UX Widget</h2>
        @sppux('counter', ['initialCount' => 0])
    </div>
@endsection
BLADE
            ,
            $appName
        );
    }

    // ── Native PHP Pages ──

    private function writeNativeIndexPage(string $appName): void
    {
        $this->writeFile(
            "src/{$appName}/pages/index.php",
            <<<'PHP'
<?php
/**
 * ============================================================================
 * Native PHP Landing Page — {{APP_NAME}}
 * ============================================================================
 *
 * HOW THIS WORKS:
 * This file is included by the SPP ViewRouter as a normal page (special: 0).
 * The augmentation pipeline automatically:
 *   - Injects registered JS/CSS files
 *   - Processes YAML forms (if spp_form_id is posted)
 *   - Applies the active theme
 *   - Fires rendering events
 *
 * IMPORTANT:
 *   - Do NOT output <!DOCTYPE> or <html> tags — the augmentation adds them
 *   - Just output the page BODY content
 *   - Use ViewPage to register JS/CSS that should be in <head>
 *
 * HOW TO ADD JS/CSS:
 *   \SPPMod\SPPView\ViewPage::addJsIncludeFile('assets/js/app.js');
 *   \SPPMod\SPPView\ViewPage::addCssIncludeFile('assets/css/app.css');
 *
 * HOW TO MOUNT SPP-UX COMPONENTS:
 *   \SPPMod\Drishyam\SPPUX::boot();   // Register runtime assets
 *   \SPPMod\Drishyam\SPPUX::render('counter', ['initialCount' => 5]);
 * ============================================================================
 */

// Register SPP-UX assets for this page
if (class_exists('\SPPMod\Drishyam\SPPUX')) {
    \SPPMod\Drishyam\SPPUX::boot();
}
?>

<div style="max-width: 800px; margin: 2rem auto; padding: 0 1rem;">
    <div style="background: #fff; border-radius: 16px; padding: 2.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #e2e8f0;">
        <span style="display:inline-block; background:#e0e7ff; color:#4f46e5; padding:4px 12px; border-radius:20px; font-size:0.75rem; font-weight:700; margin-bottom:1rem;">NATIVE PHP PAGE</span>
        <h1 style="font-size: 2rem; margin: 0 0 0.5rem;">{{APP_NAME}}</h1>
        <p style="color: #64748b; line-height: 1.7;">
            This is a <b>native PHP page</b> rendered through the ViewRouter augmentation pipeline.
            Unlike <code>special: 1</code> pages, it gets automatic JS/CSS injection, form processing, and theme support.
        </p>

        <h3 style="margin-top: 2rem; color: #6366f1;">🧩 SPP-UX Component (mounted via PHP)</h3>
        <p style="color: #64748b; font-size: 0.9rem;">
            Code: <code>&lt;?php \SPPMod\Drishyam\SPPUX::render('counter', ['initialCount' => 10]); ?&gt;</code>
        </p>
        <?php
        if (class_exists('\SPPMod\Drishyam\SPPUX')) {
            \SPPMod\Drishyam\SPPUX::render('counter', ['initialCount' => 10]);
        }
        ?>
    </div>
</div>
PHP
            ,
            $appName
        );
    }

    private function writeNativeContactPage(string $appName): void
    {
        $this->writeFile(
            "src/{$appName}/pages/contact.php",
            <<<'PHP'
<?php
/**
 * Contact Page — Demonstrates YAML-driven forms in native PHP
 * The form is defined in etc/apps/{{APP_NAME}}/forms/contact.yml
 */
if (class_exists('\SPPMod\Drishyam\SPPUX')) {
    \SPPMod\Drishyam\SPPUX::boot();
}
?>
<div style="max-width: 700px; margin: 2rem auto; padding: 0 1rem;">
    <div style="background: #fff; border-radius: 16px; padding: 2.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #e2e8f0;">
        <span style="display:inline-block; background:#e0e7ff; color:#4f46e5; padding:4px 12px; border-radius:20px; font-size:0.75rem; font-weight:700; margin-bottom:1rem;">YAML FORM</span>
        <h1 style="margin: 0 0 0.5rem;">Contact Us</h1>
        <p style="color: #64748b;">This form is powered by the SPP YAML form engine. Definition: <code>etc/apps/{{APP_NAME}}/forms/contact.yml</code></p>

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
PHP
            ,
            $appName
        );
    }

    private function writeNativeGuidePage(string $appName): void
    {
        $this->writeFile(
            "src/{$appName}/pages/guide.php",
            <<<'GUIDEPHP'
<?php
/**
 * ============================================================================
 * SPP Framework — COMPREHENSIVE Developer Guide
 * ============================================================================
 *
 * This page is a COMPLETE tutorial for someone who has never used SPP before.
 * It covers every major feature with code examples and explanations.
 *
 * Generated for application: {{APP_NAME}}
 *
 * HOW THIS PAGE WORKS:
 *   - This is a "native PHP page" rendered by the SPP view layer
 *   - It is mapped in etc/apps/{{APP_NAME}}/pages.yml as:
 *       guide:
 *         url: pages/guide.php
 *   - SPP automatically injects theme CSS/JS around it (augmentation)
 *   - You can use PHP code at the top, then output HTML below
 *
 * TIP: You can delete this file once you understand the framework!
 * ============================================================================
 */

// Boot SPP-UX if available (allows mounting components on this page)
if (class_exists('\SPPMod\Drishyam\SPPUX')) {
    \SPPMod\Drishyam\SPPUX::boot();
}
?>
<div style="max-width: 1100px; margin: 2rem auto; padding: 0 1rem; font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
    <div style="background: #fff; border-radius: 16px; padding: 2.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #e2e8f0;">

        <span style="display:inline-block; background:#e0e7ff; color:#4f46e5; padding:4px 14px; border-radius:20px; font-size:0.75rem; font-weight:700; margin-bottom:1rem; letter-spacing:0.05em;">COMPREHENSIVE DEVELOPER GUIDE</span>
        <h1 style="margin: 0 0 0.5rem; font-size: 2rem; color: #1e293b;">SPP Framework — Complete Guide</h1>
        <p style="color: #94a3b8; font-size: 0.95rem; margin-bottom: 2.5rem;">Application: <code style="background:#f1f5f9;padding:2px 8px;border-radius:6px;">{{APP_NAME}}</code> &mdash; Everything a complete novice needs to build any workflow</p>

        <!-- ================================================================ -->
        <!-- SECTION 1: What is SPP? -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">1. What is SPP?</h2>
        <p style="color:#475569; line-height:1.8;">
            <b>SPP (Scalable PHP Platform)</b> is a full-stack PHP framework designed for building everything from simple websites to enterprise applications.
            It provides routing, templating (Blade), reactive components (SPP-UX), database ORM, authentication, testing, i18n, and much more &mdash; all in one cohesive package.
        </p>
        <p style="color:#475569; line-height:1.8;">
            <b>Key Philosophy:</b> Convention over configuration. SPP uses YAML files for forms, routes, events, and services &mdash; minimizing boilerplate PHP code.
            You write a YAML definition, and the framework auto-processes it.
        </p>
        <p style="color:#475569; line-height:1.8;">
            <b>Six App Modes:</b> <code>mixed</code> (flagship, all paradigms), <code>sppux</code> (reactive SPA), <code>blade</code> (server-rendered templates),
            <code>native</code> (raw PHP), <code>api</code> (headless REST), <code>dropin</code> (low-code HTML/PHP). Your app is: <code>{{APP_NAME}}</code>.
        </p>

        <!-- ================================================================ -->
        <!-- SECTION 2: App Structure -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">2. App Structure</h2>
        <p style="color:#475569; line-height:1.8;">Every SPP app has two main directories: <code>etc/apps/{{APP_NAME}}/</code> for configuration, and <code>src/{{APP_NAME}}/</code> for source code.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
your-project/
├── etc/apps/{{APP_NAME}}/         # ── CONFIGURATION ──
│   ├── pages.yml                  # Route definitions (URL → controller/page)
│   ├── forms/                     # YAML form definitions
│   │   └── contact.yml            # Contact form fields + validation
│   ├── events.yml                 # Event listener registrations
│   ├── services.yml               # PHP↔JS bridge service definitions
│   ├── middleware.yml              # Middleware pipeline configuration
│   └── modules.yml                # Module loading configuration
│
├── src/{{APP_NAME}}/              # ── SOURCE CODE ──
│   ├── init.php                   # App bootstrap (runs on every request)
│   ├── index.php                  # SPP-UX SPA entry point
│   ├── pages/                     # Native PHP page files
│   │   ├── contact.php            # Contact form page
│   │   ├── guide.php              # This guide page
│   │   └── errors/                # Custom error pages
│   │       ├── 404.php            # Page Not Found
│   │       └── 500.php            # Internal Server Error
│   ├── serv/                      # Controllers (Blade or JSON)
│   │   ├── HomeController.php     # Home + About pages
│   │   ├── DashboardController.php
│   │   ├── ApiController.php      # REST API endpoints
│   │   └── AuthController.php     # Login/Logout
│   ├── comp/                      # SPP-UX components (JS)
│   │   ├── hello-world.js         # Sample reactive component
│   │   └── task-manager.js        # CRUD component example
│   ├── entities/                  # Database entity definitions
│   ├── middleware/                 # Custom middleware classes
│   ├── events/                    # Event handler classes
│   ├── etc/                       # App-level config overrides
│   └── tests/                     # Parikshak test files
│
├── resources/{{APP_NAME}}/
│   └── views/                     # Blade template files (.blade.php)
│       ├── layout.blade.php       # Master layout
│       ├── home.blade.php         # Home view
│       └── dashboard.blade.php    # Dashboard view
│
└── spp/                           # Framework core (do not modify)
    ├── spp.php                    # CLI entry point
    └── etc/global-settings.yml    # All apps registered here</pre>

        <!-- ================================================================ -->
        <!-- SECTION 3: Routing -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">3. Routing</h2>
        <p style="color:#475569; line-height:1.8;">Routes are defined in <code>etc/apps/{{APP_NAME}}/pages.yml</code>. SPP supports five route types:</p>

        <h3 style="color:#475569; margin-top:1.5rem;">3a. pages.yml Route Types</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
# ── etc/apps/{{APP_NAME}}/pages.yml ──

defaults:
  home: home                              # Default page when no route matches
  pagedir: /src/{{APP_NAME}}              # Base directory for page files

pages:
  # TYPE 1: Controller route — calls a PHP class method
  home:
    controller: \App\{{APP_NAME}}\Serv\HomeController@index

  # TYPE 2: Page file route — includes a PHP file (with theme augmentation)
  contact:
    url: pages/contact.php

  # TYPE 3: Special route — standalone HTML (NO theme augmentation)
  app:
    url: index.php
    special: 1

  # TYPE 4: Asset route — serves static files from a directory
  assets:
    assets: assets

  # TYPE 5: Parameterized route — dynamic URL segments
  api/v1/items/{id}:
    controller: \App\{{APP_NAME}}\Serv\ApiController@show</pre>

        <h3 style="color:#475569; margin-top:1.5rem;">3b. PHP 8 Attribute Routing</h3>
        <p style="color:#475569; line-height:1.8;">Instead of YAML, you can use PHP 8 Attributes directly on controller methods. The <code>RouteScanner</code> discovers them automatically.</p>

        <table style="width:100%; border-collapse:collapse; margin:1rem 0; font-size:0.82rem;">
            <tr style="background:#6366f1; color:#fff;"><th style="text-align:left; padding:0.7rem;">Attribute</th><th style="text-align:left; padding:0.7rem;">Target</th><th style="text-align:left; padding:0.7rem;">Description</th></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>#[Route('/path', method, name, middleware)]</code></td><td style="padding:0.7rem;">Class / Method</td><td style="padding:0.7rem;">Define URL. Class-level = prefix for all methods.</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>#[Middleware(Class::class)]</code></td><td style="padding:0.7rem;">Class / Method</td><td style="padding:0.7rem;">Attach middleware. Repeatable. Stacks with parent.</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>#[Title('Page Title')]</code></td><td style="padding:0.7rem;">Class</td><td style="padding:0.7rem;">Sets &lt;title&gt; tag for the page.</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>#[Lazy(action: 'load')]</code></td><td style="padding:0.7rem;">Class</td><td style="padding:0.7rem;">Deferred initialization — load only when needed.</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>#[Isolate]</code></td><td style="padding:0.7rem;">Class</td><td style="padding:0.7rem;">Isolated context (no shared state with other controllers).</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>#[DataProvider('method')]</code></td><td style="padding:0.7rem;">Method</td><td style="padding:0.7rem;">Data-driven Parikshak tests.</td></tr>
        </table>

        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
use SPPMod\SPPView\Attributes\Route;
use SPPMod\SPPView\Attributes\Middleware;

#[Route('/admin')]                 // prefix for all methods in this class
#[Middleware(AuthGuard::class)]    // applied to ALL methods
class AdminController
{
    #[Route('/dashboard', method: 'GET', name: 'admin.dashboard')]
    public function dashboard() { /* handles /admin/dashboard */ }

    #[Route('/users/{id}', method: 'GET')]
    public function showUser() { /* handles /admin/users/42 */ }

    #[Route('/settings', method: 'POST')]
    #[Middleware(CsrfGuard::class)]  // method-level stacks with class-level
    public function saveSettings() { /* handles POST /admin/settings */ }
}

// Discovery paths: controllers/, src/Controllers/
// Cache file: var/cache/routes_{{APP_NAME}}.php</pre>

        <!-- ================================================================ -->
        <!-- SECTION 4: Controllers -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">4. Controllers</h2>
        <p style="color:#475569; line-height:1.8;">Controllers live in <code>src/{{APP_NAME}}/serv/</code>. They handle requests and return either rendered Blade HTML or JSON data.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// ── src/{{APP_NAME}}/serv/HomeController.php ──

namespace App\{{APP_NAME}}\Serv;

class HomeController
{
    // RENDERING A BLADE VIEW:
    // SPPBlade::getInstance() returns the Blade template engine.
    // run('viewName', $data) renders resources/{{APP_NAME}}/views/viewName.blade.php
    public function index()
    {
        $blade = \SPPMod\Drishyam\SPPBlade::getInstance();
        return $blade-&gt;run('home', [
            'title'   =&gt; 'Welcome to {{APP_NAME}}',
            'message' =&gt; 'Hello from your controller!',
        ]);
    }

    // RETURNING JSON (for API endpoints):
    public function apiEndpoint()
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' =&gt; 'success',
            'data'   =&gt; ['item1', 'item2'],
        ]);
    }

    // REDIRECTING:
    public function saveAndRedirect()
    {
        // ... save data ...
        header('Location: /{{APP_NAME}}/home');
        exit;
    }
}</pre>

        <!-- ================================================================ -->
        <!-- SECTION 5: Blade Templates -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">5. Blade Templates</h2>
        <p style="color:#475569; line-height:1.8;">Blade is the template engine (from Laravel, extended by SPP). Templates live in <code>resources/{{APP_NAME}}/views/</code>.</p>

        <h3 style="color:#475569; margin-top:1.5rem;">5a. Layout System</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
{{-- ── resources/{{APP_NAME}}/views/layout.blade.php ── --}}
&lt;!DOCTYPE html&gt;
&lt;html&gt;
&lt;head&gt;
    &lt;title&gt;@yield('title', 'My App')&lt;/title&gt;  {{-- Default title if not set --}}
&lt;/head&gt;
&lt;body&gt;
    @yield('content')    {{-- Child views inject content here --}}
&lt;/body&gt;
&lt;/html&gt;

{{-- ── resources/{{APP_NAME}}/views/home.blade.php ── --}}
@extends('layout')               {{-- Use layout.blade.php as parent --}}

@section('title', 'Home Page')   {{-- Override the title --}}

@section('content')               {{-- Fill the content slot --}}
    &lt;h1&gt;Welcome, {{ $name }}&lt;/h1&gt;  {{-- Echo escaped variable --}}
    {!! $rawHtml !!}               {{-- Echo RAW HTML (be careful!) --}}
@endsection</pre>

        <h3 style="color:#475569; margin-top:1.5rem;">5b. SPP Custom Blade Directives</h3>
        <p style="color:#475569; line-height:1.8;">SPP extends Blade with powerful custom directives. Here is the <b>complete list</b>:</p>

        <table style="width:100%; border-collapse:collapse; margin:1rem 0; font-size:0.82rem;">
            <tr style="background:#6366f1; color:#fff;"><th style="text-align:left; padding:0.7rem;">Directive</th><th style="text-align:left; padding:0.7rem;">Purpose</th><th style="text-align:left; padding:0.7rem;">Example</th></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@sppux('component', [...])</code></td><td style="padding:0.7rem;">Mount an SPP-UX reactive component</td><td style="padding:0.7rem;"><code>@sppux('task-manager', ['user' =&gt; $id])</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>@sppform('form_name')</code></td><td style="padding:0.7rem;">Render a complete YAML-defined form</td><td style="padding:0.7rem;"><code>@sppform('contact')</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@sppform_start('form')</code> / <code>@sppform_end</code></td><td style="padding:0.7rem;">Manual form rendering (custom layout)</td><td style="padding:0.7rem;"><code>@sppform_start('contact') ... @sppform_end</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>@sppelement('element')</code></td><td style="padding:0.7rem;">Render a reusable UI element</td><td style="padding:0.7rem;"><code>@sppelement('navbar')</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@sppauth</code> / <code>@endsppauth</code></td><td style="padding:0.7rem;">Show content only to logged-in users</td><td style="padding:0.7rem;"><code>@sppauth &lt;p&gt;Welcome back!&lt;/p&gt; @endsppauth</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>@sppguest</code> / <code>@endsppguest</code></td><td style="padding:0.7rem;">Show content only to guests (not logged in)</td><td style="padding:0.7rem;"><code>@sppguest &lt;a href="/login"&gt;Login&lt;/a&gt; @endsppguest</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@sppbind('var')</code></td><td style="padding:0.7rem;">Two-way reactive data binding</td><td style="padding:0.7rem;"><code>&lt;input @sppbind('username')&gt;</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>@react('Component')</code></td><td style="padding:0.7rem;">Mount a React component</td><td style="padding:0.7rem;"><code>@react('MyReactApp')</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@vue('Component')</code></td><td style="padding:0.7rem;">Mount a Vue component</td><td style="padding:0.7rem;"><code>@vue('MyVueWidget')</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>@sppoffline</code> / <code>@endsppoffline</code></td><td style="padding:0.7rem;">Offline-capable content (service worker)</td><td style="padding:0.7rem;"><code>@sppoffline Cached content @endsppoffline</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@module_component('mod', 'comp')</code></td><td style="padding:0.7rem;">Render a component from a module</td><td style="padding:0.7rem;"><code>@module_component('sppauth', 'login-form')</code></td></tr>
        </table>

        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
{{-- EXAMPLE: Using multiple directives in one view --}}
@extends('layout')

@section('content')
    @sppauth
        &lt;h1&gt;Welcome back, {{ $user-&gt;name }}!&lt;/h1&gt;
        @sppux('task-manager', ['userId' =&gt; $user-&gt;id])
        @sppform('feedback')
    @endsppauth

    @sppguest
        &lt;h1&gt;Please log in&lt;/h1&gt;
        @module_component('sppauth', 'login-form')
    @endsppguest

    @sppoffline
        &lt;p&gt;You appear to be offline. Cached content is shown.&lt;/p&gt;
    @endsppoffline
@endsection</pre>

        <!-- ================================================================ -->
        <!-- SECTION 6: SPP-UX Components -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">6. SPP-UX Components</h2>
        <p style="color:#475569; line-height:1.8;">SPP-UX is the reactive component system. Components are JavaScript classes in <code>src/{{APP_NAME}}/comp/</code>.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// ── comp/my-counter.js ──
export default class MyCounter extends BaseComponent {
    // LIFECYCLE: Called once when component initializes
    async onInit() {
        this.setState({ count: 0, label: 'Click me' });
    }

    // LIFECYCLE: Called after DOM is ready
    async onMount() {
        console.log('Counter mounted!');
    }

    // LIFECYCLE: Called before component is removed
    async onDestroy() {
        console.log('Cleanup here');
    }

    // RENDER: Return HTML template using tagged template literals
    render() {
        return html`
            &lt;button @click="${() =&gt; this.increment()}"
                    style="padding:1rem 2rem; background:#6366f1; color:#fff;
                           border:none; border-radius:10px; cursor:pointer;"&gt;
                ${this.state.label}: ${this.state.count}
            &lt;/button&gt;`;
    }

    increment() {
        this.setState({ count: this.state.count + 1 });
    }
}

// ── MOUNTING COMPONENTS (3 ways) ──
// 1. In PHP:   \SPPMod\Drishyam\SPPUX::render('my-counter')
// 2. In Blade: @sppux('my-counter', ['label' =&gt; 'Clicks'])
// 3. In HTML:  &lt;div data-spp-component="my-counter"&gt;&lt;/div&gt;</pre>

        <h3 style="color:#475569; margin-top:1.5rem;">Stores (Shared State)</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// Stores allow multiple components to share state
const store = this.getStore('appStore');
store.set('user', { name: 'John' });
const user = store.get('user');

// Subscribe to changes:
store.subscribe('user', (newVal) =&gt; {
    console.log('User changed:', newVal);
});</pre>

        <h3 style="color:#475569; margin-top:1.5rem;">Theme Picker Component</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// Built-in theme picker component for switching themes
// Mount via: @sppux('theme-picker') or SPPUX::render('theme-picker')
// Themes defined in resources/{{APP_NAME}}/themes/ with theme.yml</pre>

        <!-- ================================================================ -->
        <!-- SECTION 7: Events System -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">7. Events System</h2>
        <p style="color:#475569; line-height:1.8;">Events decouple your code. Register listeners in <code>init.php</code> or <code>etc/events.yml</code>, then fire them anywhere.</p>

        <h3 style="color:#475569; margin-top:1.5rem;">7a. Registering &amp; Firing Events</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// ── In init.php (imperative) ──
// Listen to an existing event:
\SPP\SPPEvent::listen('PageNotFound', [new \App\{{APP_NAME}}\Events\ErrorHandler(), 'onNotFound']);
\SPP\SPPEvent::listen('AppBoot', function($params) {
    // Run code when app boots
});

// Fire a custom event:
\SPP\SPPEvent::fireEvent('app.itemCreated', new \SPP\EventParams([
    'item_id' =&gt; 42,
    'user'    =&gt; $currentUser,
]));

// ── In etc/events.yml (declarative) ──
events:
  - event: "AppBoot"
    handler: "App\{{APP_NAME}}\Events\AppBootHandler::onBoot"
  - event: "PageNotFound"
    handler: "App\{{APP_NAME}}\Events\ErrorHandler::onNotFound"
  - event: "core.error.exception"
    handler: "App\{{APP_NAME}}\Events\ErrorHandler::onException"</pre>

        <h3 style="color:#475569; margin-top:1.5rem;">7b. All Framework Events</h3>
        <table style="width:100%; border-collapse:collapse; margin:1rem 0; font-size:0.82rem;">
            <tr style="background:#6366f1; color:#fff;"><th style="text-align:left; padding:0.6rem;">Event Name</th><th style="text-align:left; padding:0.6rem;">When It Fires</th></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"><code>AppBoot</code></td><td style="padding:0.6rem;">Application starts up</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"><code>BeforeRender / AfterRender</code></td><td style="padding:0.6rem;">Page render lifecycle</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"><code>PageNotFound</code></td><td style="padding:0.6rem;">404 — no matching route found</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"><code>core.error.exception</code></td><td style="padding:0.6rem;">Uncaught exception occurs</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"><code>AuthLogin / AuthLogout</code></td><td style="padding:0.6rem;">User login / logout</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"><code>EntityCreated / Updated / Deleted</code></td><td style="padding:0.6rem;">Database entity changes</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"><code>event_spp_view_render_theme</code></td><td style="padding:0.6rem;">Before theme rendering (inject HTML)</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"><code>event_spp_page_render</code></td><td style="padding:0.6rem;">During page rendering</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"><code>parikshak.suite_started / completed</code></td><td style="padding:0.6rem;">Test suite lifecycle</td></tr>
        </table>

        <!-- ================================================================ -->
        <!-- SECTION 8: Services (PHP↔JS Bridge) -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">8. Services (PHP&harr;JS Bridge)</h2>
        <p style="color:#475569; line-height:1.8;">Services let JavaScript call PHP functions without writing API endpoints. Define in <code>etc/services.yml</code>.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
# ── etc/apps/{{APP_NAME}}/services.yml ──
services:
  - name: "task.create"
    script: "src/{{APP_NAME}}/serv/task_create.php"
  - name: "task.list"
    script: "src/{{APP_NAME}}/serv/task_list.php"

// ── JavaScript (frontend) ──
const result = await spp_admin.callAppService('task.create', {
    title: 'New Task',
    priority: 'high'
});
console.log(result); // PHP response

// ── PHP (src/{{APP_NAME}}/serv/task_create.php) ──
$input = json_decode(file_get_contents('php://input'), true);
$title = $input['title'] ?? '';
// ... process and return JSON ...
echo json_encode(['status' =&gt; 'created', 'id' =&gt; 1]);</pre>

        <!-- ================================================================ -->
        <!-- SECTION 9: Error Handling -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">9. Error Handling</h2>
        <p style="color:#475569; line-height:1.8;">SPP has a layered error handling system that works differently in debug vs production mode.</p>

        <h3 style="color:#475569; margin-top:1.5rem;">9a. How It Works</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// SPP Error Handling Architecture:
//
// 1. SPPErrorHandler::register() — Called in bootstrap, sets up error/exception handlers
// 2. SPPError::exceptionHandler() — Catches all uncaught exceptions
// 3. SPPError::setCustomErrorHandler() — Override the default error display
//
// DEBUG MODE (SPP_DEBUG = true):
//   → Shows Ignition-style error pages with stack traces, code snippets,
//     and debugging info (like Laravel's Whoops page)
//
// PRODUCTION MODE (SPP_DEBUG = false):
//   → Shows user-friendly error pages (your custom 404.php / 500.php)
//   → Logs errors to var/log/
//
// API ERRORS:
//   → When URL starts with /api/, errors auto-return JSON:
//     {"error": "Not Found", "status": 404}

// ── Custom error handlers ──
SPPError::setCustomErrorHandler(function($error) {
    // Handle errors your way
    log_error($error);
    include 'path/to/custom-error.php';
});

// ── Triggering errors manually ──
SPPError::triggerUserError('Something went wrong');   // User-facing
SPPError::triggerDevError('Debug info here');          // Developer-facing
SPPError::triggerAdminError('Admin notification');     // Admin-facing</pre>

        <h3 style="color:#475569; margin-top:1.5rem;">9b. Custom 404 &amp; 500 Pages</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// In init.php, redirect to your custom error pages:
\SPP\SPPEvent::listen('PageNotFound', function($params) {
    include __DIR__ . '/pages/errors/404.php';
    exit;
});

\SPP\SPPEvent::listen('core.error.exception', function($params) {
    if (!defined('SPP_DEBUG') || !SPP_DEBUG) {
        include __DIR__ . '/pages/errors/500.php';
        exit;
    }
});

// Try/catch in controllers:
try {
    $result = riskyOperation();
} catch (\Exception $e) {
    \SPP\Log::error('Operation failed', ['exception' =&gt; $e-&gt;getMessage()]);
    return $blade-&gt;run('error', ['message' =&gt; 'Something went wrong']);
}</pre>

        <!-- ================================================================ -->
        <!-- SECTION 10: Middleware -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">10. Middleware</h2>
        <p style="color:#475569; line-height:1.8;">Middleware runs before/after a request reaches your controller. Use for auth checks, logging, CORS, etc.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// ── src/{{APP_NAME}}/middleware/AuthGuard.php ──
namespace App\{{APP_NAME}}\Middleware;

class AuthGuard
{
    // The handle() method receives $request and $next callback
    public function handle($request, $next)
    {
        if (!\SPPMod\SPPAuth\SPPAuth::isLoggedIn()) {
            header('Location: /{{APP_NAME}}/login');
            exit;
        }
        return $next($request); // Continue to controller
    }
}

// ── etc/apps/{{APP_NAME}}/middleware.yml (global pipeline) ──
middleware:
  global:
    - \App\{{APP_NAME}}\Middleware\CorsMiddleware
    - \App\{{APP_NAME}}\Middleware\SessionMiddleware
  groups:
    auth:
      - \App\{{APP_NAME}}\Middleware\AuthGuard
    api:
      - \App\{{APP_NAME}}\Middleware\ApiRateLimit

// Per-route middleware (in pages.yml):
// dashboard:
//   controller: \App\{{APP_NAME}}\Serv\DashboardController@index
//   middleware: [auth]

// Per-route via attribute:
// #[Middleware(AuthGuard::class)]
// public function dashboard() { ... }

// MiddlewareKernel processes the pipeline in order.
// Each middleware calls $next() to pass to the next one.</pre>

        <!-- ================================================================ -->
        <!-- SECTION 11: Database & Entities -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">11. Database &amp; Entities</h2>
        <p style="color:#475569; line-height:1.8;">SPP uses the <code>SPPDB</code> module for database operations and entities for ORM-style data modeling.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// ── Creating an Entity ──
// Run: php spp.php make:entity --app={{APP_NAME}} Task
// This creates src/{{APP_NAME}}/entities/Task.php

namespace App\{{APP_NAME}}\Entities;

class Task extends \SPPMod\SPPDB\Entity
{
    protected string $table = '{{APP_NAME}}_tasks';  // Uses table prefix
    protected bool $apiExpose = true;                // Auto-generates REST API

    // Define fillable fields
    protected array $fillable = ['title', 'description', 'status', 'due_date'];

    // Relationships
    public function user() {
        return $this-&gt;belongsTo(User::class, 'user_id');
    }
}

// ── Using SPPDB directly ──
$db = \SPPMod\SPPDB\SPPDB::getInstance();

// Query builder:
$tasks = $db-&gt;table('tasks')-&gt;where('status', 'active')-&gt;get();
$task  = $db-&gt;table('tasks')-&gt;find(42);

// Raw query:
$results = $db-&gt;execute_query("SELECT * FROM tasks WHERE status = ?", ['active']);

// Insert:
$db-&gt;table('tasks')-&gt;insert(['title' =&gt; 'New Task', 'status' =&gt; 'pending']);

// Update:
$db-&gt;table('tasks')-&gt;where('id', 42)-&gt;update(['status' =&gt; 'done']);

// ── Migrations ──
// php spp.php migrate --app={{APP_NAME}}
// php spp.php migrate:rollback --app={{APP_NAME}}
// php spp.php db:seed --app={{APP_NAME}}</pre>

        <!-- ================================================================ -->
        <!-- SECTION 12: YAML Forms -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">12. YAML Forms</h2>
        <p style="color:#475569; line-height:1.8;">Define forms in YAML, and SPP handles rendering, validation, and processing automatically.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
# ── etc/apps/{{APP_NAME}}/forms/contact.yml ──
id: contact
public_name: "Contact Form"
success_message: "Thank you! Your message has been received."
redirect_to: "contact"

fields:
  guest_name:
    label: "Your Name"
    type: text                 # Types: text, email, textarea, select,
    required: true             #   checkbox, radio, hidden, number, file
    validation: "min:2"        # Validation: required, min:N, max:N,
    placeholder: "John Doe"   #   email, numeric, regex:pattern
  email:
    label: "Email Address"
    type: email
    required: false
  message:
    label: "Message"
    type: textarea
    required: true
    validation: "min:10"

# ── Rendering in Blade ──
# @sppform('contact')                       — Auto-render entire form
# @sppform_start('contact') ... @sppform_end — Manual layout control

# ── Rendering in PHP ──
# &lt;form method="POST"&gt;
#   &lt;input type="hidden" name="spp_form_id" value="contact"&gt;
#   &lt;!-- your fields here --&gt;
# &lt;/form&gt;
# SPP detects spp_form_id and auto-validates/processes the POST</pre>

        <!-- ================================================================ -->
        <!-- SECTION 13: Authentication -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">13. Authentication</h2>
        <p style="color:#475569; line-height:1.8;">The <code>sppauth</code> module provides login, logout, role-based access, and session management.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
use SPPMod\SPPAuth\SPPAuth;

// ── Login ──
$result = SPPAuth::login($username, $password);
if ($result['success']) {
    header('Location: /{{APP_NAME}}/dashboard');
}

// ── Check auth status ──
if (SPPAuth::isLoggedIn()) {
    $user = SPPAuth::getUser();
    echo "Hello, " . $user['name'];
}

// ── Role-based access ──
if (SPPAuth::hasRole('admin')) {
    // Show admin content
}
SPPAuth::requireRole('editor'); // Throws exception if not editor

// ── Logout ──
SPPAuth::logout();

// ── In Blade templates ──
// @sppauth
//     Welcome, {{ SPPAuth::getUser()['name'] }}!
//     @if(SPPAuth::hasRole('admin'))
//         &lt;a href="/admin"&gt;Admin Panel&lt;/a&gt;
//     @endif
// @endsppauth
// @sppguest
//     &lt;a href="/login"&gt;Please log in&lt;/a&gt;
// @endsppguest</pre>

        <!-- ================================================================ -->
        <!-- SECTION 14: Sessions -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">14. Sessions</h2>
        <p style="color:#475569; line-height:1.8;">SPP provides a session wrapper for storing per-user data across requests.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
use SPP\SPPSession;

// Set a session variable:
SPPSession::setVar('cart_items', [1, 2, 3]);
SPPSession::setVar('locale', 'en');

// Get a session variable:
$items = SPPSession::getVar('cart_items');   // [1, 2, 3]
$lang  = SPPSession::getVar('locale');       // 'en'

// Destroy a specific variable:
SPPSession::destroyVar('cart_items');

// Flash data (available for ONE request only):
SPPSession::flash('success', 'Item saved!');
$msg = SPPSession::getFlash('success'); // Available once, then auto-deleted

// Enterprise mode uses Redis for sessions (configured in global-settings.yml):
//   session_handler: redis
//   session_save_path: tcp://127.0.0.1:6379</pre>

        <!-- ================================================================ -->
        <!-- SECTION 15: Translations (i18n) -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">15. Translations (i18n)</h2>
        <p style="color:#475569; line-height:1.8;">Multi-language support via JSON language files and the <code>spplang</code> module.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// ── Language files: resources/{{APP_NAME}}/lang/en.json ──
{
    "welcome": "Welcome to our app!",
    "greeting": "Hello, :name!",
    "items_count": "You have :count item(s)"
}

// ── resources/{{APP_NAME}}/lang/es.json ──
{
    "welcome": "&iexcl;Bienvenido a nuestra app!",
    "greeting": "&iexcl;Hola, :name!"
}

// ── PHP usage ──
use SPPMod\SPPLang\Translation;

Translation::load('en');                         // Load English
echo Translation::get('welcome');                // "Welcome to our app!"
echo Translation::get('greeting', ['name' =&gt; 'John']); // "Hello, John!"

Translation::setLocale('es');                    // Switch to Spanish
echo Translation::get('welcome');                // "&iexcl;Bienvenido..."

// ── Blade usage ──
// @lang('welcome')
// @lang('greeting', ['name' =&gt; $user-&gt;name])</pre>

        <!-- ================================================================ -->
        <!-- SECTION 16: Dependency Injection -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">16. Dependency Injection</h2>
        <p style="color:#475569; line-height:1.8;">SPP includes a DI container for managing class dependencies and singletons.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
use SPP\Container;
use SPP\Registry;

// ── Binding services ──
Container::bind('mailer', function() {
    return new \App\{{APP_NAME}}\Services\MailService();
});

// ── Singleton (same instance every time) ──
Container::singleton('cache', function() {
    return new \App\{{APP_NAME}}\Services\CacheService();
});

// ── Resolving ──
$mailer = Container::get('mailer'); // New instance each time
$cache  = Container::get('cache'); // Same instance always

// ── Registry (global key-value store) ──
Registry::set('app.version', '1.0.0');
$version = Registry::get('app.version');  // '1.0.0'</pre>

        <!-- ================================================================ -->
        <!-- SECTION 17: Polyglot Bridge -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">17. Polyglot Bridge</h2>
        <p style="color:#475569; line-height:1.8;">Execute Python, Perl, or C++ code from PHP. Great for ML models, data processing, or legacy integration.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
use SPP\Polyglot;

// ── Run a Python script ──
$result = Polyglot::python('scripts/analyze.py', [
    'input_file' =&gt; '/path/to/data.csv',
    'model'      =&gt; 'sentiment',
]);

// ── Run a Perl script ──
$result = Polyglot::perl('scripts/parser.pl', ['--format=json']);

// ── Run a compiled C++ binary ──
$result = Polyglot::exec('bin/fast-compute', ['--threads=4']);

// $result contains: stdout, stderr, exit_code</pre>

        <!-- ================================================================ -->
        <!-- SECTION 18: Parikshak Testing -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">18. Parikshak Testing &#129514;</h2>
        <p style="color:#475569; line-height:1.8;">SPP's testing framework supports class-based, DSL, and evolutionary testing approaches.</p>

        <h3 style="color:#475569; margin-top:1.5rem;">18a. Class-based (SPPTestCase)</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
use SPPMod\Parikshak\SPPTestCase;
use SPPMod\Parikshak\Attributes\DataProvider;

class TaskTest extends SPPTestCase {
    use \SPPMod\Parikshak\InteractsWithApi;    // $this-&gt;get(), post(), put(), delete()
    use \SPPMod\Parikshak\RefreshDatabase;     // Clean DB per test

    public function testTaskCreation(): void {
        $this-&gt;assertTrue($task-&gt;exists());
        $this-&gt;assertEquals('New Task', $task-&gt;title);
        $this-&gt;assertSame(42, $task-&gt;id);         // Strict ===
        $this-&gt;assertInstanceOf(Task::class, $task);
        $this-&gt;expectException(\Exception::class, fn() =&gt; $task-&gt;delete());
    }

    #[DataProvider('statusData')]    // PHP 8 attribute for data-driven tests
    public function testStatus(string $input, bool $expected): void {
        $this-&gt;assertEquals($expected, Task::isValidStatus($input));
    }

    public function statusData(): array {
        return [['active', true], ['pending', true], ['invalid', false]];
    }
}
// File naming: test.TaskTest.php in src/{{APP_NAME}}/tests/</pre>

        <h3 style="color:#475569; margin-top:1.5rem;">18b. DSL Tests (BDD-style)</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
test('task can be created', function () {
    $task = new Task(['title' =&gt; 'Test']);
    expect($task-&gt;title)-&gt;toBe('Test');
});

it('validates required fields', function () {
    expect(fn() =&gt; new Task([]))-&gt;toThrow(\Exception::class);
});

// Also: -&gt;toBeTrue(), -&gt;toBeFalse(), -&gt;toBeNull(), -&gt;toContain()</pre>

        <h3 style="color:#475569; margin-top:1.5rem;">18c. Running Tests</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
php spp.php test:run --app={{APP_NAME}}               # Run full test suite
php spp.php test:run --app={{APP_NAME}} --coverage    # With code coverage report
php spp.php test:run --app={{APP_NAME}} TaskTest      # Run single test class
php spp.php test:blueprint --app={{APP_NAME}}          # Auto-generate test stubs
php spp.php test:monkey --app={{APP_NAME}}             # Fuzz/monkey testing
php spp.php test:evolve --app={{APP_NAME}}             # Evolutionary testing</pre>

        <!-- ================================================================ -->
        <!-- SECTION 19: Themes -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">19. Themes</h2>
        <p style="color:#475569; line-height:1.8;">Themes are defined in <code>resources/{{APP_NAME}}/themes/</code> with a <code>theme.yml</code> config.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
# ── resources/{{APP_NAME}}/themes/default/theme.yml ──
name: "Default Theme"
version: "1.0"
css:
  - css/main.css              # Theme CSS files
  - css/components.css
js:
  - js/theme.js               # Theme JavaScript
variables:                     # CSS custom properties
  --primary-color: "#6366f1"
  --bg-color: "#ffffff"
  --text-color: "#1e293b"

# Switching themes programmatically:
# \SPP\Theme::setActive('dark');
# \SPP\Theme::getActive(); // 'dark'

# In SPP-UX: Use the theme-picker component
# @sppux('theme-picker')</pre>

        <!-- ================================================================ -->
        <!-- SECTION 20: Core Modules Inventory -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">20. Core Modules Inventory</h2>
        <p style="color:#475569; line-height:1.8;">Load modules with <code>\SPP\Module::loadModule('name')</code> or list them in <code>modules.yml</code>.</p>

        <table style="width:100%; border-collapse:collapse; margin:1rem 0; font-size:0.78rem;">
            <tr style="background:#6366f1; color:#fff;"><th style="padding:0.5rem;">#</th><th style="padding:0.5rem;">Module</th><th style="padding:0.5rem;">Purpose</th><th style="padding:0.5rem;">Key API</th></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">1</td><td style="padding:0.5rem;"><b>sppview</b></td><td style="padding:0.5rem;">View layer, page rendering, theme injection</td><td style="padding:0.5rem;">SPPGlobal, ViewPage, RouteScanner</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">2</td><td style="padding:0.5rem;"><b>spprouter</b></td><td style="padding:0.5rem;">URL routing, dynamic params, named routes</td><td style="padding:0.5rem;">SPPRouter::resolve(), pages.yml</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">3</td><td style="padding:0.5rem;"><b>drishyam</b></td><td style="padding:0.5rem;">Blade templates + SPP-UX components</td><td style="padding:0.5rem;">SPPBlade, SPPUX::render(), @sppux</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">4</td><td style="padding:0.5rem;"><b>sppdb</b></td><td style="padding:0.5rem;">Database (MySQL/PostgreSQL/SQLite)</td><td style="padding:0.5rem;">SPPDB, execute_query(), migrations</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">5</td><td style="padding:0.5rem;"><b>sppauth</b></td><td style="padding:0.5rem;">Authentication, sessions, RBAC</td><td style="padding:0.5rem;">SPPAuth::login(), isLoggedIn()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">6</td><td style="padding:0.5rem;"><b>sppapi</b></td><td style="padding:0.5rem;">REST API auto-generation from entities</td><td style="padding:0.5rem;">Entity $apiExpose = true</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">7</td><td style="padding:0.5rem;"><b>parikshak</b></td><td style="padding:0.5rem;">Testing: unit, fuzzing, evolutionary, DSL</td><td style="padding:0.5rem;">SPPTestCase, test(), expect()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">8</td><td style="padding:0.5rem;"><b>spplogger</b></td><td style="padding:0.5rem;">Structured logging, log rotation</td><td style="padding:0.5rem;">\SPP\Log::info(), error()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">9</td><td style="padding:0.5rem;"><b>sppcache</b></td><td style="padding:0.5rem;">File &amp; memory caching</td><td style="padding:0.5rem;">\SPP\Cache::get(), put()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">10</td><td style="padding:0.5rem;"><b>sppqueue</b></td><td style="padding:0.5rem;">Background jobs, scheduled tasks</td><td style="padding:0.5rem;">\SPP\Queue::dispatch()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">11</td><td style="padding:0.5rem;"><b>sppstorage</b></td><td style="padding:0.5rem;">File storage, uploads</td><td style="padding:0.5rem;">\SPP\Storage::put(), get()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">12</td><td style="padding:0.5rem;"><b>sppaudit</b></td><td style="padding:0.5rem;">Audit trail, change tracking</td><td style="padding:0.5rem;">Auto per entity</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">13</td><td style="padding:0.5rem;"><b>sppsecurity</b></td><td style="padding:0.5rem;">CSRF, XSS, input sanitization</td><td style="padding:0.5rem;">Auto middleware</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">14</td><td style="padding:0.5rem;"><b>sppmaker</b></td><td style="padding:0.5rem;">Code generators (entity, controller, etc)</td><td style="padding:0.5rem;">php spp.php make:entity</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">15</td><td style="padding:0.5rem;"><b>spplang</b></td><td style="padding:0.5rem;">i18n, translations, locale</td><td style="padding:0.5rem;">\SPP\Lang::get(), @lang()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">16</td><td style="padding:0.5rem;"><b>sppai</b></td><td style="padding:0.5rem;">AI/LLM integration, embeddings</td><td style="padding:0.5rem;">\SPP\AI::prompt()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">17</td><td style="padding:0.5rem;"><b>sppworkflow</b></td><td style="padding:0.5rem;">State machines, approval flows</td><td style="padding:0.5rem;">YAML state definitions</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">18</td><td style="padding:0.5rem;"><b>spplive</b></td><td style="padding:0.5rem;">Real-time updates, SSE, WebSocket</td><td style="padding:0.5rem;">\SPP\Live::broadcast()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">19</td><td style="padding:0.5rem;"><b>sppreport</b></td><td style="padding:0.5rem;">PDF/Excel/CSV report generation</td><td style="padding:0.5rem;">Admin Reports panel</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">20</td><td style="padding:0.5rem;"><b>sppxdb</b></td><td style="padding:0.5rem;">Cross-database, federated queries</td><td style="padding:0.5rem;">Admin XDB panel</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">21</td><td style="padding:0.5rem;"><b>sppdeploy</b></td><td style="padding:0.5rem;">Deployment, migrations, env</td><td style="padding:0.5rem;">php spp.php deploy</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">22</td><td style="padding:0.5rem;"><b>sppdoc/sppdocs</b></td><td style="padding:0.5rem;">Auto API documentation</td><td style="padding:0.5rem;">Admin Docs panel</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">23</td><td style="padding:0.5rem;"><b>sppenv</b></td><td style="padding:0.5rem;">.env file support</td><td style="padding:0.5rem;">\SPP\Env::get('KEY')</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">24</td><td style="padding:0.5rem;"><b>sppext</b></td><td style="padding:0.5rem;">Extension/plugin system</td><td style="padding:0.5rem;">Extension marketplace</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">25</td><td style="padding:0.5rem;"><b>dbconfig</b></td><td style="padding:0.5rem;">DB-backed config storage</td><td style="padding:0.5rem;">\SPP\SPPConfig::get()</td></tr>
        </table>

        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// Load modules in init.php:
\SPP\Module::loadModule('parikshak');
\SPP\Module::loadModule('sppqueue');
if (\SPP\Module::isLoaded('sppai')) { /* use AI */ }
\SPP\Module::setConfig('key', 'value', 'modulename');</pre>

        <!-- ================================================================ -->
        <!-- SECTION 21: CLI Commands -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">21. CLI Commands</h2>
        <p style="color:#475569; line-height:1.8;">All commands are run with <code>php spp.php &lt;command&gt;</code> from the project root.</p>

        <table style="width:100%; border-collapse:collapse; margin:1rem 0; font-size:0.82rem;">
            <tr style="background:#6366f1; color:#fff;"><th style="text-align:left; padding:0.6rem;">Category</th><th style="text-align:left; padding:0.6rem;">Command</th><th style="text-align:left; padding:0.6rem;">What It Does</th></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"><b>Generators</b></td><td style="padding:0.6rem;"><code>make:app</code></td><td style="padding:0.6rem;">Create new application</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>make:entity</code></td><td style="padding:0.6rem;">Generate entity class</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>make:controller</code></td><td style="padding:0.6rem;">Generate controller class</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>make:middleware</code></td><td style="padding:0.6rem;">Generate middleware class</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"><b>Database</b></td><td style="padding:0.6rem;"><code>migrate</code></td><td style="padding:0.6rem;">Run migrations</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>migrate:rollback</code></td><td style="padding:0.6rem;">Rollback last migration</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>db:seed</code></td><td style="padding:0.6rem;">Seed database</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"><b>Testing</b></td><td style="padding:0.6rem;"><code>test:run</code></td><td style="padding:0.6rem;">Run test suite</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>test:blueprint</code></td><td style="padding:0.6rem;">Auto-generate test stubs</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>test:monkey</code></td><td style="padding:0.6rem;">Fuzz testing</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"><b>Cache</b></td><td style="padding:0.6rem;"><code>cache:clear</code></td><td style="padding:0.6rem;">Clear all caches</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>cache:warmup</code></td><td style="padding:0.6rem;">Pre-build caches</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>view:clear</code></td><td style="padding:0.6rem;">Clear compiled Blade views</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"><b>Deploy</b></td><td style="padding:0.6rem;"><code>deploy</code></td><td style="padding:0.6rem;">Deploy application</td></tr>
        </table>

        <!-- ================================================================ -->
        <!-- SECTION 22: Configuration -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">22. Configuration</h2>
        <p style="color:#475569; line-height:1.8;">SPP uses YAML files for configuration. Key files:</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
# ── spp/etc/global-settings.yml ── (framework-wide)
apps:
  {{APP_NAME}}:
    base_url: /{{APP_NAME}}
    table_prefix: {{APP_NAME}}_
    type: mixed
    shared_group: core
    etc_path: etc/apps/{{APP_NAME}}
    src_path: src/{{APP_NAME}}

# ── etc/apps/{{APP_NAME}}/config.yml ── (app-specific)
app:
  name: "{{APP_NAME}}"
  debug: true                    # SPP_DEBUG mode
  timezone: "UTC"

# ── Reading config in PHP ──
$value = \SPP\SPPConfig::get('app.name');       // "{{APP_NAME}}"
$debug = \SPP\SPPConfig::get('app.debug');       // true
\SPP\SPPConfig::set('app.timezone', 'Asia/Tokyo');</pre>

        <!-- ================================================================ -->
        <!-- SECTION 23: Caching -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">23. Caching</h2>
        <p style="color:#475569; line-height:1.8;">SPP caches routes, views, and config for performance. Use CLI to manage.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// ── Using the cache API ──
\SPP\Cache::put('key', $data, 3600);      // Cache for 1 hour
$data = \SPP\Cache::get('key');            // Retrieve
\SPP\Cache::forget('key');                 // Delete
\SPP\Cache::flush();                       // Clear all

// ── Cache locations ──
// var/cache/system/config.php    — Compiled config
// var/cache/routes_*.php         — Compiled routes
// var/cache/views/               — Compiled Blade templates

// ── CLI commands ──
// php spp.php cache:clear                 — Clear everything
// php spp.php cache:warmup                — Pre-compile caches
// php spp.php view:clear                  — Clear Blade cache only</pre>

        <!-- ================================================================ -->
        <!-- SECTION 24: Logging -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">24. Logging</h2>
        <p style="color:#475569; line-height:1.8;">The <code>spplogger</code> module provides structured logging with automatic log rotation.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
use SPP\Log;

Log::debug('Debug information', ['context' =&gt; $data]);
Log::info('User logged in', ['user_id' =&gt; 42]);
Log::warning('Slow query detected', ['time' =&gt; '2.5s']);
Log::error('Payment failed', ['order_id' =&gt; 123, 'reason' =&gt; $msg]);
Log::critical('Database connection lost');

// Logs are stored in: var/log/
// Format: [2024-01-15 10:30:00] INFO: User logged in {"user_id":42}
// Log rotation is automatic (configurable max file size)</pre>

        <!-- ================================================================ -->
        <!-- SECTION 25: Getting Started Checklist -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">25. Getting Started Checklist</h2>
        <p style="color:#475569; line-height:1.8;">Follow these steps to build your first feature in <code>{{APP_NAME}}</code>:</p>

        <div style="background:#f0fdf4; border-left:4px solid #22c55e; padding:1.2rem 1.5rem; border-radius:0 10px 10px 0; margin:1rem 0;">
            <ol style="color:#475569; line-height:2.2; margin:0; padding-left:1.2rem;">
                <li><b>Explore your app</b> &mdash; Visit <code>/{{APP_NAME}}/home</code> in your browser</li>
                <li><b>Edit a controller</b> &mdash; Modify <code>src/{{APP_NAME}}/serv/HomeController.php</code></li>
                <li><b>Edit a Blade view</b> &mdash; Modify <code>resources/{{APP_NAME}}/views/home.blade.php</code></li>
                <li><b>Create an entity</b> &mdash; Run <code>php spp.php make:entity --app={{APP_NAME}} Task</code></li>
                <li><b>Run migrations</b> &mdash; Run <code>php spp.php migrate --app={{APP_NAME}}</code></li>
                <li><b>Add a new route</b> &mdash; Edit <code>etc/apps/{{APP_NAME}}/pages.yml</code></li>
                <li><b>Create a YAML form</b> &mdash; Add a new file in <code>etc/apps/{{APP_NAME}}/forms/</code></li>
                <li><b>Build a component</b> &mdash; Create a JS file in <code>src/{{APP_NAME}}/comp/</code></li>
                <li><b>Write tests</b> &mdash; Add tests in <code>src/{{APP_NAME}}/tests/</code></li>
                <li><b>Run tests</b> &mdash; <code>php spp.php test:run --app={{APP_NAME}}</code></li>
                <li><b>Clear cache</b> &mdash; <code>php spp.php cache:clear</code> after route/config changes</li>
                <li><b>Deploy</b> &mdash; <code>php spp.php deploy</code></li>
            </ol>
        </div>

        <div style="margin-top:2rem; padding:1.5rem; background:#eff6ff; border-radius:10px; text-align:center;">
            <p style="color:#3b82f6; font-weight:600; margin:0;">Tip: Delete this guide page once you are comfortable with the framework!</p>
            <p style="color:#64748b; font-size:0.85rem; margin:0.5rem 0 0;">Remove the <code>'guide'</code> route from <code>etc/apps/{{APP_NAME}}/pages.yml</code> and delete this file.</p>
        </div>

    </div>
</div>
GUIDEPHP
            ,
            $appName
        );
    }

    // =========================================================================
    //  GUIDE PAGE: SPP-UX Mode — Reactive Component Tutorial
    // =========================================================================

    private function writeSppuxGuidePage(string $appName): void
    {
        $this->writeFile(
            "src/{$appName}/pages/guide.html",
            <<<'SPPUXGUIDE'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{APP_NAME}} — SPP-UX Developer Guide</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        /* ── SPP-UX Dark Theme Aesthetic ── */
        :root {
            --bg: #0a0a0f;
            --surface: #12121a;
            --panel: #1a1a2e;
            --border: #2a2a3e;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --primary: #818cf8;
            --primary-glow: rgba(129,140,248,0.15);
            --accent: #34d399;
            --warning: #fbbf24;
            --code-bg: #0f0f1a;
            --gradient-start: #6366f1;
            --gradient-end: #8b5cf6;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.7;
        }
        .guide-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }
        .guide-header {
            text-align: center;
            padding: 3rem 0 2rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 2.5rem;
        }
        .guide-header .badge {
            display: inline-block;
            background: var(--primary-glow);
            color: var(--primary);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            margin-bottom: 1rem;
            border: 1px solid rgba(129,140,248,0.2);
        }
        .guide-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .guide-header p { color: var(--muted); margin-top: 0.5rem; font-size: 1rem; }
        .toc {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem 2rem;
            margin-bottom: 2.5rem;
        }
        .toc h3 { color: var(--primary); margin-bottom: 0.8rem; font-size: 1rem; }
        .toc ol { padding-left: 1.5rem; color: var(--muted); }
        .toc li { margin: 0.3rem 0; }
        .toc a { color: var(--primary); text-decoration: none; }
        .toc a:hover { text-decoration: underline; }
        .section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 1.5rem;
        }
        .section h2 {
            color: var(--primary);
            font-size: 1.3rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
        }
        .section h3 { color: var(--accent); margin: 1.5rem 0 0.5rem; font-size: 1.05rem; }
        .section p { color: var(--muted); margin: 0.5rem 0; }
        .section ul, .section ol { color: var(--muted); padding-left: 1.5rem; margin: 0.5rem 0; }
        .section li { margin: 0.3rem 0; }
        .section b, .section strong { color: var(--text); }
        code {
            font-family: 'JetBrains Mono', monospace;
            background: var(--code-bg);
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 0.85em;
            color: var(--accent);
            border: 1px solid var(--border);
        }
        pre {
            background: var(--code-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.2rem;
            overflow-x: auto;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.82rem;
            line-height: 1.7;
            color: var(--text);
            margin: 0.8rem 0;
        }
        pre code { background: none; border: none; padding: 0; color: inherit; }
        .tip {
            background: rgba(52,211,153,0.08);
            border-left: 3px solid var(--accent);
            padding: 1rem 1.2rem;
            border-radius: 0 8px 8px 0;
            margin: 1rem 0;
            color: var(--muted);
        }
        .warning {
            background: rgba(251,191,36,0.08);
            border-left: 3px solid var(--warning);
            padding: 1rem 1.2rem;
            border-radius: 0 8px 8px 0;
            margin: 1rem 0;
            color: var(--muted);
        }
        table { width: 100%; border-collapse: collapse; margin: 0.8rem 0; font-size: 0.85rem; }
        th { background: var(--panel); color: var(--primary); text-align: left; padding: 0.6rem 0.8rem; }
        td { padding: 0.6rem 0.8rem; border-bottom: 1px solid var(--border); color: var(--muted); }
        .footer {
            text-align: center;
            padding: 2rem;
            color: var(--muted);
            font-size: 0.85rem;
            border-top: 1px solid var(--border);
            margin-top: 2rem;
        }
    </style>
</head>
<body>
<div class="guide-container">

    <div class="guide-header">
        <div class="badge">SPP-UX COMPREHENSIVE GUIDE</div>
        <h1>SPP-UX Developer Tutorial</h1>
        <p>Application: <code>{{APP_NAME}}</code> &mdash; Everything a complete novice needs to build reactive components</p>
    </div>

    <!-- ════════════ TABLE OF CONTENTS ════════════ -->
    <div class="toc">
        <h3>&#128203; Table of Contents</h3>
        <ol>
            <li><a href="#what-is-sppux">What is SPP-UX?</a></li>
            <li><a href="#project-structure">Project Structure</a></li>
            <li><a href="#lifecycle">Component Lifecycle</a></li>
            <li><a href="#state">State Management</a></li>
            <li><a href="#rendering">Rendering &amp; Templates</a></li>
            <li><a href="#props">Props</a></li>
            <li><a href="#composition">Component Composition</a></li>
            <li><a href="#store">SPPStore &mdash; Shared State</a></li>
            <li><a href="#services">Service Calls</a></li>
            <li><a href="#ui-helpers">UI Helpers</a></li>
            <li><a href="#themes">Themes &amp; CSS Variables</a></li>
            <li><a href="#sppform">SPPForm &mdash; YAML Forms</a></li>
            <li><a href="#events">Event Handling</a></li>
            <li><a href="#errors">Error Handling</a></li>
            <li><a href="#testing">Testing with Parikshak</a></li>
            <li><a href="#cli">CLI Commands</a></li>
            <li><a href="#first-component">Your First Component</a></li>
        </ol>
    </div>

    <!-- ════════════ 1. WHAT IS SPP-UX? ════════════ -->
    <div class="section" id="what-is-sppux">
        <h2>1. What is SPP-UX?</h2>
        <p><strong>SPP-UX (Drishyam)</strong> is a reactive component framework that runs entirely in the browser. It provides a React/Vue-like developer experience with <strong>zero dependencies</strong> &mdash; no npm, no webpack, no node_modules.</p>
        <p>Each component is a single <code>.js</code> file that extends <code>SPPComponent</code>. Components have:</p>
        <ul>
            <li><strong>Reactive state</strong> &mdash; call <code>this.setState()</code> and the DOM updates automatically</li>
            <li><strong>Lifecycle hooks</strong> &mdash; <code>onInit</code>, <code>onMount</code>, <code>afterUpdate</code>, <code>onDestroy</code></li>
            <li><strong>HTML templates</strong> &mdash; tagged template literals with <code>html``</code></li>
            <li><strong>Event binding</strong> &mdash; <code>@click</code>, <code>@input</code>, <code>@change</code> in templates</li>
            <li><strong>Service bridge</strong> &mdash; call PHP backend methods from JavaScript</li>
        </ul>
        <div class="tip"><strong>Key Insight:</strong> SPP-UX runs client-side but is served by the SPP PHP framework. The <code>index.php</code> entry point loads the SPP-UX runtime, and components auto-mount via <code>data-spp-component</code> attributes.</div>
    </div>

    <!-- ════════════ 2. PROJECT STRUCTURE ════════════ -->
    <div class="section" id="project-structure">
        <h2>2. Project Structure (sppux mode)</h2>
        <pre><code>your-project/
&#9500;&#9472;&#9472; etc/apps/{{APP_NAME}}/          # Configuration
&#9474;   &#9500;&#9472;&#9472; pages.yml                   # Routes (index is special:1)
&#9474;   &#9500;&#9472;&#9472; forms/                      # YAML form definitions
&#9474;   &#9474;   &#9492;&#9472;&#9472; contact.yml             # Auto-rendered by SPPForm
&#9474;   &#9500;&#9472;&#9472; services.yml                # PHP&#8596;JS bridge services
&#9474;   &#9492;&#9472;&#9472; events.yml                  # Event listeners
&#9474;
&#9500;&#9472;&#9472; src/{{APP_NAME}}/               # Source code
&#9474;   &#9500;&#9472;&#9472; index.php                   # SPA entry point (special:1)
&#9474;   &#9500;&#9472;&#9472; comp/                       # SPP-UX components (.js files)
&#9474;   &#9474;   &#9500;&#9472;&#9472; hello-world.js          # Sample component
&#9474;   &#9474;   &#9500;&#9472;&#9472; task-manager.js         # CRUD example
&#9474;   &#9474;   &#9492;&#9472;&#9472; theme-picker.js         # Theme switcher demo
&#9474;   &#9500;&#9472;&#9472; pages/                      # Static/guide pages
&#9474;   &#9474;   &#9492;&#9472;&#9472; guide.html              # This tutorial!
&#9474;   &#9500;&#9472;&#9472; assets/                     # CSS, images, static files
&#9474;   &#9500;&#9472;&#9472; entities/                   # Database entities
&#9474;   &#9492;&#9472;&#9472; tests/                      # Parikshak tests
&#9474;
&#9492;&#9472;&#9472; spp/                            # Framework core</code></pre>
        <div class="tip"><strong>Naming convention:</strong> Components use kebab-case filenames: <code>my-widget.js</code>. They are mounted with <code>data-spp-component="my-widget"</code>.</div>
    </div>

    <!-- ════════════ 3. LIFECYCLE ════════════ -->
    <div class="section" id="lifecycle">
        <h2>3. Component Lifecycle</h2>
        <p>Every SPP-UX component goes through a defined lifecycle. Understanding this is crucial:</p>
        <pre><code>class MyComponent extends SPPComponent {
    // 1. constructor() &#8594; Called when the class is instantiated.
    //    Use for: setting initial state, binding methods.
    //    DO NOT access DOM here &#8212; it doesn't exist yet.
    constructor() {
        super();
        this.state = { count: 0 };
    }

    // 2. onInit() &#8594; Called ONCE before the first render.
    //    Use for: fetching initial data, setting up subscriptions.
    async onInit() {
        const data = await this.service('getItems');
        this.setState({ items: data });
    }

    // 3. render() &#8594; Returns the HTML template. Called on every state change.
    //    MUST return html`` tagged template literal.
    render() {
        return html`&lt;div&gt;Count: ${this.state.count}&lt;/div&gt;`;
    }

    // 4. onMount() &#8594; Called ONCE after the first render hits the DOM.
    //    Use for: DOM measurements, third-party library init, focus.
    onMount() {
        this.el.querySelector('input')?.focus();
    }

    // 5. afterUpdate() &#8594; Called AFTER every re-render (not the first).
    //    Use for: animations, scroll position, post-update logic.
    afterUpdate() {
        console.log('DOM updated with new state');
    }

    // 6. onDestroy() &#8594; Called when the component is removed from DOM.
    //    Use for: cleanup timers, unsubscribe, close connections.
    onDestroy() {
        clearInterval(this._timer);
    }
}</code></pre>
        <table>
            <tr><th>Hook</th><th>When</th><th>DOM Ready?</th><th>Use For</th></tr>
            <tr><td><code>constructor</code></td><td>Instantiation</td><td>No</td><td>Initial state, bind methods</td></tr>
            <tr><td><code>onInit</code></td><td>Before first render</td><td>No</td><td>Async data fetch, subscriptions</td></tr>
            <tr><td><code>render</code></td><td>Every state change</td><td>No (returns template)</td><td>Declare UI</td></tr>
            <tr><td><code>onMount</code></td><td>After first DOM paint</td><td>Yes</td><td>DOM access, focus, lib init</td></tr>
            <tr><td><code>afterUpdate</code></td><td>After re-render</td><td>Yes</td><td>Animations, scroll, post-update</td></tr>
            <tr><td><code>onDestroy</code></td><td>Removal from DOM</td><td>Yes (about to remove)</td><td>Cleanup timers, listeners</td></tr>
        </table>
    </div>

    <!-- ════════════ 4. STATE MANAGEMENT ════════════ -->
    <div class="section" id="state">
        <h2>4. State Management</h2>
        <p>State is the data that drives your component's UI. When state changes, the component re-renders automatically.</p>
        <pre><code>// &#9989; CORRECT &#8212; use setState() to trigger re-render
this.setState({ count: this.state.count + 1 });

// &#9989; Partial updates &#8212; only specified keys are changed
this.setState({ loading: true });  // other state keys unchanged

// &#10060; WRONG &#8212; direct mutation does NOT trigger re-render
this.state.count = 5;  // UI will NOT update!

// &#9989; Reading current state
const currentCount = this.state.count;

// &#9989; Complex state updates (arrays, objects)
this.setState({
    items: [...this.state.items, newItem],           // append to array
    user: { ...this.state.user, name: 'New Name' }   // update object
});</code></pre>
        <div class="warning"><strong>Immutable State:</strong> Never mutate <code>this.state</code> directly. Always use <code>this.setState()</code>. SPP-UX compares old and new state to determine what changed.</div>
    </div>

    <!-- ════════════ 5. RENDERING ════════════ -->
    <div class="section" id="rendering">
        <h2>5. Rendering &amp; Templates</h2>
        <p>The <code>render()</code> method returns an <code>html``</code> tagged template literal. This is NOT a string &mdash; it's a template that SPP-UX diffs against the DOM.</p>
        <pre><code>render() {
    return html`
        &lt;div class="my-component"&gt;
            &lt;!-- &#9312; Text interpolation --&gt;
            &lt;h1&gt;Hello, ${this.state.name}&lt;/h1&gt;

            &lt;!-- &#9313; Conditional rendering --&gt;
            ${this.state.loading
                ? html`&lt;div class="spinner"&gt;Loading...&lt;/div&gt;`
                : html`&lt;div class="content"&gt;${this.state.data}&lt;/div&gt;`
            }

            &lt;!-- &#9314; List rendering --&gt;
            &lt;ul&gt;
                ${this.state.items.map(item =&gt; html`
                    &lt;li key="${item.id}"&gt;${item.name}&lt;/li&gt;
                `)}
            &lt;/ul&gt;

            &lt;!-- &#9315; Event binding --&gt;
            &lt;button @click="${() =&gt; this.increment()}"&gt;Click Me&lt;/button&gt;

            &lt;!-- &#9316; Two-way input binding --&gt;
            &lt;input @input="${e =&gt; this.setState({name: e.target.value})}"
                   value="${this.state.name}"&gt;

            &lt;!-- &#9317; Dynamic classes --&gt;
            &lt;div class="item ${this.state.active ? 'is-active' : ''}"&gt;&lt;/div&gt;

            &lt;!-- &#9318; Dynamic styles --&gt;
            &lt;div style="color: ${this.state.error ? 'red' : 'green'}"&gt;&lt;/div&gt;
        &lt;/div&gt;
    `;
}</code></pre>
    </div>

    <!-- ════════════ 6. PROPS ════════════ -->
    <div class="section" id="props">
        <h2>6. Props</h2>
        <p>Props are data passed to a component from its parent HTML element via <code>data-spp-props</code>.</p>
        <pre><code>&lt;!-- In your HTML &#8212; pass props as JSON --&gt;
&lt;div data-spp-component="user-card"
     data-spp-props='{"userId": 42, "showAvatar": true}'&gt;&lt;/div&gt;

// In your component &#8212; access via this.props
class UserCard extends SPPComponent {
    async onInit() {
        // this.props contains the parsed JSON
        const user = await this.service('getUser', { id: this.props.userId });
        this.setState({ user });
    }

    render() {
        return html`
            &lt;div class="card"&gt;
                ${this.props.showAvatar
                    ? html`&lt;img src="${this.state.user?.avatar}"&gt;`
                    : ''}
                &lt;h3&gt;${this.state.user?.name}&lt;/h3&gt;
            &lt;/div&gt;
        `;
    }
}</code></pre>
        <div class="tip"><strong>Props are read-only.</strong> Components should not modify <code>this.props</code>. Use state for mutable data.</div>
    </div>

    <!-- ════════════ 7. COMPOSITION ════════════ -->
    <div class="section" id="composition">
        <h2>7. Component Composition</h2>
        <p>Mount sub-components inside a parent using <code>data-spp-component</code> divs in your template:</p>
        <pre><code>// parent-dashboard.js
class ParentDashboard extends SPPComponent {
    render() {
        return html`
            &lt;div class="dashboard"&gt;
                &lt;h1&gt;Dashboard&lt;/h1&gt;

                &lt;!-- Mount child components &#8212; the loader auto-discovers these --&gt;
                &lt;div data-spp-component="stats-widget"
                     data-spp-props='{"period": "weekly"}'&gt;&lt;/div&gt;

                &lt;div data-spp-component="recent-activity"&gt;&lt;/div&gt;

                &lt;div data-spp-component="task-manager"&gt;&lt;/div&gt;
            &lt;/div&gt;
        `;
    }

    onMount() {
        // After render, tell SPP-UX to scan for new component divs
        SPPUX.mountChildren(this.el);
    }
}</code></pre>
    </div>

    <!-- ════════════ 8. SPPSTORE ════════════ -->
    <div class="section" id="store">
        <h2>8. SPPStore &mdash; Shared State</h2>
        <p>When multiple components need to share data, use <code>SPPStore</code> (a global reactive store):</p>
        <pre><code>// &#9312; Create a store (usually in your entry point or a shared file)
const appStore = new SPPStore({
    user: null,
    theme: 'midnight',
    notifications: [],
    cart: { items: [], total: 0 }
});

// &#9313; Subscribe to changes in any component
class NavBar extends SPPComponent {
    onInit() {
        appStore.subscribe('user', (newUser) =&gt; {
            this.setState({ user: newUser });
        });
    }
}

// &#9314; Update from any component &#8212; all subscribers get notified
class LoginForm extends SPPComponent {
    async login() {
        const user = await this.service('authenticate', this.state.form);
        appStore.set('user', user);    // NavBar auto-updates!
        appStore.notify('user');        // Explicit notification
    }
}

// &#9315; Read store values
const currentTheme = appStore.get('theme');</code></pre>
    </div>

    <!-- ════════════ 9. SERVICE CALLS ════════════ -->
    <div class="section" id="services">
        <h2>9. Service Calls (PHP&#8596;JS Bridge)</h2>
        <p>SPP-UX provides three ways to call your PHP backend from JavaScript:</p>
        <pre><code>// METHOD 1: this.service() &#8212; explicit call with method name
const items = await this.service('getItems', { page: 1 });
// Calls the PHP service method registered in etc/apps/{{APP_NAME}}/services.yml

// METHOD 2: this.serv proxy &#8212; shorthand (auto-maps method names)
const user = await this.serv.getUser({ id: 42 });
// Equivalent to: this.service('getUser', { id: 42 })

// METHOD 3: SPPUX.api() &#8212; direct REST API call (no service registration)
const response = await SPPUX.api('/api/v1/items', {
    method: 'POST',
    body: { name: 'New Item' }
});</code></pre>
        <h3>Registering PHP Services</h3>
        <pre><code># etc/apps/{{APP_NAME}}/services.yml
services:
  getItems:
    class: \App\{{APP_NAME}}\Serv\ItemService
    method: getItems

  getUser:
    class: \App\{{APP_NAME}}\Serv\UserService
    method: findById</code></pre>
    </div>

    <!-- ════════════ 10. UI HELPERS ════════════ -->
    <div class="section" id="ui-helpers">
        <h2>10. UI Helpers</h2>
        <p>SPP-UX includes a full UI library for common interactions:</p>
        <pre><code>// &#9312; Toast Notifications
this.notify('Item saved successfully!', 'success');  // success, error, warning, info
this.notify('Something went wrong', 'error');

// &#9313; Confirmation Dialog
const confirmed = await this.confirm('Delete this item?', 'This cannot be undone.');
if (confirmed) { /* delete */ }

// &#9314; Prompt Dialog (get user input)
const name = await this.prompt('Enter your name:', 'Default Value');

// &#9315; Modal (full custom content)
SPPUX.Modal.open({
    title: 'Edit Item',
    content: html`&lt;form&gt;...&lt;/form&gt;`,
    onClose: () =&gt; console.log('Modal closed'),
    size: 'lg'   // sm, md, lg, xl, full
});
SPPUX.Modal.close();

// &#9316; Busy Indicator (loading overlay)
SPPUX.Busy.show('Loading data...');
await fetchData();
SPPUX.Busy.hide();

// &#9317; Drawer (slide-in panel)
SPPUX.Drawer.open({
    title: 'Settings',
    content: html`&lt;div&gt;...&lt;/div&gt;`,
    position: 'right'   // left, right
});

// &#9318; Spotlight (command palette / search)
SPPUX.Spotlight.open({
    placeholder: 'Search actions...',
    items: [{label: 'Go Home', action: () =&gt; navigate('/')}]
});</code></pre>
    </div>

    <!-- ════════════ 11. THEMES ════════════ -->
    <div class="section" id="themes">
        <h2>11. Themes &amp; CSS Variables</h2>
        <p>SPP-UX ships with <strong>7 built-in themes</strong>. Switch themes at runtime with one call:</p>
        <table>
            <tr><th>Theme</th><th>Style</th><th>Primary Color</th></tr>
            <tr><td><code>midnight</code></td><td>Dark purple-blue</td><td>#818cf8</td></tr>
            <tr><td><code>emerald</code></td><td>Dark green</td><td>#34d399</td></tr>
            <tr><td><code>royal</code></td><td>Dark gold</td><td>#fbbf24</td></tr>
            <tr><td><code>cyberpunk</code></td><td>Neon pink</td><td>#f472b6</td></tr>
            <tr><td><code>ocean</code></td><td>Dark teal</td><td>#22d3ee</td></tr>
            <tr><td><code>saffron</code></td><td>Warm orange</td><td>#fb923c</td></tr>
            <tr><td><code>day</code></td><td>Light mode</td><td>#6366f1</td></tr>
        </table>
        <pre><code>// Switch theme programmatically
SPPUX.Theme.set('cyberpunk');

// Get current theme
const current = SPPUX.Theme.get();

// CSS Variables available in all themes:
// --sppux-bg          Background color
// --sppux-text        Text color
// --sppux-primary     Primary accent
// --sppux-panel       Panel/card background
// --sppux-border      Border color
// --sppux-muted       Muted text
// --sppux-success     Success color
// --sppux-error       Error color
// --sppux-warning     Warning color
// --sppux-radius      Border radius
// --sppux-shadow      Box shadow

/* Use in your CSS: */
.my-card {
    background: var(--sppux-panel);
    color: var(--sppux-text);
    border: 1px solid var(--sppux-border);
    border-radius: var(--sppux-radius);
    box-shadow: var(--sppux-shadow);
}</code></pre>
    </div>

    <!-- ════════════ 12. SPPFORM ════════════ -->
    <div class="section" id="sppform">
        <h2>12. SPPForm &mdash; YAML-Driven Forms</h2>
        <p>Define forms in YAML and SPPForm renders them automatically in the current theme:</p>
        <pre><code># etc/apps/{{APP_NAME}}/forms/contact.yml
form:
  id: contact
  title: Contact Us
  action: /api/contact
  method: POST

fields:
  name:
    type: text
    label: Your Name
    required: true
    placeholder: John Doe

  email:
    type: email
    label: Email Address
    required: true
    validation: email

  message:
    type: textarea
    label: Message
    rows: 5

  priority:
    type: select
    label: Priority
    options:
      low: Low
      medium: Medium
      high: High

submit:
  label: Send Message
  class: btn-primary</code></pre>
        <pre><code>// Mount in a component
render() {
    return html`
        &lt;div data-spp-form="contact"&gt;&lt;/div&gt;
    `;
}
// SPPForm auto-discovers data-spp-form divs and renders the YAML form</code></pre>
    </div>

    <!-- ════════════ 13. EVENTS ════════════ -->
    <div class="section" id="events">
        <h2>13. Event Handling</h2>
        <p>SPP-UX templates support all DOM events with the <code>@</code> prefix:</p>
        <table>
            <tr><th>Event</th><th>Example</th><th>Use For</th></tr>
            <tr><td><code>@click</code></td><td><code>@click="${() =&gt; this.handleClick()}"</code></td><td>Buttons, links, toggles</td></tr>
            <tr><td><code>@input</code></td><td><code>@input="${e =&gt; this.onInput(e)}"</code></td><td>Text inputs (fires on each keystroke)</td></tr>
            <tr><td><code>@change</code></td><td><code>@change="${e =&gt; this.onChange(e)}"</code></td><td>Select, checkbox, radio (on blur)</td></tr>
            <tr><td><code>@submit</code></td><td><code>@submit="${e =&gt; this.onSubmit(e)}"</code></td><td>Form submission</td></tr>
            <tr><td><code>@keydown</code></td><td><code>@keydown="${e =&gt; this.onKey(e)}"</code></td><td>Keyboard shortcuts</td></tr>
            <tr><td><code>@keyup</code></td><td><code>@keyup="${e =&gt; this.onKeyUp(e)}"</code></td><td>After key release</td></tr>
            <tr><td><code>@focus</code></td><td><code>@focus="${() =&gt; this.onFocus()}"</code></td><td>Input focus</td></tr>
            <tr><td><code>@blur</code></td><td><code>@blur="${() =&gt; this.onBlur()}"</code></td><td>Input blur (validation)</td></tr>
            <tr><td><code>@mouseover</code></td><td><code>@mouseover="${() =&gt; this.onHover()}"</code></td><td>Hover effects</td></tr>
            <tr><td><code>@dragstart</code></td><td><code>@dragstart="${e =&gt; this.onDrag(e)}"</code></td><td>Drag and drop</td></tr>
            <tr><td><code>@drop</code></td><td><code>@drop="${e =&gt; this.onDrop(e)}"</code></td><td>Drop target</td></tr>
            <tr><td><code>@scroll</code></td><td><code>@scroll="${e =&gt; this.onScroll(e)}"</code></td><td>Scroll tracking</td></tr>
        </table>
    </div>

    <!-- ════════════ 14. ERROR HANDLING ════════════ -->
    <div class="section" id="errors">
        <h2>14. Error Handling</h2>
        <pre><code>class MyComponent extends SPPComponent {
    async onInit() {
        try {
            const data = await this.service('getData');
            this.setState({ data, error: null });
        } catch (err) {
            // Show error toast and set error state
            this.notify('Failed to load data: ' + err.message, 'error');
            this.setState({ error: err.message, loading: false });
        }
    }

    render() {
        if (this.state.error) {
            return html`
                &lt;div class="error-panel"&gt;
                    &lt;p&gt;&#9888; ${this.state.error}&lt;/p&gt;
                    &lt;button @click="${() =&gt; this.retry()}"&gt;Retry&lt;/button&gt;
                &lt;/div&gt;
            `;
        }
        return html`&lt;div&gt;${this.state.data}&lt;/div&gt;`;
    }

    async retry() {
        this.setState({ error: null, loading: true });
        await this.onInit();
    }
}</code></pre>
    </div>

    <!-- ════════════ 15. TESTING ════════════ -->
    <div class="section" id="testing">
        <h2>15. Testing with Parikshak</h2>
        <pre><code>// src/{{APP_NAME}}/tests/HelloWorldTest.php
use SPP\Testing\Parikshak;

class HelloWorldTest extends Parikshak
{
    /** @test */
    public function component_file_exists()
    {
        $this-&gt;assertFileExists(
            SPP_APP_DIR . '/src/{{APP_NAME}}/comp/hello-world.js'
        );
    }

    /** @test */
    public function entry_point_boots_sppux()
    {
        $content = file_get_contents(SPP_APP_DIR . '/src/{{APP_NAME}}/index.php');
        $this-&gt;assertStringContains('SPPUX::boot', $content);
    }
}
// Run: php spp.php test:run --app={{APP_NAME}}</code></pre>
    </div>

    <!-- ════════════ 16. CLI ════════════ -->
    <div class="section" id="cli">
        <h2>16. CLI Commands for SPP-UX</h2>
        <table>
            <tr><th>Command</th><th>Description</th></tr>
            <tr><td><code>php spp.php make:ux-component --app={{APP_NAME}} MyWidget</code></td><td>Generate a new component file</td></tr>
            <tr><td><code>php spp.php make:store --app={{APP_NAME}} AppStore</code></td><td>Generate a shared store</td></tr>
            <tr><td><code>php spp.php make:service --app={{APP_NAME}} ItemService</code></td><td>Generate a PHP service class</td></tr>
            <tr><td><code>php spp.php make:entity --app={{APP_NAME}} Task</code></td><td>Generate a database entity</td></tr>
            <tr><td><code>php spp.php test:run --app={{APP_NAME}}</code></td><td>Run Parikshak tests</td></tr>
            <tr><td><code>php spp.php cache:clear</code></td><td>Clear framework caches</td></tr>
            <tr><td><code>php spp.php theme:list</code></td><td>List available themes</td></tr>
        </table>
    </div>

    <!-- ════════════ 17. FIRST COMPONENT ════════════ -->
    <div class="section" id="first-component">
        <h2>17. Your First Component &mdash; Step by Step</h2>
        <p>Let's build a simple counter component from scratch:</p>

        <h3>Step 1: Create the file</h3>
        <pre><code>// Create: src/{{APP_NAME}}/comp/my-counter.js</code></pre>

        <h3>Step 2: Write the component</h3>
        <pre><code>class MyCounter extends SPPComponent {
    constructor() {
        super();
        this.state = { count: 0 };  // Initial state
    }

    increment() {
        this.setState({ count: this.state.count + 1 });
    }

    decrement() {
        this.setState({ count: this.state.count - 1 });
    }

    render() {
        return html`
            &lt;div style="text-align:center; padding:2rem;"&gt;
                &lt;h2&gt;Counter: ${this.state.count}&lt;/h2&gt;
                &lt;button @click="${() =&gt; this.decrement()}"
                        style="padding:0.5rem 1rem; margin:0.5rem;"&gt;
                    &#8722;
                &lt;/button&gt;
                &lt;button @click="${() =&gt; this.increment()}"
                        style="padding:0.5rem 1rem; margin:0.5rem;"&gt;
                    +
                &lt;/button&gt;
            &lt;/div&gt;
        `;
    }
}</code></pre>

        <h3>Step 3: Mount it in index.php</h3>
        <pre><code>&lt;!-- Add this div anywhere in your index.php body --&gt;
&lt;div data-spp-component="my-counter"&gt;&lt;/div&gt;
&lt;!-- The SPP-UX loader auto-discovers and mounts it --&gt;</code></pre>

        <h3>Step 4: Visit your app</h3>
        <pre><code>Open your browser to your app's URL.
The counter should appear and respond to button clicks!</code></pre>

        <div class="tip"><strong>Congratulations!</strong> You've built your first SPP-UX component. Delete this guide page when you're comfortable &mdash; remove <code>pages/guide.html</code> from your project.</div>
    </div>

    <div class="footer">
        &copy; {{APP_NAME}} &bull; Powered by SPP Framework &bull; SPP-UX Drishyam Engine
    </div>
</div>
</body>
</html>
SPPUXGUIDE
            ,
            $appName
        );
    }

    // =========================================================================
    //  GUIDE PAGE: Blade Mode — Server-Rendered Template Tutorial
    // =========================================================================

    private function writeBladeGuidePage(string $appName): void
    {
        $viewsDir = SPP_APP_DIR . "/src/{$appName}/resources/views";
        if (!is_dir($viewsDir))
            mkdir($viewsDir, 0777, true);

        $this->writeFile(
            "src/{$appName}/resources/views/guide.blade.php",
            <<<'BLADEGUIDE'
{{-- ============================================================================
     {{APP_NAME}} — COMPREHENSIVE Blade Mode Developer Guide
     ============================================================================
     This is a Blade template rendered by HomeController@guide.
     It uses @extends('layouts.app') to inherit the master layout.
     Delete this file once you understand the framework!
     ============================================================================ --}}

@extends('layouts.app')

@section('content')
<div style="max-width: 1100px; margin: 2rem auto; padding: 0 1rem; font-family: Inter, -apple-system, BlinkMacSystemFont, sans-serif;">
    <div style="background: #fff; border-radius: 16px; padding: 2.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #e2e8f0;">

        <span style="display:inline-block; background:#e0e7ff; color:#4f46e5; padding:4px 14px; border-radius:20px; font-size:0.75rem; font-weight:700; margin-bottom:1rem; letter-spacing:0.05em;">BLADE MODE GUIDE</span>
        <h1 style="margin: 0 0 0.5rem; font-size: 2rem; color: #1e293b;">Blade Mode — Complete Tutorial</h1>
        <p style="color: #94a3b8; font-size: 0.95rem; margin-bottom: 2.5rem;">Application: <code style="background:#f1f5f9;padding:2px 8px;border-radius:6px;">{{APP_NAME}}</code></p>

        <!-- ════════════ 1. WHAT IS BLADE MODE? ════════════ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">1. What is Blade Mode?</h2>
        <p style="color:#475569; line-height:1.8;">
            <b>Blade mode</b> uses server-rendered PHP templates. Your HTML is generated on the server (in PHP) and sent
            to the browser as a complete page. This is the traditional web approach &mdash; reliable, SEO-friendly, and fast for content-heavy sites.
        </p>
        <p style="color:#475569; line-height:1.8;">
            SPP's Blade engine is inspired by Laravel's Blade but adds custom directives like <code>@sppux</code>, <code>@sppform</code>,
            <code>@sppauth</code>, and more for deep framework integration.
        </p>

        <!-- ════════════ 2. BLADE SYNTAX ════════════ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">2. Blade Template Syntax</h2>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
{{-- This is a Blade comment (not sent to browser) --}}

{{-- ① Echo with HTML escaping (safe from XSS) --}}
&lt;p&gt;Hello, {{ $name }}&lt;/p&gt;

{{-- ② Echo WITHOUT escaping (raw HTML — use carefully!) --}}
&lt;div&gt;{!! $rawHtml !!}&lt;/div&gt;

{{-- ③ Conditionals --}}
@if ($user->isAdmin())
    &lt;p&gt;Welcome, Admin!&lt;/p&gt;
@elseif ($user->isMember())
    &lt;p&gt;Welcome, Member!&lt;/p&gt;
@else
    &lt;p&gt;Welcome, Guest!&lt;/p&gt;
@endif

{{-- ④ Loops --}}
@foreach ($items as $item)
    &lt;li&gt;{{ $item->name }}&lt;/li&gt;
@endforeach

@for ($i = 0; $i < 10; $i++)
    &lt;span&gt;{{ $i }}&lt;/span&gt;
@endfor

@while ($condition)
    &lt;p&gt;Still going...&lt;/p&gt;
@endwhile

{{-- ⑤ Checking empty collections --}}
@forelse ($items as $item)
    &lt;li&gt;{{ $item->name }}&lt;/li&gt;
@empty
    &lt;li&gt;No items found.&lt;/li&gt;
@endforelse</pre>

        <!-- ════════════ 3. LAYOUTS ════════════ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">3. Layouts &amp; Inheritance</h2>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
{{-- ── layouts/app.blade.php (Master Layout) ── --}}
&lt;!DOCTYPE html&gt;
&lt;html&gt;
&lt;head&gt;
    &lt;title&gt;@yield('title', 'Default Title')&lt;/title&gt;
    @stack('styles')     {{-- Pushed CSS from child views --}}
&lt;/head&gt;
&lt;body&gt;
    @include('partials.nav')    {{-- Include a partial --}}
    @yield('content')           {{-- Child content goes here --}}
    @stack('scripts')           {{-- Pushed JS from child views --}}
&lt;/body&gt;
&lt;/html&gt;

{{-- ── home.blade.php (Child View) ── --}}
@extends('layouts.app')

@section('title', 'Home Page')

@push('styles')
    &lt;style&gt;.hero { background: #6366f1; }&lt;/style&gt;
@endpush

@section('content')
    &lt;div class="hero"&gt;&lt;h1&gt;Welcome!&lt;/h1&gt;&lt;/div&gt;
@endsection

@push('scripts')
    &lt;script&gt;console.log('Home loaded');&lt;/script&gt;
@endpush</pre>

        <!-- ════════════ 4. SPP CUSTOM DIRECTIVES ════════════ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">4. ALL SPP Custom Directives</h2>
        <p style="color:#475569; line-height:1.8;">These are SPP-specific Blade directives that integrate the framework's features:</p>

        <table style="width:100%; border-collapse:collapse; margin:1rem 0; font-size:0.82rem;">
            <tr style="background:#6366f1; color:#fff;"><th style="text-align:left; padding:0.7rem;">Directive</th><th style="text-align:left; padding:0.7rem;">Description</th></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@sppux('component', ['prop'=>'val'])</code></td><td style="padding:0.7rem;">Mount an SPP-UX reactive component inside Blade</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>@sppform('formName')</code></td><td style="padding:0.7rem;">Render a YAML-defined form (from etc/apps/APP/forms/)</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@sppform_start('name') / @sppform_end</code></td><td style="padding:0.7rem;">Manual form rendering with custom layout between</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>@sppelement('field', 'formName')</code></td><td style="padding:0.7rem;">Render a single form field from a YAML form</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@sppauth ... @endsppauth</code></td><td style="padding:0.7rem;">Show content only for authenticated users</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>@sppguest ... @endsppguest</code></td><td style="padding:0.7rem;">Show content only for guests (not logged in)</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@sppbind($entity)</code></td><td style="padding:0.7rem;">Bind a database entity to a form for editing</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>@react('Component', ['data'=>$val])</code></td><td style="padding:0.7rem;">Mount a React component (if React bridge loaded)</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@vue('component', ['data'=>$val])</code></td><td style="padding:0.7rem;">Mount a Vue component (if Vue bridge loaded)</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>@sppoffline('key') ... @endsppoffline</code></td><td style="padding:0.7rem;">Cache content for offline access (service worker)</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@module_component('name')</code></td><td style="padding:0.7rem;">Mount a component from an SPP module</td></tr>
        </table>

        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
{{-- Mount a reactive SPP-UX component inside a Blade view --}}
@sppux('task-manager', ['userId' => $user->id])

{{-- Render a YAML form --}}
@sppform('contact')

{{-- Conditional auth sections --}}
@sppauth
    &lt;p&gt;Welcome back, {{ $user->name }}!&lt;/p&gt;
    &lt;a href="/dashboard"&gt;Dashboard&lt;/a&gt;
@endsppauth

@sppguest
    &lt;a href="/login"&gt;Log In&lt;/a&gt;
@endsppguest

{{-- Bind entity to form for editing --}}
@sppbind($task)
    @sppform('task-edit')
@endsppbind</pre>

        <!-- ════════════ 5. CONTROLLERS ════════════ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">5. Controllers</h2>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// src/{{APP_NAME}}/serv/HomeController.php
namespace App\{{APP_NAME}}\Serv;

class HomeController
{
    public function index()
    {
        // SPPBlade::getInstance()->run() renders a Blade template
        $blade = \SPPMod\Drishyam\SPPBlade::getInstance();
        return $blade->run('home', [
            'title'    => 'Welcome',
            'items'    => $this->getItems(),
            'user'     => \SPPMod\SPPAuth\SPPAuth::getUser(),
        ]);
    }

    // Views are looked up in: src/{{APP_NAME}}/resources/views/
    // 'home' → home.blade.php
    // 'layouts.app' → layouts/app.blade.php
    // 'partials.nav' → partials/nav.blade.php
}</pre>

        <!-- ════════════ 6. ATTRIBUTE ROUTING ════════════ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">6. PHP 8 Attribute Routing</h2>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
use SPPMod\SPPView\Attributes\Route;
use SPPMod\SPPView\Attributes\Middleware;
use SPPMod\SPPView\Attributes\Title;

#[Route('/blog')]
#[Title('Blog')]
class BlogController
{
    #[Route('/', method: 'GET', name: 'blog.index')]
    public function index() { /* GET /blog */ }

    #[Route('/{slug}', method: 'GET')]
    public function show() { /* GET /blog/my-post */ }

    #[Route('/create', method: 'POST')]
    #[Middleware(\App\{{APP_NAME}}\Middleware\AuthGuard::class)]
    public function store() { /* POST /blog/create */ }
}</pre>

        <!-- ════════════ 7. THEME SYSTEM ════════════ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">7. Theme System</h2>
        <p style="color:#475569; line-height:1.8;">
            SPP auto-injects theme CSS from <code>etc/apps/{{APP_NAME}}/theme.yml</code>.
            The augmentation pipeline wraps your Blade output with theme stylesheets.
        </p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
# etc/apps/{{APP_NAME}}/theme.yml
theme:
  name: modern
  css:
    - /assets/css/theme.css
    - /assets/css/custom.css
  js:
    - /assets/js/app.js
  meta:
    viewport: "width=device-width, initial-scale=1"</pre>

        <!-- ════════════ 8. ENTITIES ════════════ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">8. Entities &amp; Admin Exposure</h2>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// src/{{APP_NAME}}/entities/Task.php
namespace App\{{APP_NAME}}\Entities;

use SPPMod\SPPDB\SPPEntity;

class Task extends SPPEntity
{
    protected string $table = '{{APP_NAME}}_tasks';
    protected bool $adminExpose = true;    // Auto-generates admin CRUD UI
    protected bool $apiExpose = true;      // Auto-generates REST API endpoints

    protected array $fields = [
        'title'       => ['type' => 'string', 'required' => true],
        'description' => ['type' => 'text'],
        'status'      => ['type' => 'enum', 'values' => ['pending','done']],
        'due_date'    => ['type' => 'date'],
    ];
}</pre>

        <!-- ════════════ 9. YAML FORMS IN BLADE ════════════ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">9. YAML Forms in Blade</h2>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
{{-- Simple: render the entire form --}}
@sppform('contact')

{{-- Advanced: custom layout with individual fields --}}
@sppform_start('registration')
    &lt;div class="row"&gt;
        &lt;div class="col"&gt;@sppelement('first_name', 'registration')&lt;/div&gt;
        &lt;div class="col"&gt;@sppelement('last_name', 'registration')&lt;/div&gt;
    &lt;/div&gt;
    @sppelement('email', 'registration')
    @sppelement('password', 'registration')
@sppform_end</pre>

        <!-- ════════════ 10. MIDDLEWARE ════════════ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">10. Middleware</h2>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// src/{{APP_NAME}}/middleware/AuthGuard.php
namespace App\{{APP_NAME}}\Middleware;

class AuthGuard
{
    public function handle($request, $next)
    {
        if (!\SPPMod\SPPAuth\SPPAuth::authSessionExists()) {
            header('Location: /login');
            exit;
        }
        return $next($request);
    }
}

# Register in etc/apps/{{APP_NAME}}/middleware.yml
middleware:
  auth: \App\{{APP_NAME}}\Middleware\AuthGuard
  csrf: \SPPMod\SPPView\Middleware\CsrfGuard</pre>

        <!-- ════════════ 11. EVENTS ════════════ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">11. Events from Blade Context</h2>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// Dispatch an event from a controller
\SPP\Events\SPPEvent::dispatch('user.registered', ['user' => $user]);

# Register listener in etc/apps/{{APP_NAME}}/events.yml
events:
  user.registered:
    - \App\{{APP_NAME}}\Events\SendWelcomeEmail
    - \App\{{APP_NAME}}\Events\LogRegistration</pre>

        <!-- ════════════ 12. ERROR PAGES ════════════ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">12. Error Handling &amp; Custom Pages</h2>
        <p style="color:#475569; line-height:1.8;">
            Custom error pages live in <code>src/{{APP_NAME}}/pages/errors/</code>.
            Routes are defined in pages.yml: <code>error/404</code> and <code>error/500</code>.
        </p>

        <!-- ════════════ 13. TESTING ════════════ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">13. Parikshak Testing</h2>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// Test a controller renders the correct view
use SPP\Testing\Parikshak;

class HomeControllerTest extends Parikshak
{
    /** @test */
    public function home_page_renders()
    {
        $controller = new \App\{{APP_NAME}}\Serv\HomeController();
        $html = $controller->index();
        $this->assertStringContains('Welcome', $html);
    }
}

// Run: php spp.php test:run --app={{APP_NAME}}</pre>

        <!-- ════════════ 14. CLI COMMANDS ════════════ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">14. CLI Commands for Blade</h2>
        <table style="width:100%; border-collapse:collapse; margin:1rem 0; font-size:0.82rem;">
            <tr style="background:#6366f1; color:#fff;"><th style="text-align:left; padding:0.7rem;">Command</th><th style="text-align:left; padding:0.7rem;">Description</th></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>php spp.php make:blade --app={{APP_NAME}} mypage</code></td><td style="padding:0.7rem;">Generate a new Blade view</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>php spp.php make:controller --app={{APP_NAME}} Blog</code></td><td style="padding:0.7rem;">Generate a controller class</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>php spp.php make:entity --app={{APP_NAME}} Task</code></td><td style="padding:0.7rem;">Generate a database entity</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>php spp.php make:middleware --app={{APP_NAME}} Auth</code></td><td style="padding:0.7rem;">Generate middleware class</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>php spp.php migrate --app={{APP_NAME}}</code></td><td style="padding:0.7rem;">Run database migrations</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>php spp.php cache:clear</code></td><td style="padding:0.7rem;">Clear compiled views and caches</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>php spp.php test:run --app={{APP_NAME}}</code></td><td style="padding:0.7rem;">Run Parikshak tests</td></tr>
        </table>

        <div style="margin-top:2rem; padding:1.5rem; background:#eff6ff; border-radius:10px; text-align:center;">
            <p style="color:#3b82f6; font-weight:600; margin:0;">Tip: Delete this guide page once you are comfortable!</p>
            <p style="color:#64748b; font-size:0.85rem; margin:0.5rem 0 0;">Remove the <code>'guide'</code> route from pages.yml and delete this file.</p>
        </div>

    </div>
</div>
@endsection
BLADEGUIDE
            ,
            $appName
        );
    }

    // =========================================================================
    //  GUIDE PAGE: API Mode — Headless REST Backend Tutorial
    // =========================================================================

    private function writeApiGuidePage(string $appName): void
    {
        // ── JSON documentation controller ──
        $ns = $appName;
        $this->writeFile(
            "src/{$appName}/serv/ApiDocsController.php",
            <<<PHP
<?php
namespace App\\{$ns}\\Serv;

/**
 * ============================================================================
 * ApiDocsController — Self-Documenting API Guide
 * ============================================================================
 *
 * This controller returns comprehensive JSON documentation for the {$ns} API.
 * It serves as both a machine-readable schema and a novice tutorial.
 *
 * Route: api/docs/json
 *   Returns JSON with endpoint descriptions, auth info, and examples.
 *
 * HOW TO EXTEND:
 *   Add new entries to the \\\$endpoints array as you build new API routes.
 * ============================================================================
 */
class ApiDocsController
{
    public function index()
    {
        header('Content-Type: application/json');
        echo json_encode([
            'api' => '{$ns} REST API',
            'version' => 'v1',
            'base_url' => '/api/v1',
            'tutorial' => [
                'what_is_api_mode' => 'API mode creates a headless REST backend. No HTML views — only JSON responses. Perfect for SPAs, mobile apps, and third-party integrations.',
                'conventions' => [
                    'GET /resource' => 'List all items (index)',
                    'GET /resource/{id}' => 'Get single item (show)',
                    'POST /resource' => 'Create new item (store)',
                    'PUT /resource/{id}' => 'Update item (update)',
                    'DELETE /resource/{id}' => 'Delete item (delete)',
                ],
                'status_codes' => [
                    200 => 'OK — Request succeeded',
                    201 => 'Created — Resource created',
                    400 => 'Bad Request — Invalid input',
                    401 => 'Unauthorized — Authentication required',
                    403 => 'Forbidden — Insufficient permissions',
                    404 => 'Not Found — Resource does not exist',
                    422 => 'Unprocessable — Validation failed',
                    429 => 'Too Many Requests — Rate limited',
                    500 => 'Server Error — Something went wrong',
                ],
                'authentication' => [
                    'methods' => ['Bearer Token (JWT)', 'API Key (header)'],
                    'header' => 'Authorization: Bearer <token>',
                    'api_key_header' => 'X-API-Key: <key>',
                    'generate_key' => 'php spp.php api:key:generate --app={$ns}',
                ],
                'error_format' => [
                    'status' => 'error',
                    'message' => 'Human-readable error message',
                    'errors' => ['field' => ['Validation message']],
                    'code' => 'MACHINE_READABLE_CODE',
                ],
                'pagination' => [
                    'query_params' => '?page=1&per_page=25',
                    'response_meta' => ['total', 'page', 'per_page', 'last_page'],
                ],
                'rate_limiting' => [
                    'default' => '60 requests/minute',
                    'headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'],
                ],
                'cors' => [
                    'headers_sent' => ['Access-Control-Allow-Origin', 'Access-Control-Allow-Methods', 'Access-Control-Allow-Headers'],
                    'configure' => 'Set in init.php or middleware',
                ],
            ],
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/v1/items', 'description' => 'List all items', 'auth' => false, 'params' => ['page' => 'int', 'per_page' => 'int']],
                ['method' => 'GET', 'path' => '/api/v1/items/{id}', 'description' => 'Get single item', 'auth' => false, 'params' => ['id' => 'int (required)']],
                ['method' => 'POST', 'path' => '/api/v1/items', 'description' => 'Create new item', 'auth' => true, 'body' => ['name' => 'string (required)', 'status' => 'string']],
                ['method' => 'POST', 'path' => '/api/auth/login', 'description' => 'Authenticate and get token', 'auth' => false, 'body' => ['username' => 'string', 'password' => 'string']],
                ['method' => 'GET', 'path' => '/api/docs', 'description' => 'API JSON documentation', 'auth' => false],
                ['method' => 'GET', 'path' => '/api/docs/html', 'description' => 'API HTML documentation page', 'auth' => false],
            ],
            'cli_commands' => [
                'php spp.php api:key:generate --app={$ns}' => 'Generate a new API key',
                'php spp.php api:route:list --app={$ns}' => 'List all API routes',
                'php spp.php make:entity --app={$ns} Resource' => 'Create a new entity with auto-API',
                'php spp.php test:run --app={$ns}' => 'Run Parikshak API tests',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
PHP
        );

        // ── Styled HTML API docs page ──
        $this->writeFile(
            "src/{$appName}/pages/api-docs.php",
            <<<'APIDOCSHTML'
<?php
/**
 * ============================================================================
 * {{APP_NAME}} — API Documentation (HTML)
 * ============================================================================
 *
 * This page renders a styled, human-readable API guide for your REST backend.
 * It is mapped in pages.yml as a special:1 page (standalone HTML).
 *
 * TIP: Delete this file once your API is documented elsewhere!
 * ============================================================================
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{APP_NAME}} — API Documentation</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #0f172a; --surface: #1e293b; --border: #334155; --text: #e2e8f0; --muted: #94a3b8; --primary: #818cf8; --accent: #34d399; --warning: #fbbf24; --error: #f87171; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); }
        .container { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem; }
        .header { text-align: center; padding: 2rem 0; border-bottom: 1px solid var(--border); margin-bottom: 2rem; }
        .header .badge { display: inline-block; background: rgba(129,140,248,0.15); color: var(--primary); padding: 6px 16px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em; border: 1px solid rgba(129,140,248,0.2); margin-bottom: 1rem; }
        .header h1 { font-size: 2.2rem; font-weight: 800; }
        .header p { color: var(--muted); margin-top: 0.5rem; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 2rem; margin-bottom: 1.5rem; }
        .card h2 { color: var(--primary); font-size: 1.2rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border); }
        .card h3 { color: var(--accent); margin: 1.2rem 0 0.5rem; font-size: 1rem; }
        .card p { color: var(--muted); margin: 0.4rem 0; line-height: 1.7; }
        .card ul, .card ol { padding-left: 1.5rem; color: var(--muted); }
        .card li { margin: 0.3rem 0; }
        .card b, .card strong { color: var(--text); }
        code { font-family: 'JetBrains Mono', monospace; background: #0f172a; padding: 2px 6px; border-radius: 4px; font-size: 0.85em; color: var(--accent); border: 1px solid var(--border); }
        pre { background: #0f172a; border: 1px solid var(--border); border-radius: 8px; padding: 1rem; overflow-x: auto; font-family: 'JetBrains Mono', monospace; font-size: 0.82rem; line-height: 1.7; color: var(--text); margin: 0.8rem 0; }
        pre code { background: none; border: none; padding: 0; color: inherit; }
        table { width: 100%; border-collapse: collapse; margin: 0.8rem 0; font-size: 0.85rem; }
        th { background: rgba(129,140,248,0.15); color: var(--primary); text-align: left; padding: 0.6rem 0.8rem; }
        td { padding: 0.6rem 0.8rem; border-bottom: 1px solid var(--border); color: var(--muted); }
        .method { display: inline-block; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem; }
        .method-get { background: rgba(52,211,153,0.15); color: var(--accent); }
        .method-post { background: rgba(129,140,248,0.15); color: var(--primary); }
        .method-put { background: rgba(251,191,36,0.15); color: var(--warning); }
        .method-delete { background: rgba(248,113,113,0.15); color: var(--error); }
        .tip { background: rgba(52,211,153,0.08); border-left: 3px solid var(--accent); padding: 0.8rem 1rem; border-radius: 0 8px 8px 0; margin: 1rem 0; }
        .footer { text-align: center; padding: 2rem; color: var(--muted); font-size: 0.85rem; border-top: 1px solid var(--border); margin-top: 2rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="badge">API MODE GUIDE</div>
        <h1>{{APP_NAME}} REST API</h1>
        <p>Comprehensive API documentation and novice tutorial</p>
    </div>

    <!-- 1. What is API Mode? -->
    <div class="card">
        <h2>1. What is API Mode?</h2>
        <p><strong>API mode</strong> creates a headless REST backend. There are no HTML views &mdash; your application only returns JSON responses. This is ideal for:</p>
        <ul>
            <li>Single Page Applications (SPAs) that consume your API</li>
            <li>Mobile apps (iOS/Android) that need a backend</li>
            <li>Third-party integrations and webhooks</li>
            <li>Microservice architectures</li>
        </ul>
    </div>

    <!-- 2. Endpoints -->
    <div class="card">
        <h2>2. API Endpoints</h2>
        <table>
            <tr><th>Method</th><th>Path</th><th>Description</th><th>Auth</th></tr>
            <tr><td><span class="method method-get">GET</span></td><td><code>/api/v1/items</code></td><td>List all items</td><td>No</td></tr>
            <tr><td><span class="method method-get">GET</span></td><td><code>/api/v1/items/{id}</code></td><td>Get single item</td><td>No</td></tr>
            <tr><td><span class="method method-post">POST</span></td><td><code>/api/v1/items</code></td><td>Create new item</td><td>Yes</td></tr>
            <tr><td><span class="method method-put">PUT</span></td><td><code>/api/v1/items/{id}</code></td><td>Update item</td><td>Yes</td></tr>
            <tr><td><span class="method method-delete">DELETE</span></td><td><code>/api/v1/items/{id}</code></td><td>Delete item</td><td>Yes</td></tr>
            <tr><td><span class="method method-post">POST</span></td><td><code>/api/auth/login</code></td><td>Authenticate</td><td>No</td></tr>
        </table>
    </div>

    <!-- 3. ApiController -->
    <div class="card">
        <h2>3. ApiController Structure</h2>
        <pre><code>namespace App\{{APP_NAME}}\Serv;

class ApiController
{
    // GET /api/v1/items
    public function index()
    {
        header('Content-Type: application/json');
        $items = []; // Query from database
        echo json_encode(['status' => 'ok', 'data' => $items]);
    }

    // GET /api/v1/items/{id}
    public function show()
    {
        $pageData = \SPPMod\SPPView\SPPGlobal::get('page');
        $id = $pageData['params'][0] ?? null;
        // ... validate and return
    }

    // POST /api/v1/items
    public function store()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        // ... validate, create, and return 201
    }

    // PUT /api/v1/items/{id}
    public function update()
    {
        // ... validate, update, and return 200
    }

    // DELETE /api/v1/items/{id}
    public function delete()
    {
        // ... validate, delete, and return 200
    }
}</code></pre>
    </div>

    <!-- 4. Authentication -->
    <div class="card">
        <h2>4. Authentication</h2>
        <h3>JWT / Bearer Tokens</h3>
        <pre><code>// Request:
POST /api/auth/login
Content-Type: application/json
{"username": "admin", "password": "secret"}

// Response:
{"status": "ok", "token": "eyJhbGciOiJIUzI1NiIs..."}

// Use token in subsequent requests:
GET /api/v1/items
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...</code></pre>

        <h3>API Keys</h3>
        <pre><code># Generate an API key via CLI:
php spp.php api:key:generate --app={{APP_NAME}}

# Use in requests:
GET /api/v1/items
X-API-Key: your_generated_key_here</code></pre>
    </div>

    <!-- 5. Response Patterns -->
    <div class="card">
        <h2>5. JSON Response Patterns</h2>
        <pre><code>// Success (single item):
{"status": "ok", "data": {"id": 1, "name": "Item"}}

// Success (list with pagination):
{"status": "ok", "data": [...], "meta": {"total": 50, "page": 1, "per_page": 25, "last_page": 2}}

// Error:
{"status": "error", "message": "Validation failed", "errors": {"name": ["Name is required"]}}

// 401 Unauthorized:
{"status": "error", "message": "Authentication required", "code": "AUTH_REQUIRED"}</code></pre>
    </div>

    <!-- 6. Entity Auto-API -->
    <div class="card">
        <h2>6. Entity Auto-API ($apiExpose)</h2>
        <pre><code>// When $apiExpose = true in your entity, SPP auto-generates:
//   GET    /api/v1/{entity}        → List
//   GET    /api/v1/{entity}/{id}   → Show
//   POST   /api/v1/{entity}        → Create
//   PUT    /api/v1/{entity}/{id}   → Update
//   DELETE /api/v1/{entity}/{id}   → Delete

class Task extends SPPEntity
{
    protected bool $apiExpose = true;  // ← This is all you need!
}</code></pre>
        <div class="tip"><strong>Tip:</strong> Use <code>php spp.php make:entity --app={{APP_NAME}} Task</code> to generate an entity, then set <code>$apiExpose = true</code>.</div>
    </div>

    <!-- 7. Request Validation -->
    <div class="card">
        <h2>7. Request Validation</h2>
        <pre><code>public function store()
{
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $errors = [];
    if (empty($input['name'])) $errors['name'][] = 'Name is required';
    if (empty($input['email'])) $errors['email'][] = 'Email is required';
    if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'][] = 'Invalid email format';
    }

    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Validation failed', 'errors' => $errors]);
        return;
    }

    // Process valid input...
}</code></pre>
    </div>

    <!-- 8. Versioning & CORS -->
    <div class="card">
        <h2>8. Versioning, CORS &amp; Rate Limiting</h2>
        <pre><code>// API Versioning: Use path prefix
// /api/v1/items  → Version 1
// /api/v2/items  → Version 2

// CORS headers (set in init.php or middleware):
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Rate Limiting headers in response:
// X-RateLimit-Limit: 60
// X-RateLimit-Remaining: 45
// X-RateLimit-Reset: 1625097600</code></pre>
    </div>

    <!-- 9. Testing -->
    <div class="card">
        <h2>9. Testing APIs with Parikshak</h2>
        <pre><code>use SPP\Testing\Parikshak;
use SPP\Testing\Traits\InteractsWithApi;

class ApiTest extends Parikshak
{
    use InteractsWithApi;

    /** @test */
    public function list_items_returns_json()
    {
        $response = $this-&gt;apiGet('/api/v1/items');
        $this-&gt;assertStatusCode(200);
        $this-&gt;assertJsonStructure(['status', 'data']);
    }

    /** @test */
    public function create_requires_auth()
    {
        $response = $this-&gt;apiPost('/api/v1/items', ['name' => 'Test']);
        $this-&gt;assertStatusCode(401);
    }
}

// Run: php spp.php test:run --app={{APP_NAME}}</code></pre>
    </div>

    <!-- 10. CLI -->
    <div class="card">
        <h2>10. CLI Commands</h2>
        <table>
            <tr><th>Command</th><th>Description</th></tr>
            <tr><td><code>php spp.php api:key:generate --app={{APP_NAME}}</code></td><td>Generate a new API key</td></tr>
            <tr><td><code>php spp.php api:route:list --app={{APP_NAME}}</code></td><td>List all registered API routes</td></tr>
            <tr><td><code>php spp.php make:entity --app={{APP_NAME}} Resource</code></td><td>Create entity with auto-API</td></tr>
            <tr><td><code>php spp.php make:controller --app={{APP_NAME}} Items</code></td><td>Create API controller</td></tr>
            <tr><td><code>php spp.php test:run --app={{APP_NAME}}</code></td><td>Run Parikshak tests</td></tr>
            <tr><td><code>php spp.php cache:clear</code></td><td>Clear caches</td></tr>
        </table>
    </div>

    <div class="footer">
        &copy; {{APP_NAME}} &bull; Powered by SPP Framework &bull; REST API Mode
    </div>
</div>
</body>
</html>
APIDOCSHTML
            ,
            $appName
        );
    }

    // =========================================================================
    //  GUIDE PAGE: Drop-in Mode — Low-Code Tutorial
    // =========================================================================

    private function writeDropinGuidePage(string $appName): void
    {
        $viewsDir = SPP_APP_DIR . "/resources/{$appName}/views";
        if (!is_dir($viewsDir))
            mkdir($viewsDir, 0777, true);

        $this->writeFile(
            "resources/{$appName}/views/guide.html",
            <<<'DROPINGUIDE'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{APP_NAME}} — Drop-in Mode Guide</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f8fafc; --primary: #6366f1; --text: #0f172a; --muted: #64748b; --surface: #fff; --border: #e2e8f0; --accent: #22c55e; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text); }
        .container { max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem; }
        .card { background: var(--surface); border-radius: 16px; padding: 2.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid var(--border); margin-bottom: 1.5rem; }
        .badge { display: inline-block; background: #e0e7ff; color: #4f46e5; padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em; margin-bottom: 1rem; }
        h1 { font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; }
        h2 { color: var(--primary); margin-top: 0; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border); font-size: 1.2rem; }
        h3 { color: var(--primary); margin: 1.2rem 0 0.5rem; font-size: 1rem; }
        p { color: var(--muted); line-height: 1.8; margin: 0.5rem 0; }
        b, strong { color: var(--text); }
        code { background: #f1f5f9; padding: 2px 8px; border-radius: 6px; font-size: 0.9em; }
        pre { background: #0f172a; color: #e2e8f0; padding: 1.2rem; border-radius: 10px; overflow-x: auto; font-size: 0.82rem; line-height: 1.7; margin: 0.8rem 0; }
        pre code { background: none; padding: 0; color: inherit; }
        ul, ol { color: var(--muted); padding-left: 1.5rem; margin: 0.5rem 0; line-height: 2; }
        .tip { background: #f0fdf4; border-left: 4px solid var(--accent); padding: 1rem 1.2rem; border-radius: 0 10px 10px 0; margin: 1rem 0; }
        .warning { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 1rem 1.2rem; border-radius: 0 10px 10px 0; margin: 1rem 0; }
        table { width: 100%; border-collapse: collapse; margin: 0.8rem 0; font-size: 0.85rem; }
        th { background: var(--primary); color: #fff; text-align: left; padding: 0.6rem 0.8rem; }
        td { padding: 0.6rem 0.8rem; border-bottom: 1px solid var(--border); color: var(--muted); }
        .footer { text-align: center; margin-top: 2rem; font-size: 0.85rem; color: var(--muted); }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="badge">DROP-IN MODE GUIDE</div>
        <h1>{{APP_NAME}} — Drop-in Mode Tutorial</h1>
        <p>The simplest SPP app mode. Self-contained PHP/HTML files with zero configuration.</p>
    </div>

    <!-- 1. What is Drop-in Mode? -->
    <div class="card">
        <h2>1. What is Drop-in Mode?</h2>
        <p><strong>Drop-in mode</strong> is the simplest SPP application mode. It's a self-contained PHP/HTML entry point with a mini-router that serves files from a views directory. Think of it as "PHP files with SPP superpowers."</p>
        <p>Key characteristics:</p>
        <ul>
            <li><strong>No controllers</strong> — pages are plain PHP/HTML files</li>
            <li><strong>No Blade templates</strong> — just regular PHP</li>
            <li><strong>Special:1 mode</strong> — bypasses the augmentation pipeline</li>
            <li><strong>SPP is booted</strong> — you have access to sessions, config, events, and all SPP APIs</li>
            <li><strong>YAML forms</strong> — form processing still works via ViewPage::processForms()</li>
        </ul>
    </div>

    <!-- 2. Mini-Router Pattern -->
    <div class="card">
        <h2>2. The Mini-Router Pattern</h2>
        <p>Your <code>src/{{APP_NAME}}/index.php</code> contains a simple router that maps URL parameters to files:</p>
        <pre><code>// src/{{APP_NAME}}/index.php

// SPP framework is ALREADY booted — no require needed!

// Process YAML forms
if (class_exists('\SPPMod\SPPView\ViewPage')) {
    \SPPMod\SPPView\ViewPage::processForms();
}

// Simple router — maps ?page=xxx to a file
$page = $_GET['page'] ?? 'index';
$page = preg_replace('/[^a-zA-Z0-9_\-]/', '', $page);  // Sanitize!

$viewsDir = SPP_APP_DIR . '/resources/{{APP_NAME}}/views/';

if (file_exists($viewsDir . $page . '.php')) {
    include $viewsDir . $page . '.php';
} elseif (file_exists($viewsDir . $page . '.html')) {
    echo file_get_contents($viewsDir . $page . '.html');
} else {
    echo "&lt;h1&gt;404 — Page Not Found&lt;/h1&gt;";
}</code></pre>
    </div>

    <!-- 3. Adding New Pages -->
    <div class="card">
        <h2>3. Adding New Pages</h2>
        <p>To add a new page, simply create a file in <code>resources/{{APP_NAME}}/views/</code>:</p>
        <pre><code>resources/{{APP_NAME}}/views/
├── index.html       ← Home page (loaded by default)
├── guide.html       ← This guide page
├── about.php        ← PHP page with dynamic content
├── contact.php      ← Contact form page
└── gallery.html     ← Static HTML page</code></pre>
        <p>Access pages via URL parameter:</p>
            <li><code>/index</code> → loads <code>index.html</code> (default)</li>
            <li><code>/index?page=about</code> → loads <code>about.php</code></li>
            <li><code>/index?page=contact</code> → loads <code>contact.php</code></li>
            <li><code>/index?page=guide</code> → loads <code>guide.html</code> (this page)</li>
        </ul>
    </div>

    <!-- 4. Using SPP Core APIs -->
    <div class="card">
        <h2>4. Available SPP Core APIs</h2>
        <p>Even in drop-in mode, the full SPP framework is booted. You can use:</p>
        <pre><code>// SESSION — Store/retrieve session data
$session = \SPP\Session\SPPSession::getInstance();
$session->set('user_name', 'Alice');
$name = $session->get('user_name');

// CONFIG — Read framework/app configuration
$config = \SPP\Config\SPPConfig::get('app.name');
$dbHost = \SPP\Config\SPPConfig::get('database.host');

// EVENTS — Dispatch and listen to events
\SPP\Events\SPPEvent::dispatch('page.viewed', ['page' => $page]);

// LOGGING — Write to log files
\SPP\Logger\SPPLog::info('Page loaded: ' . $page);
\SPP\Logger\SPPLog::error('Something went wrong');

// BASE URL — Get your app's base URL
$baseUrl = \SPP\App::getBaseUrl('{{APP_NAME}}');

// DATABASE (if SPPDB module is available)
// $db = new \SPPMod\SPPDB\SPPDB();
// $results = $db->execute_query('SELECT * FROM my_table');</code></pre>
    </div>

    <!-- 5. When to Use Drop-in -->
    <div class="card">
        <h2>5. When to Use Drop-in vs Other Modes</h2>
        <table>
            <tr><th>Scenario</th><th>Best Mode</th><th>Why</th></tr>
            <tr><td>Quick prototype / landing page</td><td><strong>dropin</strong></td><td>Fastest setup, minimal files</td></tr>
            <tr><td>Content site with templates</td><td>blade</td><td>Layouts, partials, directives</td></tr>
            <tr><td>Interactive SPA dashboard</td><td>sppux</td><td>Reactive components, stores</td></tr>
            <tr><td>Full-featured web app</td><td>mixed</td><td>All paradigms combined</td></tr>
            <tr><td>Mobile app backend</td><td>api</td><td>JSON-only, no HTML</td></tr>
            <tr><td>Simple PHP pages with themes</td><td>native</td><td>PHP pages + augmentation</td></tr>
        </table>
    </div>

    <!-- 6. Upgrading -->
    <div class="card">
        <h2>6. Upgrading to Native or Mixed Mode</h2>
        <p>When your drop-in app outgrows its simplicity, upgrade:</p>
        <h3>Step 1: Edit pages.yml</h3>
        <pre><code># etc/apps/{{APP_NAME}}/pages.yml
# BEFORE (drop-in):
pages:
  index:
    url: index.php
    special: 1          ← Remove this line

# AFTER (native):
pages:
  index:
    url: pages/index.php    ← Move to pages/ directory
  about:
    url: pages/about.php
  contact:
    url: pages/contact.php</code></pre>
        <h3>Step 2: Move files</h3>
        <pre><code># Create pages directory
mkdir src/{{APP_NAME}}/pages/

# Move your views to pages (as PHP files)
# Each file now gets theme augmentation (CSS/JS injection)</code></pre>
        <h3>Step 3: For Mixed mode, add controllers</h3>
        <pre><code># Add controller routes in pages.yml:
pages:
  home:
    controller: \App\{{APP_NAME}}\Serv\HomeController@index</code></pre>
        <div class="tip"><strong>Tip:</strong> You can upgrade incrementally. Start with native mode (add more pages.yml routes), then add controllers for Blade, then add SPP-UX components.</div>
    </div>

    <!-- 7. SPP Framework Already Booted -->
    <div class="card">
        <h2>7. How SPP Framework is Already Booted</h2>
        <p>When a request reaches your drop-in app, SPP has already:</p>
        <ol>
            <li><strong>Loaded <code>sppinit.php</code></strong> — Framework core is initialized</li>
            <li><strong>Read <code>global-settings.yml</code></strong> — Your app is registered</li>
            <li><strong>Set app context</strong> — <code>SPP_APP_DIR</code>, base URL, etc. are available</li>
            <li><strong>Started session</strong> — PHP session is active</li>
            <li><strong>Loaded modules</strong> — SPPDB, SPPAuth, etc. are ready (if installed)</li>
            <li><strong>Matched route</strong> — ViewRouter found your <code>index</code> route (special:1)</li>
            <li><strong>Included your <code>index.php</code></strong> — Your code runs with full framework access</li>
        </ol>
        <div class="warning"><strong>Never do these in drop-in mode:</strong><br>
        ❌ <code>require 'sppinit.php'</code> — already loaded<br>
        ❌ <code>\SPP\App::getApp()</code> to set context — already set<br>
        ❌ <code>session_start()</code> — already started by SPP</div>
    </div>

    <div class="footer">
        &copy; {{APP_NAME}} &bull; Powered by SPP Framework &bull; Drop-in Mode<br>
        <small>Access this guide at: <code>/index?page=guide</code></small>
    </div>
</div>
</body>
</html>
DROPINGUIDE
            ,
            $appName
        );
    }

    // ── YAML Forms ──

    private function writeContactForm(string $appName): void
    {
        $this->writeFile(
            "etc/apps/{$appName}/forms/contact.yml",
            <<<YAML
################################################################################
# Contact Form Definition — {$appName}
#
# HOW THIS WORKS:
# YAML forms are automatically processed by SPPView when a POST contains
# the hidden field: <input type="hidden" name="spp_form_id" value="contact">
#
# FIELD TYPES: text, email, textarea, select, checkbox, radio, hidden, number
# VALIDATION:  required, min:N, max:N, email, numeric, regex:pattern
#
# HOW TO ADD A NEW FORM:
# 1. Create a new YAML file here (e.g., feedback.yml)
# 2. Add <input type="hidden" name="spp_form_id" value="feedback"> to your HTML
# 3. Or use the Blade directive: @sppform('feedback')
################################################################################

id: contact
public_name: "Contact Form"
success_message: "Thank you! Your message has been received."
redirect_to: "contact"

fields:
  guest_name:
    label: "Your Name"
    type: text
    required: true
    validation: "min:2"
    placeholder: "John Doe"
  email:
    label: "Email Address"
    type: email
    required: false
    placeholder: "you@example.com"
  message:
    label: "Message"
    type: textarea
    required: true
    validation: "min:10"
    placeholder: "Tell us what's on your mind..."
YAML
        );
    }

    // =========================================================================
    //  COMMON STRUCTURE (shared by ALL modes)
    // =========================================================================

    private function scaffoldCommonStructure(string $appName, string $appType): void
    {
        echo "\n  ── Common Structure ──\n";

        $baseDir = SPP_APP_DIR . '/src/' . $appName;

        // ── Extended directories ──
        $extraDirs = [
            $baseDir . '/pages',
            $baseDir . '/serv',
            $baseDir . '/components',
            $baseDir . '/assets',
            $baseDir . '/assets/js',
            $baseDir . '/assets/css',
            $baseDir . '/assets/img',
            $baseDir . '/resources/views',
            $baseDir . '/resources/themes/default',
            $baseDir . '/comp',
            $baseDir . '/modules',
        ];

        foreach ($extraDirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }

        // ── Logo asset copy ──
        $sourceLogo = SPP_APP_DIR . '/res/spp/images/logo.jpg';
        $targetLogo = $baseDir . '/assets/logo.jpg';
        if (file_exists($sourceLogo) && !file_exists($targetLogo)) {
            @copy($sourceLogo, $targetLogo);
        }

        // ── init.php (app boot) ──
        $this->writeFile(
            "src/{$appName}/init.php",
            <<<PHP
<?php
/**
 * ============================================================================
 * Application Boot — {$appName}
 * ============================================================================
 *
 * HOW THIS WORKS:
 * This file is loaded automatically by the SPP framework during bootstrap
 * when the '{$appName}' application context is active. It runs ONCE per request.
 *
 * USE THIS FOR:
 *   - Registering autoloaders for app-specific namespaces
 *   - Registering event listeners
 *   - Setting up custom middleware
 *   - Configuring services
 *
 * DO NOT USE FOR:
 *   - Output (echo/print) — this runs before any rendering
 *   - Heavy processing — keep boot fast
 * ============================================================================
 */

// ── App-Specific Autoloader ─────────────────────────────────────────────
// Maps the \\App\\{$appName}\\ namespace to this app's directory.
// This allows you to use classes like \\App\\{$appName}\\Serv\\HomeController
spl_autoload_register(function (\$className) {
    // Only handle our app namespace
    \$prefix = 'App\\\\{$appName}\\\\';
    if (strpos(\$className, \$prefix) !== 0) return;

    \$relative = substr(\$className, strlen(\$prefix));
    \$parts = explode('\\\\', \$relative);

    // Map namespace to directory (Serv → serv, Entities → entities, etc.)
    \$file = __DIR__ . '/' . strtolower(\$parts[0]);
    if (count(\$parts) > 1) {
        \$file .= '/' . implode('/', array_slice(\$parts, 1));
    }
    \$file .= '.php';

    if (file_exists(\$file)) {
        require_once \$file;
    }
});

// ── Event Registration ──────────────────────────────────────────────────
// Register event listeners for framework lifecycle events.
// Uncomment to activate:
//
// \\SPP\\SPPEvent::listen('PageNotFound', function (\$params) {
//     // Custom 404 handling
//     echo "<h1>Page not found in {$appName}</h1>";
// });
//
// \\SPP\\SPPEvent::listen('app.boot', function (\$params) {
//     // Runs when app context is set
// });

// ── SPP-UX Auto-Boot ────────────────────────────────────────────────────
// Automatically register SPP-UX assets for all pages in this app.
// This means @sppux directives and SPPUX::render() work on any page.
if (php_sapi_name() !== 'cli' && class_exists('\\\\SPPMod\\\\Drishyam\\\\SPPUX')) {
    \\SPPMod\\Drishyam\\SPPUX::boot('{$appName}');
}

// ── Dynamic Asset Inclusion ───────────────────────────────────
// Automatically injects mapped CSS and JS assets from module.yml
// configurations via the secure AssetRouter alias system.
if (php_sapi_name() !== 'cli') {
    \\\\SPP\\\\App::includeAssets();
}
PHP
        );

        // ── etc/config.yml ──
        $this->writeFile(
            "src/{$appName}/etc/config.yml",
            <<<YAML
################################################################################
# Application Runtime Configuration — {$appName}
#
# Values here can be read via:
#   \SPP\App::getAppConf('key_name', '{$appName}')
################################################################################

app_name: "{$appName}"
app_type: "{$appType}"
default_layout: "premium-glass"

# Asset directories (relative to app src)
assets:
  theme-assets: "resources/themes"
  app-assets: "assets"

# Uncomment to enable features:
# debug: true
# cache: false
# auth_required: true
YAML
        );

        // ── etc/routes.yml ──
        $this->writeFile(
            "src/{$appName}/etc/routes.yml",
            <<<YAML
################################################################################
# Application Isolated Routes
# These routes are discovered by SPPRouter when scanning modules.
# They supplement (not replace) the main etc/apps/{$appName}/pages.yml
################################################################################

routes:
  - path: "login"
    target: "\\App\\{$appName}\\Serv\\AuthController@loginForm"
    type: "controller"

  - path: "logout"
    target: "\\App\\{$appName}\\Serv\\AuthController@logout"
    type: "controller"
YAML
        );

        // ── etc/services.yml ──
        $this->writeFile(
            "src/{$appName}/etc/services.yml",
            <<<YAML
################################################################################
# App Services — Callable from JavaScript via spp_admin.callAppService()
#
# HOW TO ADD A SERVICE:
# 1. Create a PHP script in serv/ (e.g., serv/my_action.php)
# 2. Register it here with a unique name
# 3. Call from JS: await spp_admin.callAppService('my.action', {param: 'value'})
#
# The PHP script receives POST data and should set \$response array:
#   \$response = ['status' => 'success', 'data' => [...]]
################################################################################

services:
  - name: "task.create"
    script: "src/{$appName}/serv/task_create.php"
    method: "POST"
YAML
        );

        // ── etc/app.yml ──
        $appNameLower = strtolower($appName);
        $this->writeFile(
            "src/{$appName}/etc/app.yml",
            <<<YAML
# Self-contained application descriptor
# Discovered automatically by SPP framework
base_url: "/{$appNameLower}"
table_prefix: "{$appName}_"
type: "{$appType}"
shared_group: "core"
YAML
        );

        // ── Theme manifest ──
        $this->writeFile(
            "src/{$appName}/resources/themes/default/theme.yml",
            <<<YAML
name: "default"
version: "1.0.0"
description: "Default theme for {$appName}"
YAML
        );

        // ── Theme CSS ──
        $this->writeFile(
            "src/{$appName}/resources/themes/default/custom.css",
            <<<CSS
/*
 * Custom Theme CSS — {$appName}
 *
 * Override SPP-UX CSS variables here for custom branding.
 * These override the built-in theme presets.
 *
 * Available variables (see spp/modules/spp/drishyam/css/sppux.css for full list):
 *   --sppux-primary: #6366f1;
 *   --sppux-primary-glow: rgba(99, 102, 241, 0.4);
 *   --sppux-bg: #0f0f23;
 *   --sppux-panel: rgba(30, 30, 60, 0.8);
 *   --sppux-text: #e2e8f0;
 *   --sppux-border: rgba(255, 255, 255, 0.08);
 */

/* Uncomment to customize:
:root {
    --sppux-primary: #f43f5e;
    --sppux-primary-glow: rgba(244, 63, 94, 0.4);
}
*/
CSS
        );

        // ── Login view ──
        $this->writeFile(
            "src/{$appName}/resources/views/login.blade.php",
            <<<'BLADE'
{{--
  Login Page — Uses @sppguest to show only to non-authenticated users
--}}
@extends('layouts.app')
@section('title', 'Login')
@section('content')
    @sppguest
    <div style="max-width: 400px; margin: 2rem auto;">
        <div class="card">
            <h2 style="margin-top:0;">🔐 Login</h2>
            @if(!empty($error))
                <div style="background: rgba(239,68,68,0.1); color: #dc2626; padding: 0.8rem; border-radius: 8px; margin-bottom: 1rem;">{{ $error }}</div>
            @endif
            <form method="POST" action="@url('auth/login')">
                <div style="margin-bottom: 1rem;">
                    <label style="display:block; font-weight:600; font-size:0.85rem; margin-bottom:0.4rem; color:var(--muted);">Username</label>
                    <input type="text" name="username" required style="width:100%; padding:0.8rem; border:1px solid var(--border); border-radius:8px; font-family:inherit;">
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label style="display:block; font-weight:600; font-size:0.85rem; margin-bottom:0.4rem; color:var(--muted);">Password</label>
                    <input type="password" name="password" required style="width:100%; padding:0.8rem; border:1px solid var(--border); border-radius:8px; font-family:inherit;">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Sign In</button>
            </form>
            <p style="margin-top:1rem; font-size:0.85rem; text-align:center;">Default: admin / admin</p>
        </div>
    </div>
    @endsppguest

    @sppauth
    <div class="card" style="text-align:center;">
        <h2>✅ You are already logged in</h2>
        <a href="@url('dashboard')" class="btn btn-primary">Go to Dashboard</a>
    </div>
    @endsppauth
@endsection
BLADE
            ,
            $appName
        );

        // ── AuthController ──
        $this->writeFile(
            "src/{$appName}/serv/AuthController.php",
            <<<PHP
<?php
namespace App\\{$appName}\\Serv;

/**
 * ============================================================================
 * AuthController — Login / Logout with SPPAuth
 * ============================================================================
 *
 * HOW AUTHENTICATION WORKS:
 * SPPAuth provides session-based authentication with guards.
 * Guards define different auth strategies (web, api, etc.)
 *
 * KEY METHODS:
 *   SPPAuth::guard('web')->login(\$user)        — Create session
 *   SPPAuth::guard('web')->logout()             — Destroy session
 *   SPPAuth::authSessionExists()                — Check if logged in
 *   SPPAuth::guard('web')->user()               — Get current user
 *
 * IN BLADE TEMPLATES:
 *   @sppauth ... @endsppauth                   — Show if authenticated
 *   @sppguest ... @endsppguest                 — Show if guest
 * ============================================================================
 */
class AuthController
{
    public function loginForm()
    {
        \$blade = \\SPPMod\\Drishyam\\SPPBlade::getInstance();
        return \$blade->run('login', [
            'app_name' => '{$appName}',
            'base_url' => \\SPP\\App::getBaseUrl('{$appName}'),
            'error' => ''
        ]);
    }

    public function login()
    {
        \$error = '';
        if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
            \$username = trim(\$_POST['username'] ?? '');
            \$password = \$_POST['password'] ?? '';

            if (!empty(\$username) && !empty(\$password)) {
                try {
                    // Demo credential check — replace with real auth
                    if (\$username === 'admin' && (\$password === 'admin' || \$password === 'password')) {
                        \$user = (object)['id' => 'admin', 'username' => 'admin', 'email' => 'admin@localhost'];
                        \\SPPMod\\SPPAuth\\SPPAuth::guard('web')->login(\$user);
                        header('Location: ' . \\SPP\\App::url('dashboard', '{$appName}'));
                        exit;
                    } else {
                        \$error = 'Invalid credentials.';
                    }
                } catch (\\Exception \$e) {
                    \$error = 'Auth error: ' . \$e->getMessage();
                }
            }
        }

        \$blade = \\SPPMod\\Drishyam\\SPPBlade::getInstance();
        return \$blade->run('login', [
            'app_name' => '{$appName}',
            'base_url' => \\SPP\\App::getBaseUrl('{$appName}'),
            'error' => \$error
        ]);
    }

    public function logout()
    {
        if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
            \\SPPMod\\SPPAuth\\SPPAuth::guard('web')->logout();
        }
        header('Location: ' . \\SPP\\App::url('home', '{$appName}'));
        exit;
    }

    /**
     * API Login — returns JSON token (for API mode)
     */
    public function apiLogin()
    {
        header('Content-Type: application/json');
        if (\$_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'POST required']);
            return;
        }

        \$username = \$_POST['username'] ?? '';
        \$password = \$_POST['password'] ?? '';

        // Demo auth — replace with real validation
        if (\$username === 'admin' && \$password === 'admin') {
            echo json_encode([
                'status' => 'ok',
                'token' => bin2hex(random_bytes(32)),
                'user' => ['id' => 1, 'username' => 'admin']
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
        }
    }
}
PHP
        );

        // ── Sample service: task_create.php ──
        $this->writeFile(
            "src/{$appName}/serv/task_create.php",
            <<<PHP
<?php
/**
 * ============================================================================
 * Service: task.create — {{APP_NAME}}
 * ============================================================================
 *
 * HOW THIS WORKS:
 * This script is registered in etc/services.yml and callable from JavaScript:
 *   const result = await spp_admin.callAppService('task.create', { taskTitle: 'My Task' });
 *
 * INPUT: POST data (available via \$_POST)
 * OUTPUT: Set \$response array — the framework auto-encodes it as JSON
 *
 * HOW TO CREATE A NEW SERVICE:
 * 1. Create a PHP file in serv/ (e.g., serv/my_service.php)
 * 2. Register in etc/services.yml:
 *    services:
 *      - name: "my.service"
 *        script: "src/{{APP_NAME}}/serv/my_service.php"
 * 3. Call from JS: await spp_admin.callAppService('my.service', {param: 'value'})
 * ============================================================================
 */

\$taskTitle = trim(\$_POST['taskTitle'] ?? 'Untitled Task');
\$taskPriority = trim(\$_POST['taskPriority'] ?? 'Normal');

// Simulate processing — replace with real database logic:
// \$db = new \\SPPMod\\SPPDB\\SPPDB();
// \$db->execute_query("INSERT INTO {$appName}_items (name, status) VALUES (?, 'active')", [\$taskTitle]);

\$response = [
    'status' => 'success',
    'message' => 'Task \'' . \$taskTitle . '\' created with priority ' . \$taskPriority . '!',
    'data' => [
        'id' => rand(1000, 9999),
        'title' => \$taskTitle,
        'priority' => \$taskPriority,
        'created_at' => date('Y-m-d H:i:s'),
    ]
];
PHP
            ,
            $appName
        );

        // ── Event handler ──
        $this->writeFile(
            "src/{$appName}/events/AppBootHandler.php",
            <<<PHP
<?php
namespace App\\{$appName}\\Events;

/**
 * ============================================================================
 * Event Handler — {{APP_NAME}}
 * ============================================================================
 *
 * HOW EVENTS WORK:
 * The SPP event system follows a publish/subscribe pattern.
 *
 * REGISTER (in init.php):
 *   \\SPP\\SPPEvent::listen('event_name', [new AppBootHandler(), 'handle']);
 *
 * OR REGISTER (in etc/events.yml):
 *   events:
 *     event_name:
 *       - \\App\\{$appName}\\Events\\AppBootHandler
 *
 * FIRE CUSTOM EVENTS:
 *   \\SPP\\SPPEvent::fireEvent('my.custom.event', new \\SPP\\EventParams(\$data));
 *
 * BUILT-IN EVENTS:
 *   PageNotFound              — No route matched
 *   event_spp_view_render_theme — Before theme rendering
 *   event_spp_page_render      — During page render
 *   DefaultNotFound            — Missing pages.yml default
 * ============================================================================
 */
class AppBootHandler extends \\SPP\\EventHandler
{
    public function afterHandler(&\$params = [])
    {
        // This runs when the registered event fires
        if (defined('SPP_DEBUG') && SPP_DEBUG) {
            @file_put_contents(
                SPP_APP_DIR . '/var/logs/{$appName}_events.log',
                '[' . date('Y-m-d H:i:s') . "] Event handled by AppBootHandler\\n",
                FILE_APPEND
            );
        }
    }
}
PHP
            ,
            $appName
        );

        // ── Middleware ──
        $this->writeFile(
            "src/{$appName}/middleware/AuthGuard.php",
            <<<PHP
<?php
namespace App\\{$appName}\\Middleware;

/**
 * ============================================================================
 * Auth Guard Middleware — {{APP_NAME}}
 * ============================================================================
 *
 * HOW MIDDLEWARE WORKS:
 * Middleware runs before the controller/page is executed.
 * If the middleware returns false or redirects, the request is halted.
 *
 * HOW TO USE:
 * Add middleware to a route in pages.yml (if supported) or call
 * in the controller's constructor:
 *   if (!AuthGuard::check()) { redirect to login; }
 * ============================================================================
 */
class AuthGuard
{
    public static function check(): bool
    {
        if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
            return \\SPPMod\\SPPAuth\\SPPAuth::authSessionExists();
        }
        return false;
    }

    public static function requireAuth(string \$appName = ''): void
    {
        if (!self::check()) {
            \$baseUrl = \\SPP\\App::getBaseUrl(\$appName ?: '{$appName}');
            header('Location: ' . \$baseUrl . '/login');
            exit;
        }
    }
}
PHP
            ,
            $appName
        );

        // ── Entity ──
        $this->writeFile(
            "src/{$appName}/entities/Item.php",
            <<<PHP
<?php
namespace App\\{$appName}\\Entities;

/**
 * ============================================================================
 * Item Entity — {{APP_NAME}}
 * ============================================================================
 *
 * HOW ENTITIES WORK:
 * Entities map to database tables via the SPP ORM (SPPDB).
 * They can be exposed as REST API endpoints automatically.
 *
 * TABLE: {$appName}_items (uses the app's table prefix)
 *
 * CREATE TABLE:
 *   CREATE TABLE {$appName}_items (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     name VARCHAR(255) NOT NULL,
 *     status VARCHAR(50) DEFAULT 'active',
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 *   );
 *
 * USAGE:
 *   \$items = Item::findAll();
 *   \$item = Item::find_one(['id' => 1]);
 *   \$item = new Item(['name' => 'Test', 'status' => 'active']);
 *   \$item->save();
 *
 * API EXPOSURE:
 *   Set 'enable_api' => true in getMetadata() to expose via SPPAPI.
 * ============================================================================
 */
class Item
{
    public static function getTableName(): string
    {
        return '{$appName}_items';
    }

    public static function getMetadata(string \$key = '')
    {
        \$meta = [
            'table' => self::getTableName(),
            'id_field' => 'id',
            'sequence' => self::getTableName() . '_seq',
            'key_type' => 'int',
            'enable_api' => true,  // Expose via REST API
            'fields' => ['id', 'name', 'status', 'created_at'],
            'searchable' => ['name'],
        ];
        return \$key ? (\$meta[\$key] ?? null) : \$meta;
    }
}
PHP
            ,
            $appName
        );

        // ── About page (fallback for modes that don't create one) ──
        $this->writeFile(
            "src/{$appName}/pages/about.php",
            <<<'PHP'
<?php
/**
 * About Page — Shows framework architecture information
 */
?>
<div style="max-width: 800px; margin: 2rem auto; padding: 0 1rem;">
    <div style="background: #fff; border-radius: 16px; padding: 2.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #e2e8f0;">
        <h1 style="margin: 0 0 1rem;">📖 About {{APP_NAME}}</h1>
        <p style="color: #64748b; line-height: 1.7;">
            This application was scaffolded by the SPP Framework <code>make:app</code> command.
            Every file is fully commented and serves as a live tutorial.
        </p>
        <h3 style="color: #6366f1; margin-top: 1.5rem;">Architecture</h3>
        <ul style="color: #64748b; line-height: 2;">
            <li><b>Pages</b> (<code>pages/</code>): Server-rendered PHP with augmentation</li>
            <li><b>Components</b> (<code>comp/</code>): SPP-UX reactive components</li>
            <li><b>Controllers</b> (<code>serv/</code>): Business logic & view rendering</li>
            <li><b>Views</b> (<code>resources/views/</code>): Blade templates</li>
            <li><b>Events</b> (<code>events/</code>): Framework event handlers</li>
            <li><b>Tests</b> (<code>tests/</code>): Parikshak unit & evolutionary tests</li>
            <li><b>Config</b> (<code>etc/</code>): Routes, services, forms, settings</li>
        </ul>
    </div>
</div>
PHP
            ,
            $appName
        );
    }

    // =========================================================================
    //  PARIKSHAK TESTS (Automated Testing)
    // =========================================================================

    private function writeParikshakTests(string $appName): void
    {
        $testsDir = SPP_APP_DIR . "/src/{$appName}/tests";
        if (!is_dir($testsDir)) {
            mkdir($testsDir, 0777, true);
        }

        $ns = $appName;

        // ── Class-based test (SPPTestCase) ──
        $this->writeFile(
            "src/{$appName}/tests/test.HomeControllerTest.php",
            <<<PHP
<?php
namespace App\\{$ns}\\Tests;

use SPPMod\\Parikshak\\SPPTestCase;
use SPPMod\\Parikshak\\Attributes\\DataProvider;

/**
 * ============================================================================
 * HomeController Test — {$ns}
 * ============================================================================
 *
 * HOW PARIKSHAK TESTING WORKS:
 * Parikshak is the SPP framework's built-in testing engine. It supports:
 *
 *   1. CLASS-BASED TESTS (this file):
 *      - Extend SPPTestCase
 *      - Test methods MUST start with 'test' prefix
 *      - File MUST be named test.ClassName.php
 *      - Place in src/{$ns}/tests/ directory
 *
 *   2. DSL-BASED TESTS (see test.functional.php):
 *      - Use test(), it(), expect() functions
 *      - More readable, BDD-style syntax
 *
 *   3. EVOLUTIONARY TESTS (automatic):
 *      - Parikshak auto-generates entity CRUD tests from your entities/
 *      - Fuzzes inputs with ParikshakFuzzer
 *      - Validates with ParikshakOracle
 *
 * RUNNING TESTS:
 *   php spp.php test:run --app={$ns}
 *   php spp.php test:run --app={$ns} --coverage   (with code coverage)
 *   php spp.php test:run --app={$ns} EntityName    (test single entity)
 *
 * AVAILABLE ASSERTIONS (from SPPTestCase):
 *   \$this->assertTrue(\$condition, 'message')
 *   \$this->assertFalse(\$condition, 'message')
 *   \$this->assertEquals(\$expected, \$actual, 'message')
 *   \$this->assertSame(\$expected, \$actual, 'message')        // strict ===
 *   \$this->assertInstanceOf(ClassName::class, \$object)
 *   \$this->expectException(ExceptionClass::class, \$callable)
 *
 * AVAILABLE TRAITS:
 *   InteractsWithApi     — HTTP request simulation (\$this->get, \$this->post, etc.)
 *   InteractsWithBrowser — Headless browser testing
 *   InteractsWithMockery — Object mocking via Mockery
 *   RefreshDatabase      — Reset DB between tests
 *
 * PHP 8 ATTRIBUTES:
 *   #[DataProvider('providerMethodName')] — Run test with multiple data sets
 *
 * HOW TO ADD NEW TESTS:
 *   1. Create a file named test.YourTestName.php in this directory
 *   2. Create a class extending SPPTestCase
 *   3. Add methods prefixed with 'test'
 *   4. Run: php spp.php test:run --app={$ns}
 * ============================================================================
 */
class HomeControllerTest extends SPPTestCase
{
    use \\SPPMod\\Parikshak\\InteractsWithApi;

    /**
     * setUp() runs before EACH test method.
     * Use it to initialize test data, mock services, etc.
     */
    public function setUp(): void
    {
        parent::setUp();
        // Example: Initialize test data
        // \$this->testData = ['name' => 'Test Item'];
    }

    /**
     * tearDown() runs after EACH test method.
     * Use it to clean up resources, close connections, etc.
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }

    // ── Test Methods ────────────────────────────────────────────

    /**
     * Test that the app name is not empty.
     * Every test method MUST start with 'test'.
     */
    public function testAppNameIsValid(): void
    {
        \$appName = '{$ns}';
        \$this->assertTrue(!empty(\$appName), 'App name should not be empty');
        \$this->assertTrue(strlen(\$appName) > 0, 'App name should have length > 0');
    }

    /**
     * Test basic string equality.
     */
    public function testStringEquality(): void
    {
        \$expected = '{$ns}';
        \$actual = '{$ns}';
        \$this->assertEquals(\$expected, \$actual, 'App name should match');
    }

    /**
     * Test strict type checking.
     */
    public function testStrictTypeChecking(): void
    {
        \$this->assertSame(42, 42, 'Integer 42 should strictly equal 42');
        \$this->assertFalse(false, 'false should be false');
        \$this->assertTrue(true, 'true should be true');
    }

    /**
     * Test with DataProvider — runs this test once for each data set.
     * The #[DataProvider] attribute links to a method that returns test data.
     */
    #[DataProvider('validationDataProvider')]
    public function testInputValidation(string \$input, bool \$expectedValid): void
    {
        \$isValid = !empty(trim(\$input));
        \$this->assertSame(\$expectedValid, \$isValid, "Validation failed for input: '\$input'");
    }

    /**
     * Data provider method — returns arrays of test data.
     * Each inner array is passed as arguments to the test method.
     */
    public function validationDataProvider(): array
    {
        return [
            ['Hello', true],
            ['', false],
            ['  ', false],
            ['Valid Name', true],
        ];
    }

    /**
     * Test exception handling.
     */
    public function testExceptionIsThrown(): void
    {
        \$this->expectException(\\InvalidArgumentException::class, function () {
            throw new \\InvalidArgumentException('This is expected');
        });
    }

    /**
     * Test API endpoint simulation using InteractsWithApi trait.
     * This simulates HTTP requests without a real web server.
     */
    public function testApiEndpointSimulation(): void
    {
        // The InteractsWithApi trait provides:
        //   \$this->get('/path')
        //   \$this->post('/path', ['data' => 'value'])
        //   \$this->put('/path', ['data' => 'value'])
        //   \$this->delete('/path')
        //
        // Returns SPPTestResponse with:
        //   \$response->statusCode
        //   \$response->content
        //   \$response->json()  — decoded JSON
        //   \$response->assertStatus(200)
        //   \$response->assertJsonHas('key')

        // Note: Full API testing requires the SPP Kernel boot.
        // This is a placeholder showing the API:
        \$this->assertTrue(true, 'API testing placeholder — requires full kernel');
    }
}
PHP
        );

        // ── DSL-based test (functional style) ──
        $this->writeFile(
            "src/{$appName}/tests/test.functional.php",
            <<<PHP
<?php
/**
 * ============================================================================
 * Functional Tests (DSL Style) — {$ns}
 * ============================================================================
 *
 * HOW DSL TESTS WORK:
 * Parikshak provides a BDD-style DSL (Domain Specific Language) for tests.
 * Instead of classes, you use simple functions:
 *
 *   test('description', function() {
 *       // Test logic here
 *       expect(\$value)->toBe(\$expected);
 *   });
 *
 *   it('should do something', function() {
 *       // Same as test() but prefixes description with "it "
 *       expect(true)->toBeTrue();
 *   });
 *
 * AVAILABLE EXPECTATIONS:
 *   expect(\$value)->toBe(\$expected)    — Strict equality (===)
 *   expect(\$value)->toBeTrue()          — Assert true
 *   expect(\$value)->toBeFalse()         — Assert false
 *   expect(\$value)->toBeNull()          — Assert null
 *
 * RUNNING:
 *   php spp.php test:run --app={$ns}
 *
 * DSL tests are discovered automatically from test.*.php files.
 * They run alongside class-based tests in the same suite.
 * ============================================================================
 */

// ── Basic Value Tests ──

test('app name is a non-empty string', function () {
    \$appName = '{$ns}';
    expect(strlen(\$appName) > 0)->toBeTrue();
});

test('null is null', function () {
    expect(null)->toBeNull();
});

it('should validate boolean true', function () {
    expect(true)->toBeTrue();
});

it('should validate boolean false', function () {
    expect(false)->toBeFalse();
});

// ── String Tests ──

test('string concatenation works', function () {
    \$result = 'Hello' . ' ' . 'World';
    expect(\$result)->toBe('Hello World');
});

// ── Array Tests ──

test('array operations work correctly', function () {
    \$items = ['a', 'b', 'c'];
    expect(count(\$items))->toBe(3);
    expect(in_array('b', \$items))->toBeTrue();
    expect(in_array('z', \$items))->toBeFalse();
});

// ── Math Tests ──

test('basic arithmetic is correct', function () {
    expect(2 + 2)->toBe(4);
    expect(10 - 3)->toBe(7);
    expect(3 * 4)->toBe(12);
});

// ── File System Tests ──

test('app directory exists', function () {
    \$dir = SPP_APP_DIR . '/src/{$ns}';
    expect(is_dir(\$dir))->toBeTrue();
});

test('pages.yml config exists', function () {
    \$file = SPP_APP_DIR . '/etc/apps/{$ns}/pages.yml';
    expect(file_exists(\$file))->toBeTrue();
});
PHP
        );

        echo "  ✓ src/{$appName}/tests/ (Parikshak test suite)\n";
    }

    // =========================================================================
    //  PHP 8 ATTRIBUTE-BASED CONTROLLER
    // =========================================================================

    private function writeAttributeController(string $appName): void
    {
        $ns = $appName;
        $this->writeFile(
            "src/{$appName}/serv/AttributeController.php",
            <<<PHP
<?php
namespace App\\{$ns}\\Serv;

use SPPMod\\SPPView\\Attributes\\Route;
use SPPMod\\SPPView\\Attributes\\Middleware;
use SPPMod\\SPPView\\Attributes\\Title;

/**
 * ============================================================================
 * AttributeController — PHP 8 Attribute-Based Routing
 * ============================================================================
 *
 * HOW PHP 8 ATTRIBUTE ROUTING WORKS:
 * Instead of defining routes in pages.yml, you can use PHP 8 Attributes
 * directly on controller methods. The SPP RouteScanner automatically
 * discovers these attributes and registers routes.
 *
 * AVAILABLE ATTRIBUTES:
 *
 *   #[Route('/path', method: 'GET|POST', name: 'route.name', middleware: [])]
 *     - path:       URL path (supports {param} placeholders)
 *     - method:     HTTP methods (default: 'GET|POST')
 *     - name:       Named route for reverse routing
 *     - middleware:  Array of middleware class names
 *     - Can be applied to CLASS (prefix) or METHOD (individual route)
 *
 *   #[Middleware(ClassName::class)]
 *     - Applied to CLASS or METHOD
 *     - Class-level middleware applies to ALL methods
 *     - Method-level middleware stacks with class-level
 *     - IS_REPEATABLE: can use multiple times
 *
 *   #[Title('Page Title')]
 *     - CLASS-level only
 *     - Sets the page <title> for all routes in this controller
 *
 *   #[Lazy(action: 'load')]
 *     - CLASS-level only
 *     - Marks controller for lazy loading (deferred initialization)
 *
 *   #[Isolate]
 *     - CLASS-level only
 *     - Runs controller in an isolated context (no shared state)
 *
 * HOW ROUTES ARE DISCOVERED:
 * The SPP RouteScanner scans these directories for #[Route] attributes:
 *   - {app_dir}/controllers/
 *   - {app_dir}/src/Controllers/
 *   - {app_dir}/src/controllers/
 *
 * Routes are cached in var/cache/routes_{app}.php for performance.
 * Cache is auto-invalidated in development mode (SPP_DEBUG=true).
 *
 * WHEN TO USE ATTRIBUTES vs pages.yml:
 *   - Attributes: Best for controller-heavy apps (keeps routes with code)
 *   - pages.yml:  Best for page-file routes, assets, special pages
 *   - Both can coexist — attribute routes supplement pages.yml
 *
 * HOW TO ADD A NEW ATTRIBUTE ROUTE:
 *   1. Add #[Route('/path')] above any public method
 *   2. Clear route cache: rm var/cache/routes_{$ns}.php
 *   3. The route is automatically discovered
 * ============================================================================
 */

// Class-level #[Route] sets a prefix for all method routes
#[Route('/attr')]
#[Title('{$ns} Attribute Routes')]
class AttributeController
{
    /**
     * GET /attr/hello
     * Basic attribute-routed endpoint.
     */
    #[Route('/hello', method: 'GET', name: 'attr.hello')]
    public function hello()
    {
        return '<div style="max-width:700px;margin:2rem auto;padding:2rem;background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.04);border:1px solid #e2e8f0;font-family:Inter,sans-serif;">
            <span style="display:inline-block;background:#dbeafe;color:#1d4ed8;padding:4px 12px;border-radius:20px;font-size:0.75rem;font-weight:700;margin-bottom:1rem;">#[Route] ATTRIBUTE</span>
            <h1 style="margin:0 0 0.5rem;">Hello from AttributeController!</h1>
            <p style="color:#64748b;line-height:1.7;">This route was defined using a PHP 8 <code>#[Route]</code> attribute instead of pages.yml.</p>
            <pre style="background:#f1f5f9;padding:1rem;border-radius:10px;font-size:0.85rem;overflow-x:auto;">
#[Route(\'/hello\', method: \'GET\', name: \'attr.hello\')]
public function hello() { ... }</pre>
        </div>';
    }

    /**
     * GET /attr/greet/{name}
     * Parameterized route with {name} placeholder.
     */
    #[Route('/greet/{name}', method: 'GET', name: 'attr.greet')]
    public function greet()
    {
        // Route parameters are available via SPPGlobal
        \$pageData = \\SPPMod\\SPPView\\SPPGlobal::get('page');
        \$name = \$pageData['named_params']['name'] ?? \$pageData['params'][0] ?? 'World';

        return '<div style="max-width:700px;margin:2rem auto;padding:2rem;background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.04);border:1px solid #e2e8f0;font-family:Inter,sans-serif;">
            <h1 style="margin:0 0 0.5rem;">Hello, ' . htmlspecialchars(\$name) . '!</h1>
            <p style="color:#64748b;">This is a parameterized route: <code>#[Route(\'/greet/{name}\')]</code></p>
            <p style="color:#64748b;">The <code>{name}</code> parameter was extracted automatically.</p>
        </div>';
    }

    /**
     * GET|POST /attr/api/data
     * JSON API endpoint with attribute routing.
     */
    #[Route('/api/data', method: 'GET|POST', name: 'attr.api.data')]
    public function apiData()
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'source' => 'AttributeController',
            'method' => \$_SERVER['REQUEST_METHOD'],
            'message' => 'This JSON endpoint uses #[Route] attribute routing.',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * GET /attr/protected
     * Route with method-level middleware.
     */
    #[Route('/protected', method: 'GET', name: 'attr.protected')]
    #[Middleware('\\App\\{$ns}\\Middleware\\AuthGuard')]
    public function protectedRoute()
    {
        return '<div style="max-width:700px;margin:2rem auto;padding:2rem;background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.04);border:1px solid #e2e8f0;font-family:Inter,sans-serif;">
            <span style="display:inline-block;background:#dcfce7;color:#16a34a;padding:4px 12px;border-radius:20px;font-size:0.75rem;font-weight:700;">AUTHENTICATED</span>
            <h1 style="margin:0.5rem 0;">Protected Route</h1>
            <p style="color:#64748b;">This route uses <code>#[Middleware]</code> attribute for auth protection.</p>
            <pre style="background:#f1f5f9;padding:1rem;border-radius:10px;font-size:0.85rem;">#[Route(\'/protected\')]
#[Middleware(\'\\App\\{$ns}\\Middleware\\AuthGuard\')]
public function protectedRoute() { ... }</pre>
        </div>';
    }
}
PHP
        );
    }

    // =========================================================================
    //  CUSTOM ERROR PAGES (404, 500)
    // =========================================================================

    private function writeCustomErrorPages(string $appName): void
    {
        // ── 404 Page Not Found ──
        $this->writeFile(
            "src/{$appName}/pages/errors/404.php",
            <<<'ERROR404PHP'
<?php
/**
 * ============================================================================
 * Custom 404 — Page Not Found
 * ============================================================================
 *
 * HOW SPP ERROR HANDLING WORKS:
 *
 * 1. BOOTSTRAP SETUP:
 *    SPPErrorHandler::register() is called during bootstrap to set up PHP's
 *    error and exception handlers. This catches all uncaught errors globally.
 *
 * 2. EXCEPTION HANDLING:
 *    SPPError::exceptionHandler() processes all uncaught exceptions.
 *    In debug mode (SPP_DEBUG=true), it shows Ignition-style error pages
 *    with stack traces and code context. In production, it shows user-friendly pages.
 *
 * 3. CUSTOM HANDLERS:
 *    You can override the default error display:
 *      SPPError::setCustomErrorHandler(function($error) {
 *          // Your custom error display logic
 *      });
 *
 * 4. ERROR TYPES:
 *    SPPError::triggerUserError('msg')   — Displayed to end users
 *    SPPError::triggerDevError('msg')    — Displayed only in debug mode
 *    SPPError::triggerAdminError('msg')  — Logged and sent to admin
 *
 * 5. THE 'PageNotFound' EVENT:
 *    When no route matches, SPP fires the 'PageNotFound' event.
 *    Listen to it in init.php to redirect here:
 *
 *      \SPP\SPPEvent::listen('PageNotFound', function($params) {
 *          http_response_code(404);
 *          include __DIR__ . '/pages/errors/404.php';
 *          exit;
 *      });
 *
 * 6. THE 'core.error.exception' EVENT:
 *    Fired when any uncaught exception occurs. Use for global logging:
 *
 *      \SPP\SPPEvent::listen('core.error.exception', function($params) {
 *          \SPP\Log::error('Exception: ' . $params->get('message'));
 *      });
 *
 * 7. API ERRORS:
 *    When the URL starts with /api/, SPP automatically returns JSON errors:
 *    {"error": "Not Found", "status": 404}
 *
 * 8. DEBUG vs PRODUCTION:
 *    SPP_DEBUG=true  → Ignition-style error page with stack trace
 *    SPP_DEBUG=false → This user-friendly page is shown instead
 *
 * ============================================================================
 */

http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page Not Found</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .error-card {
            background: #fff;
            border-radius: 24px;
            padding: 3rem;
            max-width: 580px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0,0,0,0.15);
        }
        .error-code {
            font-size: 7rem;
            font-weight: 800;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        .error-title {
            font-size: 1.5rem;
            color: #1e293b;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        .error-message {
            color: #64748b;
            line-height: 1.7;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        .error-btn {
            display: inline-block;
            padding: 0.8rem 2rem;
            background: #6366f1;
            color: #fff;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .error-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99,102,241,0.4);
        }
        .error-path {
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: #94a3b8;
        }
        .error-path code {
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-code">404</div>
        <h1 class="error-title">Page Not Found</h1>
        <p class="error-message">
            The page you are looking for does not exist or has been moved.
            Check the URL or navigate back to the home page.
        </p>
        <a href="/" class="error-btn">Go Home</a>
        <p class="error-path">
            Requested: <code><?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?></code>
        </p>
    </div>
</body>
</html>
ERROR404PHP
            ,
            $appName
        );

        // ── 500 Internal Server Error ──
        $this->writeFile(
            "src/{$appName}/pages/errors/500.php",
            <<<'ERROR500PHP'
<?php
/**
 * ============================================================================
 * Custom 500 — Internal Server Error
 * ============================================================================
 *
 * WHEN THIS PAGE IS SHOWN:
 *   - An uncaught exception occurs in PRODUCTION mode (SPP_DEBUG = false)
 *   - You manually include this page in your exception handler
 *
 * DEBUG vs PRODUCTION:
 *   SPP_DEBUG = true  → The framework shows an Ignition-style debug page
 *                        with full stack trace, code snippets, and context.
 *                        This file is NOT shown in debug mode.
 *
 *   SPP_DEBUG = false → This user-friendly page is shown instead.
 *                        The actual error is logged to var/log/ for developers.
 *
 * HOW TO CUSTOMIZE GLOBALLY:
 *   In your init.php, listen to the 'core.error.exception' event:
 *
 *     \SPP\SPPEvent::listen('core.error.exception', function($params) {
 *         if (!defined('SPP_DEBUG') || !SPP_DEBUG) {
 *             http_response_code(500);
 *             include __DIR__ . '/pages/errors/500.php';
 *             exit;
 *         }
 *     });
 *
 * LOGGING ERRORS:
 *   \SPP\Log::error('Message', ['exception' => $e->getMessage()]);
 *   Logs go to: var/log/app.log (auto-rotated by spplogger module)
 *
 * ============================================================================
 */

http_response_code(500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — Internal Server Error</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 50%, #991b1b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .error-card {
            background: #fff;
            border-radius: 24px;
            padding: 3rem;
            max-width: 580px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0,0,0,0.2);
        }
        .error-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .error-code {
            font-size: 5rem;
            font-weight: 800;
            color: #ef4444;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        .error-title {
            font-size: 1.5rem;
            color: #1e293b;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        .error-message {
            color: #64748b;
            line-height: 1.7;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        .error-btn {
            display: inline-block;
            padding: 0.8rem 2rem;
            background: #ef4444;
            color: #fff;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .error-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239,68,68,0.4);
        }
        .error-hint {
            margin-top: 2rem;
            padding: 1rem;
            background: #fef2f2;
            border-radius: 10px;
            font-size: 0.82rem;
            color: #991b1b;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">&#9888;&#65039;</div>
        <div class="error-code">500</div>
        <h1 class="error-title">Internal Server Error</h1>
        <p class="error-message">
            Something went wrong on our end. Our team has been notified
            and is working to fix the issue. Please try again later.
        </p>
        <a href="/" class="error-btn">Go Home</a>
        <div class="error-hint">
            <strong>Developer Tip:</strong> Check <code>var/log/</code> for detailed error logs.
            Enable <code>SPP_DEBUG=true</code> in development to see full stack traces.
        </div>
    </div>
</body>
</html>
ERROR500PHP
            ,
            $appName
        );
    }
}

