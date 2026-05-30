<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class EntDeleteCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $entityName = $argv[2] ?? null;
                if (!$entityName) die("Error: Entity name required.\n");
                require_once SPP_APP_DIR . '/spp/sppinit.php';
                
                $cfgFile = \SPPMod\SPPEntity\SPPEntity::getEntityConfigFile($entityName);
                if ($cfgFile && file_exists($cfgFile)) {
                    $confirm = prompt("Are you sure you want to delete entity '{$entityName}' configuration? (y/N)", "n");
                    if (strtolower($confirm) === 'y') {
                        unlink($cfgFile);
                        echo "Success: Entity definition deleted.\n";
                    }
                } else {
                    echo "Error: Entity definition not found for '{$entityName}'.\n";
                }
    }

    public function getName(): string
    {
        return 'ent:delete';
    }

    public function getDescription(): string
    {
        return 'Legacy port of ent:delete';
    }
}
