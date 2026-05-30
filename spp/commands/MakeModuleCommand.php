<?php

namespace SPP\CLI\Commands;

/**
 * Class MakeModuleCommand
 * Scaffolds a new SPP module.
 */
class MakeModuleCommand extends BaseMakeCommand
{
    protected string $name = 'make:module';
    protected string $description = 'Create a new SPP module';

    public function execute(array $args): void
    {
        $name = $args[2] ?? null;
        if (!$name) {
            echo "Usage: php spp.php make:module <name> [--scope=spp|contrib|app]\n";
            return;
        }

        $scope = 'app';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--scope=')) {
                $scope = substr($arg, 8);
            }
        }

        $modName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
        
        $modDir = SPP_APP_DIR . '/spp/modules/app/' . $modName; // default app scope
        if ($scope === 'spp') {
            $modDir = SPP_BASE_DIR . '/modules/spp/' . $modName;
        } elseif ($scope === 'contrib') {
            $modDir = SPP_BASE_DIR . '/modules/contrib/' . $modName;
        }

        if (is_dir($modDir)) {
            echo "Error: Module '{$modName}' already exists at {$modDir}.\n";
            return;
        }

        mkdir($modDir, 0777, true);

        // Module Manifest (XML)
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<module>\n";
        $xml .= "    <name>{$modName}</name>\n";
        $xml .= "    <author>SPP CLI</author>\n";
        $xml .= "    <version>1.0</version>\n";
        $xml .= "    <description>Auto-generated {$modName} module.</description>\n";
        $xml .= "    <namespace>SPPMod\\" . ucfirst($modName) . "</namespace>\n";
        $xml .= "    <autoload>\n";
        $xml .= "        <class name=\"" . ucfirst($modName) . "\" file=\"class.{$modName}.php\"/>\n";
        $xml .= "    </autoload>\n";
        $xml .= "</module>\n";
        file_put_contents($modDir . '/module.xml', $xml);

        // Core Module Class
        $className = ucfirst($modName);
        $php = "<?php\n";
        $php .= "namespace SPPMod\\{$className};\n\n";
        $php .= "class {$className} extends \\SPP\\SPPObject\n";
        $php .= "{\n    public function __construct() {\n        // Initialize module\n    }\n}\n";
        file_put_contents($modDir . "/class.{$modName}.php", $php);

        echo "Success: Module {$modName} created at {$modDir}\n";
    }

    public function renderAdminUI(): string
    {
        $name = htmlspecialchars($this->getName());
        $html = '<div class="command-ui-container">';
        $html .= '  <h3>Create Module</h3>';
        $html .= '  <div class="form-group">';
        $html .= '    <label>Module Name (e.g. blog, forum):</label>';
        $html .= '    <input type="text" id="arg_name" class="spp-input">';
        $html .= '  </div>';
        $html .= '  <div class="form-group">';
        $html .= '    <label>Scope / Location:</label>';
        $html .= '    <select id="arg_scope" class="spp-input">';
        $html .= '      <option value="app">App-Level (Local)</option>';
        $html .= '      <option value="contrib">Contrib (Community Plugins)</option>';
        $html .= '      <option value="spp">SPP Core (Framework)</option>';
        $html .= '    </select>';
        $html .= '  </div>';
        $html .= '  <button class="spp-btn spp-btn-primary" onclick="executeCustomModuleCreate()">Create Module</button>';
        $html .= '  <script>
                        function executeCustomModuleCreate() {
                            let name = document.getElementById("arg_name").value;
                            let scope = document.getElementById("arg_scope").value;
                            let args = name + " --scope=" + scope;
                            executeCommand("' . $name . '", args);
                        }
                    </script>';
        $html .= '</div>';
        return $html;
    }
}
