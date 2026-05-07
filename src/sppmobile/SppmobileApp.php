<?php
namespace App\Sppmobile;

/**
 * Mobile Studio Pro Application Controller
 * Handles framework-level routing and lifecycle for the isolated studio.
 */
class SppmobileApp extends \SPP\App {
    
    /**
     * Directory Routes Mapping
     * Maps logical URL prefixes to physical directories within the isolated studio.
     */
    private array $dirRoutes = [
        'js'     => 'js',
        'css'    => 'css',
        'assets' => 'assets',
        'etc'    => 'etc'
    ];

    /**
     * Handle incoming requests to the /sppmobile route.
     */
    public function handle($request) {
        $q = $_GET['q'] ?? '';
        
        // Handle API requests (Still isolated)
        if ($q === 'api.php' || $q === 'api') {
            require_once __DIR__ . '/api.php';
            return;
        }

        // --- NEW: Directory Routing Engine ---
        foreach ($this->dirRoutes as $route => $dir) {
            if ($q === $route || strpos($q, $route . '/') === 0) {
                $file = __DIR__ . '/' . $q;
                if (file_exists($file) && is_file($file)) {
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    $mimes = [
                        'js' => 'application/javascript', 'css' => 'text/css',
                        'png' => 'image/png', 'jpg' => 'image/jpeg', 'svg' => 'image/svg+xml',
                        'json' => 'application/json', 'woff2' => 'font/woff2'
                    ];
                    $mime = $mimes[$ext] ?? mime_content_type($file);
                    header("Content-Type: $mime");
                    header("Cache-Control: public, max-age=3600");
                    readfile($file);
                    exit;
                }
            }
        }

        // Use Framework Routines for Page Rendering
        if (class_exists('\SPPMod\SPPView\ViewPage')) {
            \SPPMod\SPPUX\SPPUX::boot();
            
            \SPPMod\SPPView\ViewPage::setPageTitle('SPP Mobile Studio | Visual Builder');
            
            // Disable framework headers/footers to reclaim space
            \SPPMod\SPPView\ViewPage::setPageHeader('');
            \SPPMod\SPPView\ViewPage::setPageFooter('');
            
            // Force hide any persistent framework UI elements
            \SPPMod\SPPView\ViewPage::addCssContent('.content-header, .system-bar, #system-header { display: none !important; }');
            
            // Register isolated studio assets through framework routines
            \SPPMod\SPPView\ViewPage::addCssIncludeFile('css/mobile.css');
            \SPPMod\SPPView\ViewPage::addJsIncludeFile('js/mobile-app.js', ['type' => 'module']);
            \SPPMod\SPPView\ViewPage::addJsContent("window.SPP_CONFIG = { apiEndpoint: 'api.php' };");

            // Delegate to framework's showPage
            \SPPMod\SPPView\ViewPage::showPage(null, ['augment' => false]);
            return;
        }

        // Fallback for non-view environments
        require_once __DIR__ . '/index.php';
    }
}
