<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class SysInfoCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        require_once SPP_APP_DIR . '/spp/sppinit.php';
                echo "SPP System Information\n";
                echo "======================\n";
        
                $db_status = "Disconnected";
                $db_server = "N/A";
                $db_name = "N/A";
                $db_tables = "N/A";
                try {
                    $db = new \SPPMod\SPPDB\SPPDB();
                    $db_status = "Connected (" . $db->getAttribute(\PDO::ATTR_DRIVER_NAME) . ")";
                    $db_server = $db->getAttribute(\PDO::ATTR_SERVER_VERSION);
                    $db_name = \SPP\Module::getConfig('dbname', 'sppdb') ?: 'N/A';
                    $tableCount = $db->execute_query("SELECT count(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE()");
                    $db_tables = $tableCount[0]['cnt'] ?? '0';
                } catch (\Exception $e) {
                    $db_status = "Error: " . $e->getMessage();
                }
        
                $stats = ['apps' => 0, 'modules' => 0, 'entities' => 0, 'forms' => 0];
                if (defined('APP_ETC_DIR') && is_dir(APP_ETC_DIR)) {
                    $apps = array_filter(scandir(APP_ETC_DIR), function ($d) {
                        return $d !== '.' && $d !== '..' && is_dir(APP_ETC_DIR . DIRECTORY_SEPARATOR . $d);
                    });
                    $stats['apps'] = count($apps);
                    $entDir = APP_ETC_DIR . '/default/entities';
                    if (is_dir($entDir)) {
                        $ents = glob($entDir . '/*.yml');
                        $stats['entities'] = count($ents ?: []);
                    }
                    $formDir = APP_ETC_DIR . '/default/forms';
                    if (is_dir($formDir)) {
                        $forms = glob($formDir . '/*.{yml,xml}', GLOB_BRACE);
                        $stats['forms'] = count($forms ?: []);
                    }
                }
                if (class_exists('\\SPP\\Module')) {
                    \SPP\Module::loadAllModules();
                    $mods = \SPP\Registry::get('__mods');
                    $stats['modules'] = is_array($mods) ? count($mods) : 0;
                }
        
                $info = [
                    'SPP Version'      => defined('SPP_VER') ? SPP_VER : 'Unknown',
                    'PHP Version'      => PHP_VERSION,
                    'PHP SAPI'         => php_sapi_name(),
                    'OS'               => PHP_OS,
                    'Database Status'  => $db_status,
                    'DB Name'          => $db_name,
                    'DB Server'        => $db_server,
                    'DB Total Tables'  => $db_tables,
                    'Base Directory'   => SPP_BASE_DIR,
                    'App Directory'    => SPP_APP_DIR,
                    'Registered Apps'  => $stats['apps'],
                    'Loaded Modules'   => $stats['modules'],
                    'Entities (default)' => $stats['entities'],
                    'Forms (default)'    => $stats['forms']
                ];
        
                echo "\nFramework & Environment:\n";
                foreach ($info as $metric => $value) {
                    echo "  " . str_pad($metric, 20) . ": " . $value . "\n";
                }
        
                $php_metrics = [
                    'Memory Limit'       => ini_get('memory_limit'),
                    'Max Execution Time' => ini_get('max_execution_time') . 's',
                    'Upload Max Size'    => ini_get('upload_max_filesize'),
                    'Post Max Size'      => ini_get('post_max_size'),
                    'Display Errors'     => ini_get('display_errors') ? 'On' : 'Off',
                    'Error Log'          => ini_get('error_log') ?: 'Syslog'
                ];
                foreach ($php_metrics as $metric => $value) {
                    echo "  " . str_pad($metric, 20) . ": " . $value . "\n";
                }
        
                echo "\nPolyglot Runtimes:\n";
                $bridgeInfo = [];
                if (class_exists('\SPP\PolyglotBridge')) {
                     $runtimes = \SPP\PolyglotBridge::discoverRuntimes();
                     foreach ($runtimes as $id => $info) {
                         echo "  " . str_pad($info['name'], 20) . ": " . ($info['path'] ?: "Not Found") . ($info['version'] ? " (" . $info['version'] . ")" : "") . "\n";
                     }
                }
        
                echo "\nResource Bridge status:\n";
                $sharedDir = \SPP\Module::getConfig('shared_dir', 'bridge') ?: 'var/shared';
                if (!str_starts_with($sharedDir, '/') && !str_contains($sharedDir, ':')) {
                    $sharedDir = SPP_BASE_DIR . SPP_DS . '..' . SPP_DS . $sharedDir;
                }
        
                $bridge_file = $sharedDir . SPP_DS . 'bridge_config.json';
                $bridge_ready = file_exists($bridge_file);
                
                echo "  " . str_pad("Shared Directory", 20) . ": " . realpath($sharedDir) . " (" . (is_dir($sharedDir) ? "Ready" : "Missing") . ")\n";
                echo "  " . str_pad("Config Export", 20) . ": " . ($bridge_ready ? "Active (Generated)" : "Inactive") . "\n";
        
                echo "\n";
    }

    public function getName(): string
    {
        return 'sys:info';
    }

    public function getDescription(): string
    {
        return 'Legacy port of sys:info';
    }
}
