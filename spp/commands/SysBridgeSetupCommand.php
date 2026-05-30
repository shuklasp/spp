<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class SysBridgeSetupCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        require_once SPP_APP_DIR . '/spp/sppinit.php';
                 if (!class_exists('\SPP\PolyglotBridge')) die("Error: PolyglotBridge core not found.\n");
                 
                 echo "Initiating Polyglot Bridge Setup...\n";
                 $res = \SPP\PolyglotBridge::setup();
                 if ($res['success']) {
                     foreach ($res['log'] as $line) {
                         echo "  [OK] {$line}\n";
                     }
                     echo "\nSuccess: Bridge environment refreshed.\n";
                 } else {
                     echo "\nError during setup: " . ($res['error'] ?? 'Unknown error') . "\n";
                 }
    }

    public function getName(): string
    {
        return 'sys:bridge:setup';
    }

    public function getDescription(): string
    {
        return 'Legacy port of sys:bridge:setup';
    }
}
