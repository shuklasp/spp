<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class UiCompPhpCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $name = $argv[2] ?? prompt("Component Name (e.g. UserProfile)");
                $app = $argv[3] ?? $cliDefaultApp;
                if ($app !== \SPP\Scheduler::getContext()) \SPP\Scheduler::setContext($app);
                $targetDir = SPP_APP_DIR . "/src/{$app}/components";
                if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                
                $filename = "{$targetDir}/{$name}.php";
                if (file_exists($filename)) die("Error: Component '{$name}' already exists in '{$app}'.\n");
                
                $tpl = "<?php\n\nnamespace App\\" . ucfirst($app) . "\\Components;\n\n";
                $tpl .= "use SPPMod\\SPPView\\PHPComponent;\n\n";
                $tpl .= "class {$name} extends PHPComponent {\n";
                $tpl .= "    public \$state = [\n        'title' => 'Hello from {$name}'\n    ];\n\n";
                $tpl .= "    public function render(): string {\n";
                $tpl .= "        return \"<div>\\n            <h1>{\$title}</h1>\\n            <p>Ready to build.</p>\\n        </div>\";\n";
                $tpl .= "    }\n}\n";
                
                file_put_contents($filename, $tpl);
                echo "Success: Created PHP Component in {$filename}\n";
    }

    public function getName(): string
    {
        return 'ui:comp:php';
    }

    public function getDescription(): string
    {
        return 'Legacy port of ui:comp:php';
    }
}
