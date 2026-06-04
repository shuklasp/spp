<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class MakePerlCommand
 * Scaffolds a new Perl service for SPP.
 */
class MakePerlCommand extends BaseMakeCommand
{
    protected string $name = 'make:perl-service';
    protected string $description = 'Create a new Perl service script';

    public function execute(array $args): void
    {
        $name = $args[2] ?? null;
        if (!$name) {
            echo "Usage: spp make:perl-service <name> [--app=context]\n";
            return;
        }

        $app = $this->getContext($args);
        $className = ucfirst($name);
        $targetDir = $this->getTargetDir('services/perl', $app);
        $targetPath = "{$targetDir}/service." . strtolower($name) . ".pl";

        $success = $this->buildFromStub('perl_service', $targetPath, [
            'CLASS_NAME' => $className
        ]);

        if ($success) {
            echo "Success: Perl service {$className} created at {$targetPath}\n";
        }
    }
}
