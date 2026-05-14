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
        
        // Robust Path Normalization: Ensure we handle framework-delivered prefixes consistently
        $q = ltrim($q, '/');
        if (strpos($q, 'sppmobile') === 0) {
            $q = ltrim(substr($q, 9), '/');
        }
        $q = rtrim($q, '/');

        // 1. Handle Logout
        if ($q === 'logout') {
            \SPP\App::killSession();
            $baseUrl = rtrim(defined('APP_BASE_URI') ? APP_BASE_URI : '', '/');
            header("Location: " . $baseUrl . "/sppmobile/login");
            exit;
        }

        // 2. Handle API requests first
        if ($q === 'api.php' || $q === 'api') {
            require_once __DIR__ . '/api.php';
            return;
        }

        // 2. Isolated Studio Login Route
        if ($q === 'login' || $q === 'login.php') {
            require_once __DIR__ . '/login.php';
            return;
        }

        // 3. Isolated Auth Protection (No connection to /admin)
        $sessionUser = null;
        if (\SPP\SPPSession::sessionVarExists('studio_user')) {
            $sessionUser = \SPP\SPPSession::getSessionVar('studio_user');
        }

        if (!$sessionUser) {
            // Force absolute redirect to ensure it works across all environments
            $baseUrl = rtrim(defined('APP_BASE_URI') ? APP_BASE_URI : '', '/');
            $loginUrl = $baseUrl . '/sppmobile/login';
            header("Location: $loginUrl"); 
            exit;
        }

        // 4. Studio-Specific RBAC
        $role = $sessionUser['role'] ?? 'viewer';
        $rights = $this->getRightsForRole($role);

        // --- Directory Routing Engine ---
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
            \SPPMod\SPPView\ViewPage::addJsIncludeFile('https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js');
            \SPPMod\SPPView\ViewPage::addJsIncludeFile('res/spp/js/monaco.js');
            \SPPMod\SPPView\ViewPage::addCssIncludeFile('css/mobile.css');
            \SPPMod\SPPView\ViewPage::addJsIncludeFile('js/blueprints.js');
            \SPPMod\SPPView\ViewPage::addJsIncludeFile('js/mobile-app.js', ['type' => 'module']);
            
            // Delegate to framework's showPage
            \SPPMod\SPPView\ViewPage::showPage(null, ['augment' => false]);
            return;
        }

        // Fallback for non-view environments
        require_once __DIR__ . '/index.php';
    }

    /**
     * Internal Rights Mapper
     * Defines what each role can do within the studio.
     */
    private function getRightsForRole(string $role): array {
        $matrix = [
            'admin' => ['studio_view', 'studio_edit', 'studio_save', 'studio_sync', 'studio_build', 'api_access'],
            'developer' => ['studio_view', 'studio_edit', 'studio_save', 'studio_sync', 'api_access'],
            'designer' => ['studio_view', 'studio_edit', 'studio_save', 'api_access'],
            'viewer' => ['studio_view', 'api_access']
        ];
        return $matrix[$role] ?? $matrix['viewer'];
    }
}
