<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AuthRoleEditCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $rid = $argv[2] ?? null;
                if (!$rid) die("Usage: php spp.php auth:role:edit <roleid>\n");
                require_once SPP_APP_DIR . '/spp/sppinit.php';
                
                try {
                    $role = new \SPPMod\SPPAuth\SPPRole($rid);
                    echo "Editing Role: {$role->role_name} (ID: {$role->id})\n";
                    $name = prompt("Role Name", $role->role_name);
                    $desc = prompt("Description", $role->description);
        
                    // Rights Management
                    $currentRights = $role->getRights();
                    $rights = \SPPMod\SPPAuth\SPPRight::find_all();
                    echo "\nRights (Current: " . implode(',', $currentRights) . "):\n";
                    foreach ($rights as $i => $rt) {
                        $indicator = in_array($rt->id, $currentRights) ? " [*]" : " [ ]";
                        echo "  [" . ($i+1) . "]{$indicator} " . $rt->name . "\n";
                    }
                    $selected = prompt("Update Rights (comma-separated indices, or Enter to keep)");
                    $rightIds = $currentRights;
                    if ($selected !== "") {
                        $rightIds = [];
                        $indices = explode(',', $selected);
                        foreach ($indices as $idx) {
                            $idx = (int)trim($idx) - 1;
                            if (isset($rights[$idx])) $rightIds[] = $rights[$idx]->id;
                        }
                    }
        
                    \SPPMod\SPPAuth\SPPRole::saveRoleInfo([
                        'id' => $role->id,
                        'name' => $name,
                        'description' => $desc,
                        'right_ids' => $rightIds
                    ]);
                    echo "\nSuccess: Role updated.\n";
                } catch (\Exception $e) {
                    echo "Error: " . $e->getMessage() . "\n";
                }
    }

    public function getName(): string
    {
        return 'auth:role:edit';
    }

    public function getDescription(): string
    {
        return 'Legacy port of auth:role:edit';
    }
}
