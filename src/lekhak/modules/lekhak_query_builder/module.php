<?php
namespace Lekhak\Modules\LekhakQueryBuilder;

/**
 * A UI for constructing complex database queries and exposing them as views or APIs.
 * @configure admin/config/lekhak_query_builder
 */

class LekhakModuleViews {
    private $name = 'lekhak_query_builder';
    private $title = 'lekhak_query_builder';

    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_query_builder', '\\Lekhak\\Modules\\LekhakQueryBuilder\\LekhakModuleViews');
    }

    public function hook_init() {
        return true;
    }

    protected function getViewsConfig() {
        $file = SPP_APP_DIR . '/var/views.json';
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?: [];
        }
        return [];
    }

    /**
     * Intercept requests to serve "Page" displays automatically.
     */
    public function hook_request_init() {
        if (php_sapi_name() === 'cli') return;
        
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        // Remove base url if necessary (assuming standalone, but taking care of /school1 if present)
        $base = defined('APP_BASE_URI') ? rtrim(APP_BASE_URI, '/') : '';
        if ($base && strpos($path, $base) === 0) {
            $path = substr($path, strlen($base));
        }

        $views = $this->getViewsConfig();
        foreach ($views as $viewId => $view) {
            if (empty($view['displays'])) continue;
            foreach ($view['displays'] as $display) {
                if ($display['type'] === 'page' && !empty($display['path'])) {
                    // Check if path matches
                    $displayPath = '/' . ltrim($display['path'], '/');
                    if ($path === $displayPath) {
                        // We have a match! Execute the view.
                        $controller = new \App\Lekhak\Serv\ViewsBuilderController();
                        $html = $controller->executeView($viewId, $display['id']);
                        
                        // For API Infinite Scroll, the controller already calls exit.
                        
                        // Theme wrapping and class injection
                        $app = class_exists('\SPP\App') ? \SPP\App::getApp() : null;
                        $themeName = $app ? $app->getAppConf('theme') : 'eduxpro';
                        $themeNameLower = strtolower((string)$themeName);
                        
                        $themeClasses = 'spp-view-page';
                        if (str_contains($themeNameLower, 'drupal')) {
                            $themeClasses .= ' view-content views-element-container page-view';
                        } elseif (str_contains($themeNameLower, 'wordpress') || str_contains($themeNameLower, 'wp')) {
                            $themeClasses .= ' wp-view post-type-archive entry-content';
                        } elseif (str_contains($themeNameLower, 'joomla')) {
                            $themeClasses .= ' joomla-view item-page blog';
                        }
                        
                        $pageTitle = htmlspecialchars($display['name'] ?? $view['name']);
                        $wrappedHtml = "<div class='{$themeClasses}'><h1>{$pageTitle}</h1>{$html}</div>";
                        
                        // Let SPP Core wrap it in the active theme
                        if (class_exists('\SPP\SPPEvent')) {
                            $renderParams = [
                                'html'     => &$wrappedHtml,
                                'pageData' => ['title' => $pageTitle],
                                'theme'    => $themeName
                            ];
                            \SPP\SPPEvent::fireEvent('event_spp_view_render_theme', $renderParams);
                            echo $renderParams['html'];
                        } else {
                            // Fallback
                            echo "<!DOCTYPE html><html><head><title>{$pageTitle}</title></head><body>{$wrappedHtml}</body></html>";
                        }
                        
                        exit; // Terminate request
                    }
                }
            }
        }
    }

    /**
     * Expose all Views with a 'block' display plugin as placeable native Lekhak blocks.
     */
    public function hook_block_alter(&$blocks) {
        $views = $this->getViewsConfig();
        foreach ($views as $viewId => $view) {
            if (empty($view['displays'])) continue;
            foreach ($view['displays'] as $display) {
                if ($display['type'] === 'block') {
                    $blockId = 'views_' . $viewId . '_' . $display['id'];
                    $blocks[$blockId] = [
                        'title' => 'View: ' . ($display['name'] ?? $view['name']),
                        'handler' => function() use ($viewId, $display) {
                            $controller = new \App\Lekhak\Serv\ViewsBuilderController();
                            return $controller->executeView($viewId, $display['id']);
                        }
                    ];
                }
            }
        }
    }

    /**
     * Defines the configuration form schema for this module.
     */
    public static function hook_config_form(): array
    {
        return [
  'enabled' => 
  [
    'type' => 'checkbox',
    'title' => 'Enable advanced features',
    'default' => true,
  ],
  'log_level' => 
  [
    'type' => 'select',
    'title' => 'Log Level',
    'options' => 
    [
      'info' => 'Info',
      'warning' => 'Warning',
      'error' => 'Error',
    ],
    'default' => 'warning',
  ],
];
    }
}

// Return instance for the older ModuleRegistry format just in case
return [
    'status' => 'enabled',
    'machine_name' => 'lekhak_query_builder',
    'title' => 'lekhak_query_builder',
    'instance' => new LekhakModuleViews()
];
