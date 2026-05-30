<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ViewServiceRemoveCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $name = $argv[2] ?? null;
                if (!$name) die("Usage: php spp.php view:service:remove <name>\n");
                require_once SPP_APP_DIR . '/spp/sppinit.php';
                if (\SPPMod\SPPAjax\SPPAjax::unregisterService($name, 'yaml')) {
                    echo "Success: Removed from YAML.\n";
                } else if (\SPPMod\SPPAjax\SPPAjax::unregisterService($name, 'db')) {
                    echo "Success: Removed from DB.\n";
                } else {
                    echo "Error: Service not found.\n";
                }
    }

    public function getName(): string
    {
        return 'view:service:remove';
    }

    public function getDescription(): string
    {
        return 'Legacy port of view:service:remove';
    }
}
