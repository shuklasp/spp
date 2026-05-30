<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AuthRoleAssignCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $rid = $args[2] ?? null;
                $rightid = $args[3] ?? null;
                if (!$rid || !$rightid) die("Usage: php spp.php {$command} <roleid> <rightid>\n");
                require_once SPP_APP_DIR . '/spp/sppinit.php';
                try {
                    if ($command === 'auth:role:assign') {
                        \SPPMod\SPPAuth\SPPRole::assignRight((int)$rid, (int)$rightid);
                        echo "Success: Right assigned to role.\n";
                    } else {
                        \SPPMod\SPPAuth\SPPRole::unassignRight((int)$rid, (int)$rightid);
                        echo "Success: Right unassigned from role.\n";
                    }
                } catch (\Exception $e) {
                    echo "Error: " . $e->getMessage() . "\n";
                }
    }

    public function getName(): string
    {
        return 'auth:role:assign';
    }

    public function getDescription(): string
    {
        return 'Legacy port of auth:role:assign';
    }
}
