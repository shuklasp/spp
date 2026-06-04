<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class MakeJavaCommand
 * Scaffolds a new Java service for SPP.
 */
class MakeJavaCommand extends BaseMakeCommand
{
    protected string $name = 'make:java-service';
    protected string $description = 'Create a new Java service script';

    public function execute(array $args): void
    {
        $name = $args[2] ?? null;
        if (!$name) {
            echo "Usage: spp make:java-service <name> [--app=context]\n";
            return;
        }

        $app = $this->getContext($args);
        $className = ucfirst($name);
        $targetDir = $this->getTargetDir('services/java', $app);
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $targetPath = "{$targetDir}/Service" . $className . ".java";

        $success = $this->buildFromStub('java_service', $targetPath, [
            'CLASS_NAME' => "Service" . $className
        ]);

        if ($success) {
            echo "Success: Java service {$className} created at {$targetPath}\n";
        }
    }
}
