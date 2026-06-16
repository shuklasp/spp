<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ViewServiceAddCommand extends Command
{
    protected string $name = 'view:service:add';
    protected string $description = 'Register a new AJAX service endpoint';

    public function execute(array $args): void
    {
        $appname = 'default';
        $name = null;
        $script = null;
        $method = 'POST';
        $source = 'yaml';

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) $appname = substr($arg, 6);
            elseif (str_starts_with($arg, '--name=')) $name = substr($arg, 7);
            elseif (str_starts_with($arg, '--script=')) $script = substr($arg, 9);
            elseif (str_starts_with($arg, '--method=')) $method = substr($arg, 9);
            elseif (str_starts_with($arg, '--source=')) $source = substr($arg, 9);
        }

        if (!$name || !$script) {
            echo "Usage: php spp.php view:service:add --name=<service> --script=<path> [--method=POST] [--app=default] [--source=yaml|db]\n";
            return;
        }

        \SPP\Scheduler::withContext($appname, function() use ($name, $script, $method, $source) {
            \SPPMod\SppApi\SPPAjax::registerService($name, $script, $method, $source);
        });

        echo "Success: AJAX Service '{$name}' -> '{$script}' [{$method}] registered for app '{$appname}' (Source: {$source}).\n";
    }
}
