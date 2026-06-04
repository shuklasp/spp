<?php
namespace SPP\CLI\Commands;

use SPP\SPPConfig;

class ConfigKeyDeleteCommand extends \SPP\CLI\Command
{
    public function getName(): string
    {
        return 'config:key:delete';
    }

    public function getDescription(): string
    {
        return 'Delete a configuration key';
    }

    public function execute(array $args): void
    {
        $key = $args[2] ?? null;
        if (!$key) {
            echo "\033[31m[ERROR]\033[0m Key required. Usage: spp config:key:delete <key>\n";
            return;
        }
        SPPConfig::delete($key);
        echo "\033[32m[SUCCESS]\033[0m Deleted {$key}\n";
    }
}
