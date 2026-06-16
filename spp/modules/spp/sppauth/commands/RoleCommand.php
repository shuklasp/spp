<?php
namespace SPPMod\SPPAuth\Commands;

use SPP\CLI\Command;
use SPPMod\SPPDB\SPPDB;

class RoleCommand extends Command
{
    protected string $name = 'iam:roles';
    protected string $description = 'List all Roles and Entity Role Assignments';

    public function execute(array $args): void
    {
        $action = $args['action'] ?? 'list';
        $db = new SPPDB();
        $rolesTable = SPPDB::sppTable('roles');
        $erTable = SPPDB::sppTable('entity_roles');

        if ($action === 'list') {
            $roles = $db->execute_query("SELECT id, role_name, description FROM $rolesTable ORDER BY id ASC");
            if (empty($roles)) {
                echo "No roles found.\n";
            } else {
                echo "--- System Roles ---\n";
                if (function_exists('printTable')) {
                    printTable(['ID', 'Role Name', 'Description'], $roles);
                }
            }

            echo "\n--- Entity Assignments ---\n";
            $sql = "SELECT er.target_class, er.target_id, r.role_name FROM $erTable er JOIN $rolesTable r ON er.role_id = r.id";
            $assignments = $db->execute_query($sql);
            if (empty($assignments)) {
                echo "No active assignments.\n";
            } else {
                if (function_exists('printTable')) {
                    printTable(['Target Class', 'Target ID', 'Assigned Role'], $assignments);
                }
            }
        } else {
            echo "Usage: php spp.php iam:roles list\n";
        }
    }
}
