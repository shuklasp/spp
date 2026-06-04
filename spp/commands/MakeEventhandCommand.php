<?php

namespace SPP\CLI\Commands;

/**
 * Class MakeEventhandCommand
 * Scaffolds a new Event Handler class.
 */
class MakeEventhandCommand extends BaseMakeCommand
{
    protected string $name = 'make:eventhand';
    protected string $description = 'Create a new Event Handler class';

    public function execute(array $args): void
    {
        $name = $args[2] ?? null;
        if (!$name) {
            echo "Usage: php spp.php make:eventhand <HandlerClassName> [--app=appname]\n";
            return;
        }

        $app = $this->getContext($args);
        $className = ucfirst($name);

        $namespace = $this->getNamespace('Events', $app);
        if ($app === 'default') {
            $namespace = "EventHandlers";
        }

        $targetDir = $this->getTargetDir('events', $app);
        $targetPath = "{$targetDir}/{$className}.php";

        $success = $this->buildFromStub('eventhandler', $targetPath, [
            'namespace' => $namespace,
            'className' => $className
        ]);

        if ($success) {
            echo "Success: Event Handler {$className} created at {$targetPath}\n";
            
            // Auto clear cache
            $sppBin = escapeshellarg(dirname(SPP_BASE_DIR) . '/spp.php');
            shell_exec("php {$sppBin} cache:clear");
            echo "Framework cache cleared.\n";
        }
    }
}
