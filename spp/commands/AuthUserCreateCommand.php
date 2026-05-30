<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AuthUserCreateCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        require_once SPP_APP_DIR . '/spp/sppinit.php';
                $uname = prompt("Username");
                if (!$uname) die("Username required.\n");
                $email = prompt("Email");
                $pass = prompt("Password");
                
                try {
                    $id = \SPPMod\SPPAuth\SPPUser::saveUserInfo([
                        'username' => $uname,
                        'email' => $email,
                        'password' => $pass,
                        'status' => 'active',
                        'role_ids' => $roleIds
                    ]);
                    echo "\nSuccess: User '{$uname}' created with ID {$id}.\n";
                } catch (\Exception $e) {
                    echo "Error: " . $e->getMessage() . "\n";
                }
    }

    public function getName(): string
    {
        return 'auth:user:create';
    }

    public function getDescription(): string
    {
        return 'Legacy port of auth:user:create';
    }
}
