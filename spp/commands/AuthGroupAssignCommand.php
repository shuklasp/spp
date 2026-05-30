<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AuthGroupAssignCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $slug = $args[2] ?? null;
                $roleid = $args[3] ?? null;
                if (!$slug || !$roleid) die("Usage: php spp.php {$command} <group_slug> <roleid>\n");
                require_once SPP_APP_DIR . '/spp/sppinit.php';
                try {
                    $group = new \SPPMod\SPPGroup\SPPGroup();
                    $group->load($slug);
                    if (!$group->id) throw new \Exception("Group '{$slug}' not found.");
                    
                    if ($command === 'auth:group:assign') {
                        \SPPMod\SPPAuth\SPPRole::assignToEntity(get_class($group), $group->id, (int)$roleid);
                        echo "Success: Role assigned to group '{$group->id}'.\n";
                    } else {
                        \SPPMod\SPPAuth\SPPRole::unassignFromEntity(get_class($group), $group->id, (int)$roleid);
                        echo "Success: Role unassigned from group '{$group->id}'.\n";
                    }
                } catch (\Exception $e) {
                    echo "Error: " . $e->getMessage() . "\n";
                }
    }

    public function getName(): string
    {
        return 'auth:group:assign';
    }

    public function getDescription(): string
    {
        return 'Legacy port of auth:group:assign';
    }
}
