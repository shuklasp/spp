<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class UserCreateCommand extends Command
{
    protected string $name = 'user:create';
    protected string $description = 'Create a new user account';

    public function execute(array $args): void
    {
        $username = $this->getArgument($args, 0);
        if (!$username) {
            $username = $this->prompt("Username");
        }
        if (!$username) {
            $this->error("Username is required.");
            if ($this->hasFlag($args, 'json')) {
                $this->json(['success' => false, 'error' => 'Username is required.'], $args);
            }
            return;
        }

        $email = $this->getOption($args, 'email', '');
        $password = $this->getOption($args, 'password');
        if (!$password && !$this->hasFlag($args, 'json')) {
            $password = $this->prompt("Password");
        }
        if (!$password) {
            $password = bin2hex(random_bytes(6));
            if (!$this->hasFlag($args, 'json')) {
                $this->info("Generated random password: {$password}");
            }
        }

        $roleName = $this->getOption($args, 'role');
        $enabled = $this->getOption($args, 'enabled', 'Y');

        $db = new \SPPMod\SPPDB\SPPDB();
        $userTable = \SPPMod\SPPDB\SPPDB::sppTable('users');
        $existing = $db->execute_query("SELECT id FROM {$userTable} WHERE username = ?", [$username]);
        if (!empty($existing)) {
            $msg = "User '{$username}' already exists.";
            $this->error($msg);
            if ($this->hasFlag($args, 'json')) {
                $this->json(['success' => false, 'error' => $msg], $args);
            }
            return;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $nextId = (int)($db->execute_query("SELECT COALESCE(MAX(COALESCE(id, rowid)), 0) + 1 as next_id FROM {$userTable}")[0]['next_id'] ?? 1);
        $db->execute_query(
            "INSERT INTO {$userTable} (id, username, password_hash, email, enabled) VALUES (?, ?, ?, ?, ?)",
            [$nextId, $username, $hash, $email, $enabled]
        );
        $userId = $nextId;

        if ($roleName) {
            $rolesTable = \SPPMod\SPPDB\SPPDB::sppTable('roles');
            $roleRow = $db->execute_query("SELECT id FROM {$rolesTable} WHERE name = ?", [$roleName]);
            if (!empty($roleRow)) {
                $roleId = $roleRow[0]['id'];
                try {
                    $userRolesTable = \SPPMod\SPPDB\SPPDB::sppTable('userroles');
                    $db->execute_query(
                        "INSERT INTO {$userRolesTable} (userid, roleid) VALUES (?, ?)",
                        [$userId, $roleId]
                    );
                } catch (\Throwable $e) {
                    // Fallback
                }
            }
        }

        $msg = "User '{$username}' (ID: {$userId}) created successfully.";
        $this->info($msg);

        if ($this->hasFlag($args, 'json')) {
            $this->json(['success' => true, 'message' => $msg, 'user_id' => $userId, 'username' => $username, 'generated_password' => $password], $args);
        }
    }
}
