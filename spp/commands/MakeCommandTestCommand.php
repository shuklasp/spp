<?php
namespace SPP\CLI\Commands;

class MakeCommandTestCommand extends BaseMakeCommand
{
    protected string $name = 'make:command-test';
    protected string $description = 'Generate a boilerplate Parikshak feature test for a given command';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $name = $this->getArgument($args, 0) ?? null;
        if (!$name) {
            echo "Usage: php spp.php make:command-test <CommandName> [--app=appname]\n";
            return;
        }

        $app = $this->getContext($args);
        $className = ucfirst($name);
        if (strpos(strtolower($className), 'command') === false) {
             $className .= 'Command';
        }
        $testClassName = $className . 'Test';
        
        // Convert camelCase or PascalCase to colon-separated (e.g. MakeCommand -> make:command) for the default test stub
        $cmdName = strtolower(preg_replace('/(?<!^)[A-Z]/', ':$0', str_replace('Command', '', $className)));

        // Actually the namespace for tests is slightly different
        $namespace = $app === 'default' ? "SPP\\Tests\\Core\\Commands" : "App\\" . ucfirst($app) . "\\Tests\\Commands";
        
        // Target directory for tests
        $baseDir = SPP_APP_DIR;
        $targetDir = $app === 'default' ? "{$baseDir}/tests/core/Commands" : "{$baseDir}/src/{$app}/tests/Commands";
        $targetPath = "{$targetDir}/test.{$testClassName}.php";

        $success = $this->buildFromStub('commandtest', $targetPath, [
            'namespace' => $namespace,
            'className' => $testClassName,
            'commandName' => $cmdName,
        ]);

        if ($success) {
            echo "Success: Command Test {$testClassName} created at {$targetPath}\n";
        }
    }
}
