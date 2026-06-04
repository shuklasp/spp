<?php
namespace SPP\CLI\Commands;
use SPP\CLI\Command;
class ApiKeyRevokeCommand extends Command {
    protected string $name = 'api:key:revoke';
    protected string $description = 'Revoke an existing API token';
    public function execute(array $args): void {
        $token = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--token=')) $token = substr($arg, 8);
        }
        if (!$token) {
            echo "Usage: php spp.php api:key:revoke --token=<token>\n";
            return;
        }
        echo "Revoking token: {$token}...\n";
        echo "Success (Stub).\n";
    }
}
