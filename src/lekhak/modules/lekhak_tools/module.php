<?php
namespace Lekhak\Modules\LekhakTools;

/**
 * A suite of developer utilities and administrative scripts for site maintenance.
 * @configure admin/config/lekhak_tools
 */

class LekhakModuleCtools
{
    private $name = 'lekhak_tools';
    private $title = 'Chaos Tool Suite';

    public function hook_init()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_block_visibility (
                block_id INTEGER PRIMARY KEY,
                paths TEXT,
                visibility_mode VARCHAR(10) DEFAULT 'show'
            )");
        } catch (\Exception $e) {
        }
        return true;
    }

    /**
     * Hook into the block rendering array to strip out blocks 
     * that shouldn't be visible on the current path.
     */
    public function hook_block_view_alter(&$blocks)
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH);

        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $rules = $db->execute_query("SELECT * FROM lekhak_block_visibility");
            $rulesMap = [];
            foreach ($rules as $r) {
                $rulesMap[$r['block_id']] = $r;
            }

            foreach ($blocks as $blockId => $blockData) {
                if (isset($rulesMap[$blockId])) {
                    $rule = $rulesMap[$blockId];
                    $paths = explode("\n", str_replace("\r", "", $rule['paths']));
                    $match = false;

                    // Basic wildcard matching
                    foreach ($paths as $path) {
                        $path = trim($path);
                        if (empty($path))
                            continue;
                        $pattern = str_replace('*', '.*', preg_quote($path, '/'));
                        if (preg_match('/^' . $pattern . '$/i', $uri)) {
                            $match = true;
                            break;
                        }
                    }

                    if ($rule['visibility_mode'] === 'show' && !$match) {
                        unset($blocks[$blockId]);
                    } elseif ($rule['visibility_mode'] === 'hide' && $match) {
                        unset($blocks[$blockId]);
                    }
                }
            }
        } catch (\Exception $e) {
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
    'machine_name' => 'lekhak_tools',
    'title' => 'Chaos Tool Suite',
    'instance' => new LekhakModuleCtools()
];
