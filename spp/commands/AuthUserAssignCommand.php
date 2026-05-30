<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AuthUserAssignCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $uid = $args[2] ?? null;
                $roleid = $args[3] ?? null;
                if (!$uid || !$roleid) die("Usage: php spp.php {$command} <userid> <roleid>\n");
                require_once SPP_APP_DIR . '/spp/sppinit.php';
                try {
                    if ($command === 'auth:user:assign') {
                        \SPPMod\SPPAuth\SPPUser::assignRole((int)$uid, (int)$roleid);
                        echo "Success: Role assigned to user.\n";
                    } else {
                        \SPPMod\SPPAuth\SPPUser::unassignRole((int)$uid, (int)$roleid);
                        echo "Success: Role unassigned from user.\n";
                    }
                } catch (\Exception $e) {
                    echo "Error: " . $e->getMessage() . "\n";
                }
    }

    public function getName(): string
    {
        return 'auth:user:assign';
    }

    public function getDescription(): string
    {
        return 'Legacy port of auth:user:assign';
    }
}
