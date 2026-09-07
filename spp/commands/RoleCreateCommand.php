<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class RoleCreateCommand extends Command
{
    protected string $name = 'role:create';
    protected string $description = 'Create a new role';

    public function execute(array $args): void
    {
        $name = $this->getArgument($args, 0);
        if (!$name) {
            $name = $this->prompt("Role Name");
        }
        if (!$name) {
            $this->error("Role name is required.");
            if ($this->hasFlag($args, 'json')) {
                $this->json(['success' => false, 'error' => 'Role name is required.'], $args);
            }
            return;
        }

        $description = $this->getOption($args, 'description', '');

        $db = new \SPPMod\SPPDB\SPPDB();
        $rolesTable = \SPPMod\SPPDB\SPPDB::sppTable('roles');
        $existing = $db->execute_query("SELECT id FROM {$rolesTable} WHERE name = ?", [$name]);
        if (!empty($existing)) {
            $msg = "Role '{$name}' already exists.";
            $this->error($msg);
            if ($this->hasFlag($args, 'json')) {
                $this->json(['success' => false, 'error' => $msg], $args);
            }
            return;
        }

        $nextId = (int)($db->execute_query("SELECT COALESCE(MAX(COALESCE(id, rowid)), 0) + 1 as next_id FROM {$rolesTable}")[0]['next_id'] ?? 1);
        $db->execute_query("INSERT INTO {$rolesTable} (id, name, description) VALUES (?, ?, ?)", [$nextId, $name, $description]);
        $roleId = $nextId;

        $msg = "Role '{$name}' (ID: {$roleId}) created successfully.";
        $this->info($msg);

        if ($this->hasFlag($args, 'json')) {
            $this->json(['success' => true, 'message' => $msg, 'role_id' => $roleId, 'name' => $name], $args);
        }
    }
}
