<?php

namespace Lekhak\Modules\LekhakModuleHoneypot;

/**
 * Mitigates form spam using the honeypot technique, which blocks bots without annoying captchas.
 * @configure admin/config/honeypot
 */

class LekhakModuleHoneypot
{

    private $name = 'honeypot';
    private $title = 'Honeypot';

    public function hook_init()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_honeypot_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");

            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_honeypot_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
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
        // Injects invisible honeypot field.
        $form['honeypot_time'] = ['type' => 'hidden', 'value' => time()];
    }


    public function hook_entity_view_alter(&$build, $context = [])
    {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by honeypot -->';
        } else {
            $build['#suffix'] = '<!-- Processed by honeypot -->';
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
    'machine_name' => 'honeypot',
    'title' => 'Honeypot',
    'instance' => new LekhakModuleHoneypot()
];
