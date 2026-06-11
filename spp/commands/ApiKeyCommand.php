<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ApiKeyCommand extends Command
{
    protected string $name = 'api:key-generate';
    protected string $description = 'Generates a new permanent API Key.';

    public function execute(array $args): void
    {
        $name = $args[2] ?? null;
        if (empty($name)) {
            $this->error("API Key name is required. Usage: php spp.php api:key-generate \"Name\"");
            return;
        }

        $token = bin2hex(random_bytes(32));

        $db = new \SPPMod\SPPDB\SPPDB();
        if (!$db->tableExists('api_keys')) {
            $this->error("api_keys table does not exist. Did you run the migration?");
            return;
        }

        $id = uniqid();
        
        $db->execute_query(
            "INSERT INTO api_keys (id, name, token, status, created_at) VALUES (?, ?, ?, 1, NOW())",
            [$id, $name, $token]
        );

        echo "API Key successfully generated for '{$name}'.\n";
        echo "Token: " . $token . "\n";
        echo "Please store this token safely. It will not be shown again in plain text.\n";
    }
}
