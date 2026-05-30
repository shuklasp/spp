<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AuthGroupMemberAddCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $slug = $args[2] ?? null;
                $mid = $args[3] ?? null;
                $class = $args[4] ?? '\SPPMod\SPPAuth\SPPUser';
                if (!$slug || !$mid) die("Usage: php spp.php {$command} <slug> <member_id> [member_class]\n");
                require_once SPP_APP_DIR . '/spp/sppinit.php';
                try {
                    if ($command === 'auth:group:member:add') {
                        \SPPMod\SPPGroup\SPPGroup::addMemberToGroup($slug, $class, $mid);
                        echo "Success: Member added to group.\n";
                    } else {
                        \SPPMod\SPPGroup\SPPGroup::removeMemberFromGroup($slug, $class, $mid);
                        echo "Success: Member removed from group.\n";
                    }
                } catch (\Exception $e) {
                    echo "Error: " . $e->getMessage() . "\n";
                }
    }

    public function getName(): string
    {
        return 'auth:group:member:add';
    }

    public function getDescription(): string
    {
        return 'Legacy port of auth:group:member:add';
    }
}
