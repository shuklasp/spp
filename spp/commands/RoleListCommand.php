<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class RoleListCommand extends Command
{
    protected string $name = 'role:list';
    protected string $description = 'List all defined roles and permissions';

    public function execute(array $args): void
    {
        $roles = [];
        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            $rolesTable = \SPPMod\SPPDB\SPPDB::sppTable('roles');
            $roles = $db->execute_query("SELECT COALESCE(id, rowid) as id, name, description FROM {$rolesTable} ORDER BY id ASC") ?? [];
        } catch (\Throwable $e) {
            $roles = [];
        }

        if ($this->hasFlag($args, 'json')) {
            $this->json(['success' => true, 'roles' => $roles], $args);
            return;
        }

        $this->line("\n=== Defined Roles ===");
        if (empty($roles)) {
            $this->info("No roles found.");
            return;
        }

        printf("%-6s | %-20s | %-40s\n", "ID", "Name", "Description");
        $this->line(str_repeat("-", 70));
        foreach ($roles as $r) {
            printf("%-6s | %-20s | %-40s\n", $r['id'], $r['name'], $r['description'] ?? '');
        }
        $this->line("");
    }
}
