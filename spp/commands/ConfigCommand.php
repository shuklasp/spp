<?php
namespace SPP\CLI\Commands;

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

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $action = $this->getArgument($args, 0) ?? 'list';
        
        switch ($action) {
            case 'get':
                $key = $this->getArgument($args, 1) ?? null;
                if (!$key) {
                    echo "Error: Key required. Usage: spp config get <key>\n";
                    return;
                }
                $val = SPPConfig::get($key);
                echo "{$key}: " . (is_scalar($val) ? $val : json_encode($val, JSON_PRETTY_PRINT)) . "\n";
                break;

            case 'set':
                $key = $this->getArgument($args, 1) ?? null;
                $val = $this->getArgument($args, 2) ?? null;
                if (!$key || $val === null) {
                    echo "Error: Key and value required. Usage: spp config set <key> <value>\n";
                    return;
                }
                SPPConfig::set($key, $val);
                echo "Success: Set {$key} to {$val}\n";
                break;

            case 'cache':
                $appname = $this->getArgument($args, 1) ?? \SPP\Scheduler::getContext() ?: 'default';
                SPPConfig::compile($appname);
                echo "Success: Configuration cached for app '{$appname}'\n";
                break;

            case 'clear':
                $appname = $this->getArgument($args, 1) ?? \SPP\Scheduler::getContext() ?: 'default';
                SPPConfig::clearCompiled($appname);
                echo "Success: Configuration cache cleared for app '{$appname}'\n";
                break;

            case 'delete':
                $key = $this->getArgument($args, 1) ?? null;
                if (!$key) {
                    echo "Error: Key required. Usage: spp config delete <key>\n";
                    return;
                }
                SPPConfig::delete($key);
                echo "Success: Deleted {$key}\n";
                break;

            case 'list':
                $appname = $this->getArgument($args, 1) ?? \SPP\Scheduler::getContext() ?: 'default';
                $all = SPPConfig::getAll($appname);
                $rows = [];
                foreach ($all as $k => $v) {
                    $rows[] = [
                        'Key' => $k,
                        'Value' => is_scalar($v) ? (string)$v : json_encode($v)
                    ];
                }
                \SPP\CLI\Console::printTable(['Key', 'Value'], $rows);
                break;

            default:
                echo "Usage: spp config [get|set|delete|list|cache|clear] [key] [value]\n";
                break;
        }
    }
}
