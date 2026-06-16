<?php

namespace SPP\CLI\Commands;

/**
 * Class MakeControllerCommand
 * Scaffolds a new controller class.
 */
class MakeControllerCommand extends BaseMakeCommand
{
    protected string $name = 'make:controller';
    protected string $description = 'Create a new controller class';

    public function execute(array $args): void
    {
        $name = $args[2] ?? null;
        if (!$name) {
            echo "Usage: php spp.php make:controller <name> [--app=appname] [--resource]\n";
            return;
        }

        $app = $this->getContext($args);
        $isResource = in_array('--resource', $args);
        $className = ucfirst($name);
        if (strpos(strtolower($className), 'controller') === false) {
             $className .= 'Controller';
        }

        $namespace = $this->getNamespace('Controllers', $app);
        $targetDir = $this->getTargetDir('controllers', $app);
        $targetPath = "{$targetDir}/class." . strtolower($className) . ".php";

        $routeName = strtolower(str_replace('Controller', '', $className));

        $success = $this->buildFromStub('controller', $targetPath, [
            'namespace' => $namespace,
            'className' => $className,
            'routeName' => $routeName
        ]);

        if ($success) {
            echo "Success: Controller {$className} created at {$targetPath}\n";
        }
    }

    public function renderAdminUI(): string
    {
        $name = htmlspecialchars($this->getName());
        $desc = htmlspecialchars($this->getDescription());

        $html = '<div class="command-ui-container">';
        $html .= '  <h3><span class="view-icon">🕹️</span> <code>' . $name . '</code></h3>';
        $html .= '  <p>' . $desc . '</p>';
        $html .= '  <hr>';
        $html .= '  <div class="form-group">';
        $html .= '    <label>Controller Name (e.g. UserController):</label>';
        $html .= '    <input type="text" id="arg-ctrl-name" class="spp-input" placeholder="ControllerName">';
        $html .= '  </div>';
        $html .= '  <div class="form-group">';
        $html .= '    <label>Application Context (optional):</label>';
        $html .= '    <input type="text" id="arg-app" class="spp-input" placeholder="e.g. school">';
        $html .= '  </div>';
        $html .= '  <button class="spp-btn" onclick="executeMakeController()">Generate Controller</button>';
        $html .= '  <script>';
        $html .= '    function executeMakeController() {';
        $html .= '      const ctrlName = document.getElementById("arg-ctrl-name").value.trim();';
        $html .= '      const app = document.getElementById("arg-app").value.trim();';
        $html .= '      if (!ctrlName) { alert("Controller Name is required!"); return; }';
        $html .= '      let args = ctrlName;';
        $html .= '      if (app) args += " --app=" + app;';
        $html .= '      window.executeCommand("' . $name . '", args);';
        $html .= '    }';
        $html .= '  </script>';
        $html .= '</div>';

        return $html;
    }
}
