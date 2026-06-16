<?php
namespace SPPMod\SPPAuth\Commands;

use SPP\CLI\Command;
use SPPMod\SPPDB\SPPDB;

class ABACPolicyCommand extends Command
{
    protected string $name = 'iam:abac';
    protected string $description = 'Manage Attribute-Based Access Control (ABAC) policies';

    public function execute(array $args): void
    {
        $action = $args['action'] ?? 'list';
        $db = new SPPDB();
        $table = SPPDB::sppTable('abac_policies');

        if ($action === 'list') {
            $policies = $db->execute_query("SELECT id, permission, condition_logic, status FROM $table ORDER BY id DESC");
            if (empty($policies)) {
                echo "No ABAC policies found.\n";
            } else {
                if (function_exists('printTable')) {
                    printTable(['ID', 'Permission', 'Condition', 'Status'], $policies);
                } else {
                    foreach ($policies as $p) {
                        echo "- [{$p['id']}] {$p['permission']} ({$p['status']}) => {$p['condition_logic']}\n";
                    }
                }
            }
        } elseif ($action === 'create') {
            $permission = $args['param1'] ?? prompt('Permission (e.g. read:secure_data)');
            $logic = $args['param2'] ?? prompt('Condition Logic (e.g. user.department == "IT")');
            if (!$permission || !$logic) {
                echo "Permission and Logic are required.\n";
                return;
            }
            $db->execute_query("INSERT INTO $table (permission, condition_logic, status) VALUES (?, ?, 'active')", [$permission, $logic]);
            echo "Policy created.\n";
        } elseif ($action === 'delete') {
            $id = $args['param1'] ?? null;
            if (!$id) {
                echo "Policy ID required.\n";
                return;
            }
            $db->execute_query("DELETE FROM $table WHERE id = ?", [$id]);
            echo "Policy deleted.\n";
        } else {
            echo "Unknown action. Use 'list', 'create', or 'delete'.\n";
        }
    }
}
