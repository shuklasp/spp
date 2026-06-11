<?php
namespace Lekhak\Modules\LekhakToolbar;

/**
 * Provides the administrative toolbar at the top of the page for quick access to tools.
 * @configure admin/config/lekhak_toolbar
 */

class LekhakModuleAdminToolbar {
    private $name = 'lekhak_toolbar';
    private $title = 'Admin Toolbar';

    public function hook_init() {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_lekhak_toolbar_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");
            
            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_lekhak_toolbar_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
        } catch (\Exception $e) {}
        return true;
    }

    /**
     * Intercepts page rendering to inject the HTML and CSS for the toolbar.
     */
    public function hook_page_meta_alter(&$meta, $context = []) {
        // Quick check if user is admin.
        // We rely on the presence of spp_admin_fallback session or SPPAuth.
        $isAdmin = isset($_SESSION['spp_admin_fallback']) || 
                  (class_exists('\SPPMod\SPPAuth\SPPAuth') && \SPPMod\SPPAuth\SPPAuth::authSessionExists());
                  
        if ($isAdmin) {
            $toolbarHtml = '
            <style>
                #lekhak-admin-toolbar {
                    position: fixed;
                    top: 0; left: 0; width: 100%; height: 39px;
                    background: #111827; color: #fff;
                    z-index: 999999; display: flex; align-items: center;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    font-size: 13px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                }
                #lekhak-admin-toolbar a {
                    color: #d1d5db; text-decoration: none; padding: 0 15px; height: 100%;
                    display: flex; align-items: center; border-right: 1px solid #374151;
                }
                #lekhak-admin-toolbar a:hover {
                    background: #374151; color: #fff;
                }
                body { padding-top: 39px !important; }
            </style>
            <div id="lekhak-admin-toolbar">
                <a href="/lekhak/admin" style="font-weight:bold; color:#fff;">Lekhak</a>
                <a href="/lekhak/admin#content">Content</a>
                <a href="/lekhak/admin#structure">Structure</a>
                <a href="/lekhak/admin#appearance">Appearance</a>
                <a href="/lekhak/admin#modules">Extend</a>
                <a href="/lekhak/admin#config">Configuration</a>
                <div style="flex-grow:1;"></div>
                <a href="/lekhak/logout" style="border-left:1px solid #374151; border-right:none;">Log out</a>
            </div>
            ';
            
            if (!isset($meta['tags'])) $meta['tags'] = [];
            $meta['tags'][] = $toolbarHtml;
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

    public function hook_entity_view_alter(&$build, $context = []) {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by lekhak_toolbar -->';
        } else {
            $build['#suffix'] = '<!-- Processed by lekhak_toolbar -->';
        }
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'lekhak_toolbar',
    'title' => 'Admin Toolbar',
    'instance' => new LekhakModuleAdminToolbar()
];
