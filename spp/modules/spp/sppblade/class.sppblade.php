<?php

namespace SPPMod\SPPBlade;

use eftec\bladeone\BladeOne;

/**
 * Class SPPBlade
 * Wrapper for BladeOne engine in SPP.
 */
class SPPBlade extends \SPP\SPPObject
{
    protected ?BladeOne $engine = null;
    protected string $viewsPath;
    protected string $cachePath;

    public function __construct()
    {
        $app = \SPP\App::getApp();
        $appName = $app->getName();

        // Resolve paths relative to app source or base directory
        $srcDir = $app->getAppSrcDir();
        if (is_dir($srcDir . '/resources/views')) {
            $this->viewsPath = $srcDir . '/resources/views';
        } else {
            $this->viewsPath = SPP_APP_DIR . '/resources/' . $appName . '/views';
        }

        $this->cachePath = SPP_APP_DIR . '/var/cache/' . $appName . '/blade';

        $this->ensureDirectories();

        $mode = 5; // Force MODE_DEBUG compilation to guarantee live CSS/layout loading
        
        $this->engine = new BladeOne($this->viewsPath, $this->cachePath, (int)$mode);

        $this->registerDirectives();
    }

    /**
     * Register custom SPP Blade directives.
     */
    protected function registerDirectives(): void
    {
        // @sppform('login')
        // Loads form from XML/YAML in the app's forms directory
        $this->engine->directive('sppform', function ($expression) {
            if (empty($expression)) return "";
            return "<?php 
                \$appName = \SPP\Scheduler::getContext();
                \$fname = str_replace(['\'', '\"'], '', $expression);
                \$app = \SPP\App::getApp(\$appName);
                \$baseDir = \$app->getAppConfDir() . '/forms/';
                
                \$formFile = null;
                foreach (['yml', 'yaml', 'xml'] as \$ext) {
                    if (file_exists(\$baseDir . \$fname . '.' . \$ext)) {
                        \$formFile = \$baseDir . \$fname . '.' . \$ext;
                        break;
                    }
                }

                if (\$formFile) {
                    \SPPMod\SPPView\ViewPage::readFormFile(\$formFile);
                }
            ?>";
        });

        // @sppform_start('login')
        $this->engine->directive('sppform_start', function ($expression) {
            if (empty($expression)) return "";
            return "<?php 
                \$forms = \SPPMod\SPPView\ViewPage::getFormsList();
                \$fname = str_replace(['\'', '\"'], '', $expression);
                if (isset(\$forms[\$fname])) {
                    \$forms[\$fname]->startForm();
                }
            ?>";
        });

        // @sppform_end
        $this->engine->directive('sppform_end', function () {
            return "<?php 
                \$forms = \SPPMod\SPPView\ViewPage::getFormsList();
                \$activeForm = end(\$forms); // Simplification: get last registered form
                if (\$activeForm) \$activeForm->endForm();
            ?>";
        });

        // @sppelement('username', ['class' => 'form-control'])
        $this->engine->directive('sppelement', function ($expression) {
            return "<?php 
                \$args = [$expression];
                \$elemId = \$args[0];
                \$attrs = \$args[1] ?? [];
                
                \$forms = \SPPMod\SPPView\ViewPage::getFormsList();
                foreach (\$forms as \$form) {
                    \$elements = \$form->get('element');
                    if (isset(\$elements[\$elemId])) {
                        \$el = \$elements[\$elemId];
                        // If attributes are passed, try to apply them if the element supports it
                        if (!empty(\$attrs) && method_exists(\$el, 'setAttributes')) {
                            \$el->setAttributes(\$attrs);
                        }
                        echo \$el->getHTML();
                        break;
                    }
                }
            ?>";
        });
        // @sppauth
        $this->engine->directive('sppauth', function () {
            return "<?php if (\SPPMod\SPPAuth\SPPAuth::authSessionExists()): ?>";
        });

        // @sppendsppauth
        $this->engine->directive('endsppauth', function () {
            return "<?php endif; ?>";
        });

        // @sppguest
        $this->engine->directive('sppguest', function () {
            return "<?php if (!\SPPMod\SPPAuth\SPPAuth::authSessionExists()): ?>";
        });

        // @sppendsppguest
        $this->engine->directive('endsppguest', function () {
            return "<?php endif; ?>";
        });
        // @sppbind($entity)
        $this->engine->directive('sppbind', function ($expression) {
            if (empty($expression)) return "";
            return "<?php 
                \$forms = \SPPMod\SPPView\ViewPage::getFormsList();
                \$activeForm = end(\$forms);
                if (\$activeForm && isset($expression)) {
                    \$activeForm->bind($expression);
                }
            ?>";
        });
        // @react('MyComponent', ['prop' => 'value'])
        $this->engine->directive('react', function ($expression) {
            if (empty($expression)) return "";
            return "<?php 
                \$args = [$expression];
                \$name = \$args[0];
                \$props = json_encode(\$args[1] ?? []);
                \$context = \SPP\Scheduler::getContext();
                \$app = \SPP\App::getApp(\$context);
                \$srcPath = \SPP\App::getAppConf('src_path', \$context) ?? ('src/' . \$context);
                \$path = \"/{\$srcPath}/resources/js/react/{\$name}.js\";
                echo \"<div data-spp-component='1' data-spp-type='react' data-spp-path='{\$path}' data-spp-props='{\$props}'></div>\";
            ?>";
        });

        // @vue('MyComponent', ['prop' => 'value'])
        $this->engine->directive('vue', function ($expression) {
            if (empty($expression)) return "";
            return "<?php 
                \$args = [$expression];
                \$name = \$args[0];
                \$props = json_encode(\$args[1] ?? []);
                \$context = \SPP\Scheduler::getContext();
                \$app = \SPP\App::getApp(\$context);
                \$srcPath = \SPP\App::getAppConf('src_path', \$context) ?? ('src/' . \$context);
                \$path = \"/{\$srcPath}/resources/js/vue/{\$name}.js\";
                echo \"<div data-spp-component='1' data-spp-type='vue' data-spp-path='{\$path}' data-spp-props='{\$props}'></div>\";
            ?>";
        });

        // @sppux('ComponentName', ['prop' => 'value'])
        $this->engine->directive('sppux', function ($expression) {
            if (empty($expression)) return "";
            return "<?php 
                \$args = [$expression];
                \$name = \$args[0];
                \$props = \$args[1] ?? [];
                if (class_exists('\\\\SPPMod\\\\SPPUX\\\\SPPUX')) {
                    echo \SPPMod\SPPUX\SPPUX::component(\$name, \$props);
                } else {
                    \$propsJson = htmlspecialchars(json_encode(\$props), ENT_QUOTES, 'UTF-8');
                    \$context = \SPP\Scheduler::getContext();
                    \$appBaseUri = defined('APP_BASE_URI') ? APP_BASE_URI : '';
                    \$path = rtrim(\$appBaseUri, '/') . \"/src/{\$context}/comp/{\$name}.js\";
                    echo \"<div data-spp-component='1' data-spp-type='ux' data-spp-path='{\$path}' data-spp-props='{\$propsJson}'></div>\";
                }
            ?>";
        });

        // @drupal_node(123)
        $this->engine->directive('drupal_node', function ($expression) {
            return "<?php 
                \$drupal = \SPP\App::getApp()->make('drupal');
                \$node = \$drupal->getNode((int)$expression);
                if (\$node) {
                    echo \"<div class='drupal-node'>\";
                    echo \"<h3>\" . \$node->getTitle() . \"</h3>\";
                    echo \$node->get('body')->view('full');
                    echo \"</div>\";
                }
            ?>";
        });

        // @drupal_view('recent_news', 'block_1')
        $this->engine->directive('drupal_view', function ($expression) {
            return "<?php 
                \$drupal = \SPP\App::getApp()->make('drupal');
                if (\$drupal->bootstrap()) {
                    \$args = [$expression];
                    \$view = \Drupal\views\Views::getView(\$args[0]);
                    if (\$view) {
                        \$view->setDisplay(\$args[1] ?? 'default');
                        echo \$view->render();
                    }
                }
            ?>";
        });

        // @sppoffline('key')
        // Encapsulates template layout structures specifically cached for zero-latency offline client renders
        $this->engine->directive('sppoffline', function ($expression) {
            $key = trim($expression, " '\"");
            return "<?php echo \"<template data-spp-offline='\" . htmlspecialchars(\"{$key}\", ENT_QUOTES, 'UTF-8') . \"'>\"; ?>";
        });

        // @endsppoffline
        $this->engine->directive('endsppoffline', function () {
            return "<?php echo \"</template>\"; ?>";
        });

        // @load_node
        // Fully genericized context-aware entity binding adapting seamlessly to multi-tenant runtime targets
        $this->engine->directive('load_node', function () {
            return "<?php 
                if (empty(\$node)) {
                    \$__nodeId = \$_GET['id'] ?? (\$params[0] ?? null);
                    if (!empty(\$__nodeId)) {
                        try {
                            // Resolve specific database schema tables dynamically respecting global configuration mappings
                            \$__tbl = \\SPPMod\\SPPDB\\SPPDB::sppTable('nodes');
                            
                            \$__db = new \\SPPMod\\SPPDB\\SPPDB();
                            \$__res = \$__db->execute_query(\"SELECT * FROM {\$__tbl} WHERE id = ? OR alias = ?\", [\$__nodeId, \$__nodeId]);
                            if (!empty(\$__res)) {
                                \$node = (object)\$__res[0];
                            }
                        } catch (\Exception \$e) {}
                    }
                }
                if (empty(\$node)) {
                    \$node = (object)['title' => 'Live Declarative Node', 'body' => '<p>Ready-to-mount preview template fragment.</p>', 'created' => date('Y-m-d H:i:s')];
                }
            ?>";
        });

        // @load_page
        // Fully genericized dynamic landing page layout binding across disparate app namespaces
        $this->engine->directive('load_page', function () {
            return "<?php 
                if (empty(\$page)) {
                    \$__pageId = \$_GET['id'] ?? (\$params[0] ?? null);
                    if (!empty(\$__pageId)) {
                        try {
                            // Discover available framework or localized target models dynamically
                            \$__pageClass = '\\\\SPPMod\\\\Lekhak\\\\Core\\\\LandingPage';
                            \$__ctx = \SPP\Scheduler::getContext();
                            \$__appClass = \"\\\\App\\\\\" . ucfirst(\$__ctx) . \"\\\\Models\\\\LandingPage\";
                            
                            if (class_exists(\$__appClass)) {
                                \$__pageClass = \$__appClass;
                            }
                            if (class_exists(\$__pageClass) && method_exists(\$__pageClass, 'find_one')) {
                                \$page = \$__pageClass::find_one(is_numeric(\$__pageId) ? ['id' => \$__pageId] : ['alias' => \$__pageId]);
                            }
                        } catch (\Exception \$e) {}
                    }
                }
                if (empty(\$page)) {
                    \$page = (object)['title' => 'Live Scaffolding Screen Layout', 'alias' => 'preview-mode', 'id' => 1];
                }
            ?>";
        });
    }

    protected static ?self $instance = null;

    /**
     * Get singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Static wrapper for rendering.
     */
    public static function render(string $view, array $data = []): string
    {
        return self::getInstance()->renderInstance($view, $data);
    }

    public function renderInstance(string $view, array $data = []): string
    {
        @file_put_contents(SPP_LOG_DIR . '/debug_lekhak.log', "[".date('Y-m-d H:i:s')."] SPPBlade: Starting render for '$view'\n", FILE_APPEND);
        error_log("SPPBlade: Starting render for '$view'");

        // Support absolute paths
        if (file_exists($view) && str_ends_with($view, '.blade.php')) {
            $dir = dirname($view);
            $viewName = basename($view, '.blade.php');
            
            // Build search paths: 
            // 1. The directory containing the file (for immediate includes)
            // 2. The 'views' root of the theme (for layouts/extends)
            // 3. The application's base views (fallback)
            $paths = [$dir];
            
            $themeViewsRoot = $dir;
            $maxDepth = 5;
            while ($maxDepth > 0 && basename($themeViewsRoot) !== 'views' && dirname($themeViewsRoot) !== $themeViewsRoot) {
                $themeViewsRoot = dirname($themeViewsRoot);
                $maxDepth--;
            }
            if (basename($themeViewsRoot) === 'views' && !in_array($themeViewsRoot, $paths)) {
                $paths[] = $themeViewsRoot;
            }
            
            if (!in_array($this->viewsPath, $paths)) {
                $paths[] = $this->viewsPath;
            }

            // Set paths: prioritize the theme directory, then the default views
            $this->engine->setPath($paths, $this->cachePath);
            
            @file_put_contents(SPP_LOG_DIR . '/debug_lekhak.log', "[".date('Y-m-d H:i:s')."] SPPBlade: Rendering absolute file. ViewName: $viewName, Paths: " . json_encode($paths) . "\n", FILE_APPEND);

            try {
                $output = $this->engine->run($viewName, $data);
                @file_put_contents(SPP_LOG_DIR . '/debug_lekhak.log', "[".date('Y-m-d H:i:s')."] SPPBlade: Render successful for $viewName\n", FILE_APPEND);
                return $output;
            } catch (\Exception $e) {
                $msg = "SPPBlade ERROR (Absolute): " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine();
                @file_put_contents(SPP_LOG_DIR . '/debug_lekhak.log', "[".date('Y-m-d H:i:s')."] $msg\n", FILE_APPEND);
                return "Blade Error: " . $e->getMessage();
            }
        }

        // Ensure default paths for relative views
        $this->engine->setPath($this->viewsPath, $this->cachePath);
        @file_put_contents(SPP_LOG_DIR . '/debug_lekhak.log', "[".date('Y-m-d H:i:s')."] SPPBlade: Rendering relative view '$view' with paths: " . json_encode($this->viewsPath) . "\n", FILE_APPEND);

        // Support full paths by stripping base view path if present (Legacy/Fallback)
        if (strpos($view, $this->viewsPath) === 0) {
            $view = substr($view, strlen($this->viewsPath));
            $view = ltrim($view, '/\\');
            $view = str_replace('.blade.php', '', $view);
            $view = str_replace(['/', '\\'], '.', $view);
        }
        
        try {
            return $this->engine->run($view, $data);
        } catch (\Exception $e) {
            $msg = "SPPBlade ERROR (Relative): " . $e->getMessage();
            @file_put_contents(SPP_LOG_DIR . '/debug_lekhak.log', "[".date('Y-m-d H:i:s')."] $msg\n", FILE_APPEND);
            return "Blade Error: " . $e->getMessage();
        }
    }

    /**
     * Ensure views and cache directories exist.
     */
    protected function ensureDirectories(): void
    {
        if (!is_dir($this->viewsPath)) {
            mkdir($this->viewsPath, 0777, true);
        }
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0777, true);
        }
    }

    /**
     * Get the underlying engine instance.
     */
    public function getEngine(): BladeOne
    {
        return $this->engine;
    }
}
