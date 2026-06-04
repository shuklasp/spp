<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class MakeNodeCommand
 * Scaffolds a new Node.js service for SPP.
 */
class MakeNodeCommand extends BaseMakeCommand
{
    protected string $name = 'make:node-service';
    protected string $description = 'Create a new Node.js service script';

    public function execute(array $args): void
    {
        $name = $args[2] ?? null;
        if (!$name) {
            echo "Usage: spp make:node-service <name> [--app=context]\n";
            return;
        }

        $app = $this->getContext($args);
        $className = ucfirst($name);
        $targetDir = $this->getTargetDir('services/node', $app);
        $targetPath = "{$targetDir}/service." . strtolower($name) . ".js";

        $success = $this->buildFromStub('node_service', $targetPath, [
            'CLASS_NAME' => $className
        ]);

        if ($success) {
            echo "Success: Node.js service {$className} created at {$targetPath}\n";
        }
    }
}
