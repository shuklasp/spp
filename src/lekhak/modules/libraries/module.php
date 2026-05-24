<?php

namespace Lekhak\Modules\LekhakModuleLibraries;

/**
 * Manages the loading and dependencies of third-party JavaScript and PHP libraries.
 */

class LekhakModuleLibraries {

    private $name = 'libraries';
    private $title = 'Libraries API';

    public function hook_init() {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_libraries_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");
            
            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_libraries_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
        } catch (\Exception $e) {}
        // Core module initialization logic.
        return true;
    }

    /**
     * Extends native Lekhak Block and View capabilities.
     */
    public function hook_block_alter(&$blocks) {
        // Extends site building capabilities via the block API.
    }


    public function hook_entity_view_alter(&$build, $context = []) {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by libraries -->';
        } else {
            $build['#suffix'] = '<!-- Processed by libraries -->';
        }
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'libraries',
    'title' => 'Libraries API',
    'instance' => new LekhakModuleLibraries()
];
