<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AuthRoleListCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        require_once SPP_APP_DIR . '/spp/sppinit.php';
                $roles = \SPPMod\SPPAuth\SPPRole::find_all();
                $rows = [];
                foreach ($roles as $r) {
                    $rights = $r->getRights();
                    $rows[] = [$r->id, $r->role_name, count($rights)];
                }
                printTable(['ID', 'Role Name', 'Rights Count'], $rows);
    }

    public function getName(): string
    {
        return 'auth:role:list';
    }

    public function getDescription(): string
    {
        return 'Legacy port of auth:role:list';
    }
}
