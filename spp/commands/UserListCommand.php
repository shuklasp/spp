<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class UserListCommand extends Command
{
    protected string $name = 'user:list';
    protected string $description = 'List all registered framework users';

    public function execute(array $args): void
    {
        $rawUsers = [];
        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            $userTable = \SPPMod\SPPDB\SPPDB::sppTable('users');
            $rawUsers = $db->execute_query("SELECT COALESCE(id, rowid) as id, username, email, enabled FROM {$userTable} ORDER BY id ASC") ?? [];
        } catch (\Throwable $e) {
            $rawUsers = [];
        }

        $users = [];
        foreach ($rawUsers as $u) {
            $users[] = [
                'id' => $u['id'],
                'username' => $u['username'],
                'email' => $u['email'] ?? '',
                'status' => $u['status'] ?? ($u['enabled'] === 'Y' ? 'active' : ($u['enabled'] ?? 'active'))
            ];
        }

        if ($this->hasFlag($args, 'json')) {
            $this->json(['success' => true, 'users' => $users], $args);
            return;
        }

        $this->line("\n=== Registered Users ===");
        if (empty($users)) {
            $this->info("No users found.");
            return;
        }

        printf("%-6s | %-20s | %-30s | %-10s\n", "ID", "Username", "Email", "Status");
        $this->line(str_repeat("-", 75));
        foreach ($users as $u) {
            printf("%-6s | %-20s | %-30s | %-10s\n", $u['id'], $u['username'], $u['email'] ?: 'N/A', $u['status']);
        }
        $this->line("");
    }
}
