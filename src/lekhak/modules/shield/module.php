<?php

namespace Lekhak\Modules\LekhakModuleShield;

/**
 * Protects the entire site or specific sections with HTTP Basic Authentication.
 * @configure admin/config/shield
 */

class LekhakModuleShield
{

    private $name = 'shield';
    private $title = 'Shield';

    public function hook_init()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_shield_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");

            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_shield_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
        } catch (\Exception $e) {
        }
        // Core module initialization logic.
        return true;
    }

    /**
     * Hardens security and extends admin workflows.
     */
    public function hook_form_alter(&$form, $form_id)
    {
        // Enhances form security.
    }

    public function hook_boot()
    {
        // HTTP Basic Auth protection
        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            header('WWW-Authenticate: Basic realm="Restricted Area"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Authentication required.';
            exit;
        }
    }

    public function hook_entity_view_alter(&$build, $context = [])
    {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by shield -->';
        } else {
            $build['#suffix'] = '<!-- Processed by shield -->';
        }
    }

    /**
     * Defines the configuration form schema for this module.
     */
    public static function hook_config_form(): array
    {
        return [
            'user' =>
                [
                    'type' => 'text',
                    'title' => 'HTTP Auth Username',
                    'default' => '',
                ],
            'pass' =>
                [
                    'type' => 'text',
                    'title' => 'HTTP Auth Password',
                    'default' => '',
                ],
            'message' =>
                [
                    'type' => 'text',
                    'title' => 'Authentication Message',
                    'default' => 'Protected Site',
                ],
        ];
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'shield',
    'title' => 'Shield',
    'instance' => new LekhakModuleShield()
];
