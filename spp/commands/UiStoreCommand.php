<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class UiStoreCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $name = $argv[2] ?? prompt("Store Name (e.g. UserStore)");
                $app = $argv[3] ?? $cliDefaultApp;
                if ($app !== \SPP\Scheduler::getContext()) \SPP\Scheduler::setContext($app);
                $targetDir = SPP_APP_DIR . "/src/{$app}/store";
                if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                
                $filename = "{$targetDir}/" . strtolower($name) . ".js";
                if (file_exists($filename)) die("Error: Store '{$name}' already exists in '{$app}'.\n");
                
                $tpl = "/**\n * " . ucfirst($name) . " Store\n */\n";
                $tpl .= "const " . ucfirst($name) . " = new SPPStore({\n    initialized: Date.now()\n});\n\n";
                $tpl .= "export default " . ucfirst($name) . ";\n";
                
                file_put_contents($filename, $tpl);
                echo "Success: Created Global Store in {$filename}\n";
    }

    public function getName(): string
    {
        return 'ui:store';
    }

    public function getDescription(): string
    {
        return 'Legacy port of ui:store';
    }
}
