<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AuthGroupMemberListCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $slug = $argv[2] ?? null;
                if (!$slug) die("Usage: php spp.php auth:group:member:list <slug>\n");
                require_once SPP_APP_DIR . '/spp/sppinit.php';
                try {
                    $group = new \SPPMod\SPPGroup\SPPGroup();
                    $group->load($slug);
                    if (!$group->id) throw new \Exception("Group not found.");
        
                    $members = $group->getMembers(false);
                    $rows = [];
                    foreach ($members as $m) {
                        $ent = $m['entity'];
                        $rows[] = [$ent->getId(), get_class($ent), $m['role']];
                    }
                    echo "Direct Members of '{$slug}':\n";
                    printTable(['ID', 'Entity Class', 'Group Role'], $rows);
                } catch (\Exception $e) {
                    echo "Error: " . $e->getMessage() . "\n";
                }
    }

    public function getName(): string
    {
        return 'auth:group:member:list';
    }

    public function getDescription(): string
    {
        return 'Legacy port of auth:group:member:list';
    }
}
