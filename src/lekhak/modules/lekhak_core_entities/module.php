<?php

namespace Lekhak\Modules\LekhakModuleEntityApi;

/**
 * Defines the fundamental content and configuration entity types used by the CMS.
 * @configure admin/config/lekhak_core_entities
 */

class LekhakModuleEntityApi
{

    private $name = 'lekhak_core_entities';
    private $title = 'Entity API';

    public function hook_init()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_entity_api_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");

            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_entity_api_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
        } catch (\Exception $e) {
        }
        // Core module initialization logic.
        return true;
    }

    /**
     * Extends native Lekhak Block and View capabilities.
     */
    public function hook_block_alter(&$blocks)
    {
        // Extends site building capabilities via the block API.
    }


    public function hook_entity_view_alter(&$build, $context = [])
    {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by entity_api -->';
        } else {
            $build['#suffix'] = '<!-- Processed by entity_api -->';
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
    'machine_name' => 'lekhak_core_entities',
    'title' => 'Entity API',
    'instance' => new LekhakModuleEntityApi()
];
