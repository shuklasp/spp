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

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $appName = $this->getArgument($args, 0) ?? null;
        if (!$appName) {
            $appName = $this->prompt("Enter application name");
            if (!$appName) {
                echo "App name is required.\n";
                return;
            }
        }

        $appType = $this->getArgument($args, 1) ?? null;
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

        $baseUrl = $this->getArgument($args, 2) ?? null;
        if (!$baseUrl) {
            $baseUrlInput = $this->prompt("Enter base URL", "/" . $appName);
            $baseUrl = !empty($baseUrlInput) ? $baseUrlInput : "/" . $appName;
        }

        $tablePrefix = $this->getArgument($args, 3) ?? null;
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
        $this->buildFromStub('events_2', "src/{$appName}/etc/events.yml", ['appName' => $appName, 'APP_NAME' => $appName]);

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
        $this->buildFromStub('index_3', "src/{$appName}/index.php", ['APP_NAME' => $appName]);

        // Drop-in sample view
        $viewsDir = SPP_APP_DIR . "/resources/{$appName}/views";
        if (!is_dir($viewsDir))
            mkdir($viewsDir, 0777, true);

        $this->buildFromStub('index_4', "resources/{$appName}/views/index.html", ['APP_NAME' => $appName]);

        $this->writeDropinGuidePage($appName);
        $this->writeContactForm($appName);
    }

    // =========================================================================
    //  SHARED FILE GENERATORS
    // =========================================================================

    // ── SPP-UX Entry Point (used by mixed + sppux modes) ──

    private function writeSppuxEntryPoint(string $appName): void
    {
        $this->buildFromStub('index_5', "src/{$appName}/index.php", ['APP_NAME' => $appName]);
    }

    // ── SPP-UX Components ──

    private function writeSppuxComponents(string $appName): void
    {
        $compDir = SPP_APP_DIR . "/src/{$appName}/comp";
        if (!is_dir($compDir))
            mkdir($compDir, 0777, true);

        // ── main.js: Full dashboard component ──
        $this->buildFromStub('main_6', "src/{$appName}/comp/main.js", ['APP_NAME' => $appName]);

        // ── counter.js: Sub-component with props ──
        $this->buildFromStub('counter_7', "src/{$appName}/comp/counter.js", ['APP_NAME' => $appName]);

        // ── app-store.js: Shared state store ──
        $this->buildFromStub('store_8', "src/{$appName}/comp/app-store.js", ['APP_NAME' => $appName]);
    }

    // ── SPP-UX extra components for sppux mode ──

    private function writeSppuxThemePicker(string $appName): void
    {
        $this->buildFromStub('picker_9', "src/{$appName}/comp/theme-picker.js", ['APP_NAME' => $appName]);
    }

    private function writeSppuxFormDemo(string $appName): void
    {
        $this->buildFromStub('demo_10', "src/{$appName}/comp/form-demo.js", ['APP_NAME' => $appName]);
    }

    // ── Controllers ──

    private function writeHomeController(string $appName): void
    {
        $ns = $appName;
        $this->buildFromStub('homecontroller_11', "src/{$appName}/serv/HomeController.php", ['ns' => $ns, 'APP_NAME' => $appName]);
    }

    private function writeDashboardController(string $appName): void
    {
        $ns = $appName;
        $this->buildFromStub('dashboardcontroller_12', "src/{$appName}/serv/DashboardController.php", ['ns' => $ns, 'APP_NAME' => $appName]);
    }

    private function writeApiController(string $appName): void
    {
        $ns = $appName;
        $this->buildFromStub('apicontroller_13', "src/{$appName}/serv/ApiController.php", ['ns' => $ns, 'APP_NAME' => $appName]);
    }

    // ── Blade Templates ──

    private function writeBladeLayout(string $appName): void
    {
        $viewsDir = SPP_APP_DIR . "/src/{$appName}/resources/views/layouts";
        if (!is_dir($viewsDir))
            mkdir($viewsDir, 0777, true);

        $this->buildFromStub('blade_14', "src/{$appName}/resources/views/layouts/app.blade.php", ['APP_NAME' => $appName]);
    }

    private function writeBladeHome(string $appName): void
    {
        $this->buildFromStub('blade_15', "src/{$appName}/resources/views/home.blade.php", ['APP_NAME' => $appName]);
    }

    private function writeBladeAbout(string $appName): void
    {
        $this->buildFromStub('blade_16', "src/{$appName}/resources/views/about.blade.php", ['APP_NAME' => $appName]);
    }

    private function writeBladeDashboard(string $appName): void
    {
        $this->buildFromStub('blade_17', "src/{$appName}/resources/views/dashboard.blade.php", ['APP_NAME' => $appName]);
    }

    // ── Native PHP Pages ──

    private function writeNativeIndexPage(string $appName): void
    {
        $this->buildFromStub('index_18', "src/{$appName}/pages/index.php", ['APP_NAME' => $appName]);
    }

    private function writeNativeContactPage(string $appName): void
    {
        $this->buildFromStub('contact_19', "src/{$appName}/pages/contact.php", ['APP_NAME' => $appName]);
    }

    private function writeNativeGuidePage(string $appName): void
    {
        $this->buildFromStub('guide_20', "src/{$appName}/pages/guide.php", ['APP_NAME' => $appName]);
    }

    // =========================================================================
    //  GUIDE PAGE: SPP-UX Mode — Reactive Component Tutorial
    // =========================================================================

    private function writeSppuxGuidePage(string $appName): void
    {
        $this->buildFromStub('guide_21', "src/{$appName}/pages/guide.html", ['APP_NAME' => $appName]);
    }

    // =========================================================================
    //  GUIDE PAGE: Blade Mode — Server-Rendered Template Tutorial
    // =========================================================================

    private function writeBladeGuidePage(string $appName): void
    {
        $viewsDir = SPP_APP_DIR . "/src/{$appName}/resources/views";
        if (!is_dir($viewsDir))
            mkdir($viewsDir, 0777, true);

        $this->buildFromStub('blade_22', "src/{$appName}/resources/views/guide.blade.php", ['APP_NAME' => $appName]);
    }

    // =========================================================================
    //  GUIDE PAGE: API Mode — Headless REST Backend Tutorial
    // =========================================================================

    private function writeApiGuidePage(string $appName): void
    {
        // ── JSON documentation controller ──
        $ns = $appName;
        $this->buildFromStub('apidocscontroller_23', "src/{$appName}/serv/ApiDocsController.php", ['ns' => $ns, 'APP_NAME' => $appName]);

        // ── Styled HTML API docs page ──
        $this->buildFromStub('docs_24', "src/{$appName}/pages/api-docs.php", ['APP_NAME' => $appName]);
    }

    // =========================================================================
    //  GUIDE PAGE: Drop-in Mode — Low-Code Tutorial
    // =========================================================================

    private function writeDropinGuidePage(string $appName): void
    {
        $viewsDir = SPP_APP_DIR . "/resources/{$appName}/views";
        if (!is_dir($viewsDir))
            mkdir($viewsDir, 0777, true);

        $this->buildFromStub('guide_25', "resources/{$appName}/views/guide.html", ['APP_NAME' => $appName]);
    }

    // ── YAML Forms ──

    private function writeContactForm(string $appName): void
    {
        $this->buildFromStub('contact_26', "etc/apps/{$appName}/forms/contact.yml", ['appName' => $appName, 'APP_NAME' => $appName]);
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
        $this->buildFromStub('init_27', "src/{$appName}/init.php", ['appName' => $appName, 'APP_NAME' => $appName]);

        // ── etc/config.yml ──
        $this->buildFromStub('config_28', "src/{$appName}/etc/config.yml", ['appName' => $appName, 'appType' => $appType, 'APP_NAME' => $appName]);

        // ── etc/routes.yml ──
        $this->buildFromStub('routes_29', "src/{$appName}/etc/routes.yml", ['appName' => $appName, 'APP_NAME' => $appName]);

        // ── etc/services.yml ──
        $this->buildFromStub('services_30', "src/{$appName}/etc/services.yml", ['appName' => $appName, 'APP_NAME' => $appName]);

        // ── etc/app.yml ──
        $appNameLower = strtolower($appName);
        $this->buildFromStub('app_31', "src/{$appName}/etc/app.yml", ['appNameLower' => $appNameLower, 'appName' => $appName, 'appType' => $appType, 'APP_NAME' => $appName]);

        // ── Theme manifest ──
        $this->buildFromStub('theme_32', "src/{$appName}/resources/themes/default/theme.yml", ['appName' => $appName, 'APP_NAME' => $appName]);

        // ── Theme CSS ──
        $this->buildFromStub('custom_33', "src/{$appName}/resources/themes/default/custom.css", ['appName' => $appName, 'APP_NAME' => $appName]);

        // ── Login view ──
        $this->buildFromStub('blade_34', "src/{$appName}/resources/views/login.blade.php", ['APP_NAME' => $appName]);

        // ── AuthController ──
        $this->buildFromStub('authcontroller_35', "src/{$appName}/serv/AuthController.php", ['appName' => $appName, 'APP_NAME' => $appName]);

        // ── Sample service: task_create.php ──
        $this->buildFromStub('task_create_36', "src/{$appName}/serv/task_create.php", ['appName' => $appName, 'APP_NAME' => $appName]);

        // ── Event handler ──
        $this->buildFromStub('appboothandler_37', "src/{$appName}/events/AppBootHandler.php", ['appName' => $appName, 'APP_NAME' => $appName]);

        // ── Middleware ──
        $this->buildFromStub('authguard_38', "src/{$appName}/middleware/AuthGuard.php", ['appName' => $appName, 'APP_NAME' => $appName]);

        // ── Entity ──
        $this->buildFromStub('item_39', "src/{$appName}/entities/Item.php", ['appName' => $appName, 'APP_NAME' => $appName]);

        // ── About page (fallback for modes that don't create one) ──
        $this->buildFromStub('about_40', "src/{$appName}/pages/about.php", ['APP_NAME' => $appName]);
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
        $this->buildFromStub('homecontrollertest_41', "src/{$appName}/tests/test.HomeControllerTest.php", ['ns' => $ns, 'APP_NAME' => $appName]);

        // ── DSL-based test (functional style) ──
        $this->buildFromStub('functional_42', "src/{$appName}/tests/test.functional.php", ['ns' => $ns, 'APP_NAME' => $appName]);

        echo "  ✓ src/{$appName}/tests/ (Parikshak test suite)\n";
    }

    // =========================================================================
    //  PHP 8 ATTRIBUTE-BASED CONTROLLER
    // =========================================================================

    private function writeAttributeController(string $appName): void
    {
        $ns = $appName;
        $this->buildFromStub('attributecontroller_43', "src/{$appName}/serv/AttributeController.php", ['ns' => $ns, 'APP_NAME' => $appName]);
    }

    // =========================================================================
    //  CUSTOM ERROR PAGES (404, 500)
    // =========================================================================

    private function writeCustomErrorPages(string $appName): void
    {
        // ── 404 Page Not Found ──
        $this->buildFromStub('404_44', "src/{$appName}/pages/errors/404.php", ['APP_NAME' => $appName]);

        // ── 500 Internal Server Error ──
        $this->buildFromStub('500_45', "src/{$appName}/pages/errors/500.php", ['APP_NAME' => $appName]);
    }
}

