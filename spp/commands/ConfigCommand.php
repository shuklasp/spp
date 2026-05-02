<?php
namespace SPP\Commands;

use SPP\SPPConfig;

class ConfigCommand extends \SPP\CLI\Command
{
    public function getName(): string
    {
        return 'config';
    }

    public function getDescription(): string
    {
        return 'Manage framework and application configuration';
    }

    public function execute(array $args): void
    {
        $action = $args[0] ?? 'list';
        
        switch ($action) {
            case 'get':
                $key = $args[1] ?? null;
                if (!$key) {
                    echo "Error: Key required. Usage: spp config get <key>\n";
                    return;
                }
                $val = SPPConfig::get($key);
                echo "{$key}: " . (is_scalar($val) ? $val : json_encode($val, JSON_PRETTY_PRINT)) . "\n";
                break;

            case 'set':
                $key = $args[1] ?? null;
                $val = $args[2] ?? null;
                if (!$key || $val === null) {
                    echo "Error: Key and value required. Usage: spp config set <key> <value>\n";
                    return;
                }
                SPPConfig::set($key, $val);
                echo "Success: Set {$key} to {$val}\n";
                break;

            case 'cache':
                $appname = $args[1] ?? \SPP\Scheduler::getContext() ?: 'default';
                SPPConfig::compile($appname);
                echo "Success: Configuration cached for app '{$appname}'\n";
                break;

            case 'clear':
                $appname = $args[1] ?? \SPP\Scheduler::getContext() ?: 'default';
                SPPConfig::clearCompiled($appname);
                echo "Success: Configuration cache cleared for app '{$appname}'\n";
                break;

            default:
                echo "Usage: spp config [get|set|cache|clear] [key] [value]\n";
                break;
        }
    }
}
