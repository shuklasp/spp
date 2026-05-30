<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AuthUserEditCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $uid = $argv[2] ?? null;
                if (!$uid) die("Usage: php spp.php auth:user:edit <userid>\n");
                require_once SPP_APP_DIR . '/spp/sppinit.php';
                
                try {
                    $user = new \SPPMod\SPPAuth\SPPUser($uid);
                    echo "Editing User: {$user->username} (ID: {$user->id})\n";
                    $uname = prompt("Username", $user->username);
                    $email = prompt("Email", $user->email);
                    $pass = prompt("New Password (leave empty to keep current)");
                    $status = prompt("Status", $user->status);
        
                    // Fetch Current Roles
                    $currentRoles = $user->getRoles();
                    $roles = \SPPMod\SPPAuth\SPPRole::find_all();
                    echo "\nRoles (Current: " . implode(',', $currentRoles) . "):\n";
                    foreach ($roles as $i => $r) {
                        $indicator = in_array($r->id, $currentRoles) ? " [*]" : " [ ]";
                        echo "  [" . ($i+1) . "]{$indicator} " . $r->role_name . "\n";
                    }
                    $selected = prompt("Update Roles (comma-separated indices, or Enter to keep)", implode(',', array_keys($currentRoles)));
                    $roleIds = $currentRoles;
                    if ($selected !== implode(',', array_keys($currentRoles))) {
                        $roleIds = [];
                        $indices = explode(',', $selected);
                        foreach ($indices as $idx) {
                            $idx = (int)trim($idx) - 1;
                            if (isset($roles[$idx])) $roleIds[] = $roles[$idx]->id;
                        }
                    }
        
                    \SPPMod\SPPAuth\SPPUser::saveUserInfo([
                        'id' => $user->id,
                        'username' => $uname,
                        'email' => $email,
                        'password' => $pass,
                        'status' => $status,
                        'role_ids' => $roleIds
                    ]);
                    echo "\nSuccess: User updated.\n";
                } catch (\Exception $e) {
                    echo "Error: " . $e->getMessage() . "\n";
                }
    }

    public function getName(): string
    {
        return 'auth:user:edit';
    }

    public function getDescription(): string
    {
        return 'Legacy port of auth:user:edit';
    }
}
