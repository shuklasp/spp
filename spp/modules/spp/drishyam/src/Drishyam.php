<?php

namespace SPPMod\Drishyam;

use SPP\App;
use SPP\Module;
use SPP\Scheduler;

/**
 * Class Drishyam
 * The main orchestrator for the Drishyam theming engine.
 */
class Drishyam extends \SPP\SPPObject
{
    protected static ?self $instance = null;
    protected array $hooks = [];
    protected array $themes = [];
    protected array $contextThemes = [];
    protected string $defaultTheme = 'default';
    protected string $currentContext = 'site';
    protected ?Theme $activeTheme = null;
    protected array $customContextEvaluators = [];
    protected array $preWarmedCache = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void
    {

        $app = App::getApp();
        $themeDir = $app->getAppSrcDir() . '/resources/themes';
        $this->scanThemes($themeDir);
        
        if (isset($_GET['__svc']) && $_GET['__svc'] === 'drishyam:list') {
            header('Content-Type: application/json; charset=utf-8');
            $list = [];
            foreach ($this->getThemes() as $name => $tObj) {
                $desc = $tObj->getConfig('description', 'Custom dynamic presentation workspace.');
                $origInfo = $tObj->getConfig('original_info');
                $ver = $origInfo ? ($origInfo['version'] ?? '1.0.0') : '1.0.0';
                $icon = $tObj->getConfig('ENGINE_MODE') === 'drupal' ? '💧' : ($tObj->getConfig('ENGINE_MODE') === 'wordpress' ? '📝' : '🔮');
                if ($name === 'eduxpro') $icon = '💧';
                
                $list[] = [
                    'id' => $name,
                    'title' => $tObj->getConfig('name', ucfirst($name)),
                    'ver' => $ver,
                    'type' => $tObj->getConfig('type', 'site'),
                    'desc' => strip_tags($desc),
                    'icon' => $icon
                ];
            }
            echo json_encode($list);
            exit;
        }
        
        $configPath = $app->getAppConfDir() . '/drishyam.yml';
        if (file_exists($configPath)) {
            $config = \Symfony\Component\Yaml\Yaml::parseFile($configPath);
            
            // Set default theme
            if (isset($config['default_theme'])) {
                $this->defaultTheme = $config['default_theme'];
            }

            // Set contextual themes (e.g., admin: premium_admin)
            if (isset($config['contexts'])) {
                $this->contextThemes = $config['contexts'];
            }
            
            // Register hooks from config
            if (isset($config['hooks'])) {
                foreach ($config['hooks'] as $event => $callback) {
                    $this->on($event, $callback);
                }
            }
        }

        // Auto-register declared asset paths in application config file into standard dynamic routes at boot time
        if (class_exists('\SPPMod\SPPView\Pages', true)) {
            $context = \SPP\Scheduler::getContext();
            $assetsConfig = \SPP\App::getAppConf('assets', $context);
            if (is_array($assetsConfig)) {
                foreach ($assetsConfig as $prefix => $targetDir) {
                    \SPPMod\SPPView\Pages::registerRoute($prefix, ['assets' => $targetDir], $context);
                }
            }
            
            // Also map standard layout theme assets prefix automatically to prevent unserved asset paths
            \SPPMod\SPPView\Pages::registerRoute('theme-assets', ['assets' => 'resources/themes'], $context);
        }

        $this->dispatch('drishyam.boot');
    }

