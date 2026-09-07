<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class UserDeleteCommand extends Command
{
    protected string $name = 'user:delete';
    protected string $description = 'Delete a user account by username or ID';

    public function execute(array $args): void
    {
        $target = $this->getArgument($args, 0);
        if (!$target) {
            $target = $this->prompt("Username or ID to delete");
        }
        if (!$target) {
            $this->error("Target username or ID is required.");
            if ($this->hasFlag($args, 'json')) {
                $this->json(['success' => false, 'error' => 'Target is required.'], $args);
            }
            return;
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

        // Clean up role assignments if table exists
        try {
            $urTable = \SPPMod\SPPDB\SPPDB::sppTable('userroles');
            $db->execute_query("DELETE FROM {$urTable} WHERE userid = ? OR username = ?", [$userId, $username]);
        } catch (\Throwable $e) {
            // Table may not exist or different schema
        }

        // Delete user
        if ($userId !== null) {
            $db->execute_query("DELETE FROM {$userTable} WHERE id = ?", [$userId]);
        } else {
            $db->execute_query("DELETE FROM {$userTable} WHERE username = ?", [$username]);
        }

        $msg = "User '{$username}' deleted successfully.";
        $this->info($msg);

        if ($this->hasFlag($args, 'json')) {
            $this->json(['success' => true, 'message' => $msg], $args);
        }
    }
}
