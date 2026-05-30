<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AuthUserListCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        require_once SPP_APP_DIR . '/spp/sppinit.php';
                $users = \SPPMod\SPPAuth\SPPUser::find_all();
                $rows = [];
                foreach ($users as $u) {
                    $rows[] = [$u->id, $u->username, $u->email, $u->status];
                }
                printTable(['ID', 'Username', 'Email', 'Status'], $rows);
    }

    public function getName(): string
    {
        return 'auth:user:list';
    }

    public function getDescription(): string
    {
        return 'Legacy port of auth:user:list';
    }
}
