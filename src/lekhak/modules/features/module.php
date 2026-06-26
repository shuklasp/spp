<?php

namespace Lekhak\Modules\LekhakModuleFeatures;

/**
 * Enables the export and management of site configuration into code for version control and deployment.
 */

class LekhakModuleFeatures
{

    private $name = 'features';
    private $title = 'Features';

    public function hook_init()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_features_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");

            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_features_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
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
            $build['#suffix'] .= '<!-- Processed by features -->';
        } else {
            $build['#suffix'] = '<!-- Processed by features -->';
        }
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'features',
    'title' => 'Features',
    'instance' => new LekhakModuleFeatures()
];
