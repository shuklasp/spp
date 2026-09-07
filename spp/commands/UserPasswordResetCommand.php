<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class UserPasswordResetCommand extends Command
{
    protected string $name = 'user:password:reset';
    protected string $description = 'Reset password for a user';

    public function execute(array $args): void
    {
        $target = $this->getArgument($args, 0);
        if (!$target) {
            $target = $this->prompt("Username or ID");
        }
        if (!$target) {
            $this->error("Target username or ID is required.");
            if ($this->hasFlag($args, 'json')) {
                $this->json(['success' => false, 'error' => 'Target is required.'], $args);
            }
            return;
        }

        $password = $this->getOption($args, 'password');
        if (!$password && !$this->hasFlag($args, 'json')) {
            $password = $this->prompt("New Password");
        }
        if (!$password) {
            $password = bin2hex(random_bytes(6));
            if (!$this->hasFlag($args, 'json')) {
                $this->info("Generated new password: {$password}");
            }
        }

        $db = new \SPPMod\SPPDB\SPPDB();
        $userTable = \SPPMod\SPPDB\SPPDB::sppTable('users');
        $existing = is_numeric($target)
            ? $db->execute_query("SELECT id, username FROM {$userTable} WHERE id = ?", [(int)$target])
            : $db->execute_query("SELECT id, username FROM {$userTable} WHERE username = ?", [$target]);

        if (empty($existing)) {
            $msg = "User '{$target}' not found.";
            $this->error($msg);
            if ($this->hasFlag($args, 'json')) {
                $this->json(['success' => false, 'error' => $msg], $args);
            }
            return;
        }

        $userId = $existing[0]['id'];
        $username = $existing[0]['username'];
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $db->execute_query("UPDATE {$userTable} SET password_hash = ? WHERE id = ?", [$hash, $userId]);

        $msg = "Password for user '{$username}' reset successfully.";
        $this->info($msg);

        if ($this->hasFlag($args, 'json')) {
            $this->json(['success' => true, 'message' => $msg, 'username' => $username, 'new_password' => $password], $args);
        }
    }
}
