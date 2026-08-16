<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class MakeDotNetCommand
 * Scaffolds a new .NET service for SPP.
 */
class MakeDotNetCommand extends BaseMakeCommand
{
    protected string $name = 'make:dotnet-service';
    protected string $description = 'Create a new .NET service project';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $name = $this->getArgument($args, 0) ?? null;
        if (!$name) {
            echo "Usage: spp make:dotnet-service <name> [--app=context]\n";
            return;
        }

        $app = $this->getContext($args);
        $className = ucfirst($name);
        $targetDir = $this->getTargetDir('services/dotnet', $app);
        
        $projectDir = str_replace('\\', '/', $targetDir) . "/service." . strtolower($name);
        
        if (is_dir($projectDir)) {
            echo "Error: Directory {$projectDir} already exists.\n";
            return;
        }

        // 1. Scaffold console project
        // Note: dotnet restricts project names, but we escape it anyway to prevent injection
        $safeClassName = escapeshellarg("Service.{$className}");
        $safeProjectDir = escapeshellarg($projectDir);
        $cmd1 = "dotnet new console -n {$safeClassName} -o {$safeProjectDir}";
        echo shell_exec($cmd1 . " 2>&1");

        // 2. Add reference to SppClient
        $sppClientPath = str_replace('\\', '/', realpath(SPP_BASE_DIR . '/lib/dotnet/SppClient/SppClient.csproj'));
        $safeSppClientPath = escapeshellarg($sppClientPath);
        $cmd2 = "dotnet add {$safeProjectDir} reference {$safeSppClientPath}";
        echo shell_exec($cmd2 . " 2>&1");

        // 3. Overwrite Program.cs with our stub
        $targetPath = "{$projectDir}/Program.cs";
        if (file_exists($targetPath)) {
            unlink($targetPath);
        }
        $success = $this->buildFromStub('dotnet_service', $targetPath, [
            'CLASS_NAME' => $className
        ]);

        if ($success) {
            echo "Success: .NET service {$className} created at {$projectDir}\n";
        }
    }
}
