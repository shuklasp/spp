<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class MakePolyglotCommand
 * Wrapper command to scaffold any polyglot service.
 */
class MakePolyglotCommand extends Command
{
    protected string $name = 'make:polyglot';
    protected string $description = 'Scaffold a new polyglot service (e.g. php spp.php make:polyglot python MyService)';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $language = $this->getArgument($args, 0);
        $serviceName = $this->getArgument($args, 1);

        if (!$language || !$serviceName) {
            echo "Usage: spp make:polyglot <language> <service_name> [--app=context]\n";
            echo "Supported languages: python, node, go, java, cpp, dotnet, perl\n";
            return;
        }

        $language = strtolower($language);
        $commandMap = [
            'python' => MakePythonCommand::class,
            'node' => MakeNodeCommand::class,
            'go' => MakeGoCommand::class,
            'java' => MakeJavaCommand::class,
            'cpp' => MakeCppCommand::class,
            'dotnet' => MakeDotNetCommand::class,
            'cs' => MakeDotNetCommand::class,
            'perl' => MakePerlCommand::class,
        ];

        if (!isset($commandMap[$language])) {
            echo "Error: Unsupported language '{$language}'.\n";
            echo "Supported languages: " . implode(', ', array_keys($commandMap)) . "\n";
            return;
        }

        $className = $commandMap[$language];
        
        // Pass the remaining args to the specific command
        $newArgs = [
            'spp.php',
            'make:' . $language,
            $serviceName
        ];
        
        // Append any flags like --app
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $newArgs[] = $arg;
            }
        }

        if (class_exists($className)) {
            $cmd = new $className();
            $cmd->execute($newArgs);
        } else {
            echo "Error: Command class {$className} not found.\n";
        }
    }
}
