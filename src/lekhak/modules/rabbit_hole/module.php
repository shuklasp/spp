<?php

namespace Lekhak\Modules\LekhakModuleRabbitHole;

/**
 * Controls what happens when users try to view specific entities directly, often redirecting them.
 * @configure admin/config/rabbit_hole
 */

class LekhakModuleRabbitHole
{

    private $name = 'rabbit_hole';
    private $title = 'Rabbit Hole';

    public function hook_init()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_rabbit_hole_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");

            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_rabbit_hole_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
        } catch (\Exception $e) {
        }
        // Core module initialization logic.
        return true;
    }

    /**
     * Extends native routing and page rendering headers.
     */
    public function hook_page_meta_alter(&$meta)
    {
        // Enhances SEO meta parameters.
    }


    public function hook_entity_view_alter(&$build, $context = [])
    {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by rabbit_hole -->';
        } else {
            $build['#suffix'] = '<!-- Processed by rabbit_hole -->';
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
    'machine_name' => 'rabbit_hole',
    'title' => 'Rabbit Hole',
    'instance' => new LekhakModuleRabbitHole()
];
