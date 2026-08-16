<?php
namespace SPPMod\SPPAuth\Commands;

use SPP\CLI\Command;
use SPPMod\SPPDB\SPPDB;

class RoleCommand extends Command
{
    protected string $name = 'iam:roles';
    protected string $description = 'List all Roles and Entity Role Assignments';

    public function renderAdminUI(): string
    {
        $html = '<div class="command-ui-container">';
        $html .= '  <h3>Manage System Roles</h3>';
        $html .= '  <div class="tabs-toolbar" style="margin-bottom: 20px; display: flex; gap: 10px; border-bottom: 1px solid var(--glass-border);">';
        $html .= '      <button class="tab-btn" onclick="executeCommand(\'iam:roles\', \'--action=list\')">List Roles & Assignments</button>';
        $html .= '  </div>';
        $html .= '</div>';
        return $html;
    }

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $action = $args['action'] ?? 'list';
        $isJson = isset($args['json']) || in_array('--json', $args, true);

        $db = new SPPDB();
        $rolesTable = SPPDB::sppTable('roles');
        $erTable = SPPDB::sppTable('entity_roles');

        if ($action === 'list') {
            $roles = $db->execute_query("SELECT id, role_name, description FROM $rolesTable ORDER BY id ASC");
            $sql = "SELECT er.target_class, er.target_id, r.role_name FROM $erTable er JOIN $rolesTable r ON er.role_id = r.id";
            $assignments = $db->execute_query($sql);

            if ($isJson) {
                echo json_encode([
                    'roles' => $roles ?? [],
                    'assignments' => $assignments ?? []
                ]);
                return;
            }

            if (empty($roles)) {
                echo "No roles found.\n";
            } else {
                echo "--- System Roles ---\n";
                if (function_exists('printTable')) {
                    printTable(['ID', 'Role Name', 'Description'], $roles);
                }
            }

            echo "\n--- Entity Assignments ---\n";
            
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
