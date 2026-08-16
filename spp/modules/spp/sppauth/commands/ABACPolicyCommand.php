<?php
namespace SPPMod\SPPAuth\Commands;

use SPP\CLI\Command;
use SPPMod\SPPDB\SPPDB;

class ABACPolicyCommand extends Command
{
    protected string $name = 'iam:abac';
    protected string $description = 'Manage Attribute-Based Access Control (ABAC) policies';

    public function renderAdminUI(): string
    {
        $html = '<div class="command-ui-container">';
        $html .= '  <h3>Manage ABAC Policies</h3>';
        $html .= '  <div class="tabs-toolbar" style="margin-bottom: 20px; display: flex; gap: 10px; border-bottom: 1px solid var(--glass-border);">';
        $html .= '      <button class="tab-btn" onclick="executeCommand(\'iam:abac\', \'--action=list\')">List Policies</button>';
        $html .= '  </div>';
        $html .= '  <h4>Create New Policy</h4>';
        $html .= '  <div class="form-group" style="margin-bottom: 15px;">';
        $html .= '    <label style="display:block; margin-bottom:5px;">Permission</label>';
        $html .= '    <input type="text" id="abacPerm" class="spp-input" placeholder="e.g. read:secure_data" style="width:100%; background:var(--bg-color-alt); color:var(--text); border:1px solid var(--border-color); padding: 8px; border-radius: 4px;">';
        $html .= '  </div>';
        $html .= '  <div class="form-group" style="margin-bottom: 15px;">';
        $html .= '    <label style="display:block; margin-bottom:5px;">Condition Logic</label>';
        $html .= '    <input type="text" id="abacLogic" class="spp-input" placeholder="e.g. user.department == \'IT\'" style="width:100%; background:var(--bg-color-alt); color:var(--text); border:1px solid var(--border-color); padding: 8px; border-radius: 4px;">';
        $html .= '  </div>';
        $html .= '  <button class="spp-btn primary-btn" onclick="let p = document.getElementById(\'abacPerm\').value; let l = document.getElementById(\'abacLogic\').value; if(p && l) executeCommand(\'iam:abac\', \'--action=create --param1=\"\' + p + \'\" --param2=\"\' + l + \'\"\');">Create Policy</button>';
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
        $table = SPPDB::sppTable('abac_policies');

        if ($action === 'list') {
            $policies = $db->execute_query("SELECT id, permission, condition_logic, status FROM $table ORDER BY id DESC");
            
            if ($isJson) {
                echo json_encode(['sources' => [['items' => $policies ?? []]]]);
                return;
            }

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
            $permission = $args['param1'] ?? null;
            $logic = $args['param2'] ?? null;
            if (!$permission || !$logic) {
                echo "Permission and Logic are required.\n";
                echo "Usage: php spp.php iam:abac --action=create --param1=\"<permission>\" --param2=\"<logic>\"\n";
                return;
            }
            $db->execute_query("INSERT INTO $table (permission, condition_logic, status) VALUES (?, ?, 'active')", [$permission, $logic]);
            echo "Policy created successfully.\n";
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
