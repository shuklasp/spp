<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class UiServCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $name = $argv[2] ?? prompt("Service Name (e.g. list_stats)");
                $app = $argv[3] ?? $cliDefaultApp;
                if ($app !== \SPP\Scheduler::getContext()) \SPP\Scheduler::setContext($app);
                $targetDir = SPP_APP_DIR . "/src/{$app}/serv";
                if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                
                $filename = "{$targetDir}/{$name}.php";
                if (file_exists($filename)) die("Error: Service '{$name}' already exists in '{$app}'.\n");
                
                $tpl = "<?php\n/**\n * Service: {$name}\n * Application: {$app}\n */\n\n";
                $tpl .= "try {\n    // Implementation logic here\n";
                $tpl .= "    \$data = ['status' => 'success', 'timestamp' => time()];\n    \n";
                $tpl .= "    echo json_encode(['success' => true, 'data' => \$data]);\n";
                $tpl .= "} catch (\\Exception \$e) {\n";
                $tpl .= "    echo json_encode(['success' => false, 'message' => \$e->getMessage()]);\n}\n";
                
                file_put_contents($filename, $tpl);
                echo "Success: Created Backend Service in {$filename}\n";
    }

    public function getName(): string
    {
        return 'ui:serv';
    }

    public function getDescription(): string
    {
        return 'Legacy port of ui:serv';
    }
}
