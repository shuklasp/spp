<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AuthRoleCreateCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        require_once SPP_APP_DIR . '/spp/sppinit.php';
                $name = prompt("Role Name");
                if (!$name) die("Role name required.\n");
                $desc = prompt("Description");
        
                // Select Rights
                $rightsData = \SPPMod\SPPAuth\SPPRight::find_all();
                echo "\nAvailable Rights:\n";
                foreach ($rightsData as $i => $rt) {
                    echo "  [" . ($i+1) . "] " . $rt->name . "\n";
                }
                $selected = prompt("Select Rights (comma-separated indices)", "");
                $rightIds = [];
                if ($selected) {
                    $indices = explode(',', $selected);
                    foreach ($indices as $idx) {
                        $idx = (int)trim($idx) - 1;
                        if (isset($rightsData[$idx])) $rightIds[] = $rightsData[$idx]->id;
                    }
                }
        
                try {
                    $id = \SPPMod\SPPAuth\SPPRole::saveRoleInfo([
                        'name' => $name,
                        'description' => $desc,
                        'right_ids' => $rightIds
                    ]);
                    echo "\nSuccess: Role '{$name}' created with ID {$id}.\n";
                } catch (\Exception $e) {
                    echo "Error: " . $e->getMessage() . "\n";
                }
    }

    public function getName(): string
    {
        return 'auth:role:create';
    }

    public function getDescription(): string
    {
        return 'Legacy port of auth:role:create';
    }
}