    /**
     * Scan a directory for themes.
     */
    public function scanThemes(string $dir): void
    {
        if (!is_dir($dir)) return;

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $isDrishyam = file_exists($path . '/theme.yml');
                $isDrupal = !empty(glob($path . '/*.info.yml'));
                $isWordPress = file_exists($path . '/style.css');
                
                if ($isDrishyam || $isDrupal || $isWordPress) {
                    $this->themes[$item] = new Theme($item, $path);
                } else {
                    $this->scanThemes($path);
                }
            }
        }
    }

    /**
     * Register a hook for an event.
     */
    public function on(string $event, callable $callback): void
    {
        $this->hooks[$event][] = $callback;
    }

    /**
     * Dispatch an event with data.
     */
    public function dispatch(string $event, &$data = null): void
    {
        if (isset($this->hooks[$event])) {
            foreach ($this->hooks[$event] as $callback) {
                $callback($data, $this);
            }
        }
    }

    /**
     * Set the active theme for the current request.
     */
    public function setActiveTheme(string $themeName): void
    {
        if (isset($this->themes[$themeName])) {
            $this->activeTheme = $this->themes[$themeName];
            $this->dispatch('drishyam.theme_activated', $this->activeTheme);
        }
    }

    /**
     * Get the active theme, potentially negotiating by context.
     */
    public function getActiveTheme(): ?Theme
    {
        if ($this->activeTheme) return $this->activeTheme;

        // Negotiate by context (e.g. if we are in /admin)
        $context = $this->determineContext();
        
        // Dynamically resolve mapped user layout engine via synchronized presentation cookies
        if ($context === 'admin' && !empty($_COOKIE['lekhak_admin_theme_engine'])) {
            $cookieTheme = $_COOKIE['lekhak_admin_theme_engine'];
            if (isset($this->themes[$cookieTheme])) return $this->themes[$cookieTheme];
        } elseif ($context === 'site' && !empty($_COOKIE['lekhak_site_theme_engine'])) {
            $cookieTheme = $_COOKIE['lekhak_site_theme_engine'];
            if (isset($this->themes[$cookieTheme])) return $this->themes[$cookieTheme];
        }

        if (isset($this->contextThemes[$context])) {
            $themeName = $this->contextThemes[$context];
            return $this->themes[$themeName] ?? $this->themes[$this->defaultTheme] ?? null;
        }

        return $this->themes[$this->defaultTheme] ?? reset($this->themes) ?: null;
    }

    public function setContext(string $context): void
    {
        $this->currentContext = $context;
    }

    /**
     * Registers a hot-swappable custom declarative context evaluator callable.
     */
    public function registerContextOverride(callable $evaluator, string $themeName): void
    {
        $this->customContextEvaluators[] = ['evaluator' => $evaluator, 'theme' => $themeName];
    }

    /**
     * Determine current UI context (e.g., admin or site).
     */
    protected function determineContext(): string
    {
        if ($this->currentContext !== 'site') return $this->currentContext;

        foreach ($this->customContextEvaluators as $override) {
            if (($override['evaluator'])()) {
                $this->setActiveTheme($override['theme']);
                return $override['theme'];
            }
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_contains($uri, '/admin')) return 'admin';
        
        // Check Scheduler context for hint
        $context = \SPP\Scheduler::getContext();
        if (str_contains($context, 'admin')) return 'admin';

        return 'site';
    }

    /**
     * Pre-warms layout maps completely locally in memory to guarantee sub-microsecond presentation retrieval speeds.
     */
    public function preWarm(): void
    {
        foreach ($this->themes as $theme) {
            $viewsDir = rtrim($theme->getPath(), '/\\') . '/views';
            if (is_dir($viewsDir)) {
                $this->warmDir($viewsDir, $theme->getName());
            }
        }
    }

    protected function warmDir(string $dir, string $themeName): void
    {
        foreach (scandir($dir) as $f) {
            if ($f !== '.' && $f !== '..') {
                $p = $dir . '/' . $f;
                if (is_dir($p)) {
                    $this->warmDir($p, $themeName);
                } elseif (str_ends_with($f, '.blade.php')) {
                    $hash = md5_file($p);
                    $this->preWarmedCache[$themeName][$p] = $hash;
                }
            }
        }
    }

    public function getThemes(): array
    {
        return $this->themes;
    }

    /**
     * Static helper to trigger a render.
     */
    public static function render(string $view, array $data = []): string
    {
        return DrishyamRenderer::render($view, $data);
    }
}
