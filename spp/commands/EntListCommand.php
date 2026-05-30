<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class EntListCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        require_once SPP_APP_DIR . '/spp/sppinit.php';
                $entities = \SPPMod\SPPEntity\SPPEntity::listAvailableEntities();
                echo "Detected Entity Definitions:\n";
                $rows = [];
                foreach ($entities as $e) {
                    $rows[] = [$e['name'], $e['table'], $e['modified']];
                }
                printTable(['Name', 'Table', 'Last Modified'], $rows);
    }

    public function getName(): string
    {
        return 'ent:list';
    }

    public function getDescription(): string
    {
        return 'Legacy port of ent:list';
    }
}
