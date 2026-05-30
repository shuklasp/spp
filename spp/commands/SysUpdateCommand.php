<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class SysUpdateCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        // Mock environment for sppinit.php
                $_SERVER['DOCUMENT_ROOT'] = SPP_APP_DIR;
                $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
                $_SERVER['REQUEST_METHOD'] = 'GET';
                if (!isset($_SESSION)) {
                    $_SESSION = [];
                }
                
                require_once SPP_APP_DIR . '/spp/sppinit.php';
        
                echo "SPP System Update Tool\n";
                echo "======================\n";
                
                echo "Scanning and applying updates...\n";
                try {
                    // Ensure routing schemas are present
                    echo "  [INFO] Initializing Routing schemas (Pages & Services)...\n";
                    \SPPMod\SPPView\Pages::ensureDbSchema();
                    \SPPMod\SPPAjax\SPPAjax::ensureDbSchema();
        
                    $log = \SPP\Module::runSystemUpdate();
                    if (empty($log)) {
                        echo "System is already up to date.\n";
                    } else {
                        foreach ($log as $line) {
                            echo "  [OK] {$line}\n";
                        }
                        echo "\nSuccess: System update completed.\n";
                    }
                } catch (\Exception $e) {
                    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
                    exit(1);
                }
    }

    public function getName(): string
    {
        return 'sys:update';
    }

    public function getDescription(): string
    {
        return 'Legacy port of sys:update';
    }
}
