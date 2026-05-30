<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AuthRightListCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        require_once SPP_APP_DIR . '/spp/sppinit.php';
                $rights = \SPPMod\SPPAuth\SPPRight::find_all();
                $rows = [];
                foreach ($rights as $rt) {
                    $rows[] = [$rt->id, $rt->name, $rt->get('description')];
                }
                printTable(['ID', 'Right Name', 'Description'], $rows);
    }

    public function getName(): string
    {
        return 'auth:right:list';
    }

    public function getDescription(): string
    {
        return 'Legacy port of auth:right:list';
    }
}
