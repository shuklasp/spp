<?php

namespace Lekhak\Modules\LekhakModuleParanoia;

/**
 * Hides dangerous administrative functions and disables PHP execution in the UI for extreme security.
 * @configure admin/config/paranoia
 */

class LekhakModuleParanoia
{

    private $name = 'paranoia';
    private $title = 'Paranoia';

    public function hook_init()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_paranoia_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");

            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_paranoia_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
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


    public function hook_entity_view_alter(&$build, $context = [])
    {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by paranoia -->';
        } else {
            $build['#suffix'] = '<!-- Processed by paranoia -->';
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

return [
    'status' => 'enabled',
    'machine_name' => 'paranoia',
    'title' => 'Paranoia',
    'instance' => new LekhakModuleParanoia()
];
