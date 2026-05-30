<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class SysBridgeInfoCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        require_once SPP_APP_DIR . '/spp/sppinit.php';
                 if (!class_exists('\SPP\PolyglotBridge')) die("Error: PolyglotBridge core not found.\n");
                 
                 echo "SPP Polyglot Bridge Diagnostics\n";
                 echo "===============================\n";
                 $runtimes = \SPP\PolyglotBridge::discoverRuntimes();
                 echo "\nDetected Runtimes:\n";
                 foreach ($runtimes as $id => $info) {
                     echo "  " . strtoupper($id) . ":\n";
                     echo "    Path    : " . ($info['path'] ?: "NOT DETECTED") . "\n";
                     echo "    Version : " . ($info['version'] ?: "N/A") . "\n";
                 }
                 
                 $sharedDir = \SPP\Module::getConfig('shared_dir', 'bridge') ?: 'var/shared';
                 if (!str_starts_with($sharedDir, '/') && !str_contains($sharedDir, ':')) {
                     $sharedDir = SPP_BASE_DIR . SPP_DS . '..' . SPP_DS . $sharedDir;
                 }
                 $bridgeFile = $sharedDir . SPP_DS . 'bridge_config.json';
                 echo "\nBridge Status:\n";
                 echo "  Shared Dir: " . realpath($sharedDir) . "\n";
                 echo "  Config    : " . (file_exists($bridgeFile) ? "AVAILABLE" : "MISSING") . "\n";
                 if (file_exists($bridgeFile)) {
                      echo "  Last Sync : " . date("Y-m-d H:i:s", filemtime($bridgeFile)) . "\n";
                 }
                 echo "\n";
    }

    public function getName(): string
    {
        return 'sys:bridge:info';
    }

    public function getDescription(): string
    {
        return 'Legacy port of sys:bridge:info';
    }
}
