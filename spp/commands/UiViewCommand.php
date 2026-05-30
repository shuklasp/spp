<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class UiViewCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $name = $argv[2] ?? prompt("Component Name (e.g. Dashboard)");
                $app = $argv[3] ?? $cliDefaultApp;
                if ($app !== \SPP\Scheduler::getContext()) \SPP\Scheduler::setContext($app);
                $targetDir = SPP_APP_DIR . "/src/{$app}/comp";
                if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                
                $filename = "{$targetDir}/" . strtolower($name) . ".js";
                if (file_exists($filename)) die("Error: View '{$name}' already exists in '{$app}'.\n");
                
                $tpl = "/**\n * " . ucfirst($name) . " View Component\n */\n";
                $tpl .= "export default class " . ucfirst($name) . "View extends BaseComponent {\n";
                $tpl .= "    async onInit() {\n        this.state = { loading: true };\n        await this.loadData();\n    }\n\n";
                $tpl .= "    async loadData() {\n        this.setState({ loading: false });\n    }\n\n";
                $tpl .= "    render() {\n        return html`\n            <div class=\"" . strtolower($name) . "-view fade-in\">\n";
                $tpl .= "                <h1>" . ucfirst($name) . "</h1>\n                <p>Auto-generated component template.</p>\n";
                $tpl .= "            </div>\n        `;\n    }\n}\n";
                
                file_put_contents($filename, $tpl);
                echo "Success: Created View Component in {$filename}\n";
    }

    public function getName(): string
    {
        return 'ui:view';
    }

    public function getDescription(): string
    {
        return 'Legacy port of ui:view';
    }
}
