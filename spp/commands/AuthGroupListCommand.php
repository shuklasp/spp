<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AuthGroupListCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        require_once SPP_APP_DIR . '/spp/sppinit.php';
                $groups = \SPPMod\SPPGroup\SPPGroupLoader::listAllGroups();
                $rows = [];
                foreach ($groups as $g) {
                    $groupObj = new \SPPMod\SPPGroup\SPPGroup();
                    $groupObj->load($g['name']);
                    $members = $groupObj->getMembers(false);
                    $rows[] = [$g['name'], $groupObj->get('name'), $g['source'], count($members)];
                }
                printTable(['Slug/ID', 'Name', 'Source', 'Direct Members'], $rows);
    }

    public function getName(): string
    {
        return 'auth:group:list';
    }

    public function getDescription(): string
    {
        return 'Legacy port of auth:group:list';
    }
}
