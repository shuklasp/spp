<?php
namespace Lekhak\Modules\LekhakPeeche;

use SPP\SPPConfig;
use SPP\App;

class LekhakModulePeeche {
    public static function hook_config_form(): array {
        return [
            'lekhak_peeche_fallback_url' => [
                'type' => 'text',
                'label' => 'Fallback URL',
                'description' => 'The relative URL to send users to when they press back (e.g. / for home).',
                'default' => '/',
            ],
            'lekhak_peeche_admin_prefix' => [
                'type' => 'text',
                'label' => 'Admin Path Prefix',
                'description' => 'Paths starting with this prefix will be excluded from the script.',
                'default' => '/admin',
            ],
        ];
    }

    public static function hook_page_render_alter(&$html) {
        $fallback_url = SPPConfig::get('lekhak_peeche_fallback_url', '/');
        $admin_prefix = SPPConfig::get('lekhak_peeche_admin_prefix', '/admin');

        $current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $base_url = App::getBaseUrl();
        
        // Exclude admin paths
        if (strpos($current_path, $base_url . $admin_prefix) === 0) {
            return;
        }

        // Add base URL to fallback
        $full_fallback_url = rtrim($base_url, '/') . '/' . ltrim($fallback_url, '/');
        
        $script = <<<HTML
<script>
(function() {
    var fallbackUrl = "{$full_fallback_url}";
    // Check if we already injected history for this page load
    if (sessionStorage.getItem('peeche_init_' + location.href)) return;
    
    var isExternal = false;
    var ref = document.referrer;
    if (!ref || ref.indexOf(location.origin) !== 0) {
        isExternal = true;
    }

    if (isExternal && location.pathname !== fallbackUrl && location.pathname !== fallbackUrl + '/') {
        sessionStorage.setItem('peeche_init_' + location.href, '1');
        history.replaceState({peeche: 'fallback'}, '', fallbackUrl);
        history.pushState({peeche: 'current'}, '', location.href);
    }

    window.addEventListener('popstate', function(event) {
        if (event.state && event.state.peeche === 'fallback') {
            window.location.reload();
        }
    });
})();
</script>
HTML;

        // Inject script right before closing body tag, or append if no body tag found
        if (strpos($html, '</body>') !== false) {
            $html = str_replace('</body>', $script . "\n</body>", $html);
        } else {
            $html .= "\n" . $script;
        }
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'lekhak_peeche',
    'title' => 'Lekhak Peeche',
    'instance' => new LekhakModulePeeche()
];
