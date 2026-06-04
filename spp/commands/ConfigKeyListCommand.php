<?php
namespace SPP\CLI\Commands;

use SPP\SPPConfig;

class ConfigKeyListCommand extends \SPP\CLI\Command
{
    public function getName(): string
    {
        return 'config:key:list';
    }

    public function getDescription(): string
    {
        return 'List all framework configuration keys';
    }

    public function execute(array $args): void
    {
        $appname = \SPP\Scheduler::getContext() ?: 'default';
        $all = SPPConfig::getAll($appname);
        $rows = [];
        foreach ($all as $k => $v) {
            $rows[] = [
                'Key' => $k,
                'Value' => is_scalar($v) ? (string)$v : json_encode($v)
            ];
        }
        printTable(['Key', 'Value'], $rows);
    }
}
