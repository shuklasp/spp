<?php

namespace SPP\CLI\Commands;

/**
 * Class MakeLiveComponentCommand
 * Scaffolds a new Live Component class.
 */
class MakeLiveComponentCommand extends BaseMakeCommand
{
    protected string $name = 'make:live-component';
    protected string $description = 'Create a new Live Component class';

    public function execute(array $args): void
    {
        $name = $args[2] ?? null;
        if (!$name) {
            echo "Usage: php spp.php make:live-component <name> [--app=appname]\n";
            return;
        }

        $app = $this->getContext($args);
        $className = ucfirst($name);

        $namespace = $this->getNamespace('Live', $app);
        $targetDir = $this->getTargetDir('live', $app);
        $targetPath = "{$targetDir}/class." . strtolower($className) . ".php";

        $success = $this->buildFromStub('livecomponent', $targetPath, [
            'namespace' => $namespace,
            'className' => $className
        ]);

        if ($success) {
            echo "Success: Live Component {$className} created at {$targetPath}\n";
        }
    }

    public function renderAdminUI(): string
    {
        $name = htmlspecialchars($this->getName());
        $desc = htmlspecialchars($this->getDescription());

        $html = '<div class="command-ui-container">';
        $html .= '  <h3><span class="view-icon">⚡</span> <code>' . $name . '</code></h3>';
        $html .= '  <p>' . $desc . '</p>';
        $html .= '  <hr>';
        $html .= '  <div class="form-group">';
        $html .= '    <label>Component Name (e.g. UserCard, SearchBox):</label>';
        $html .= '    <input type="text" id="arg-comp-name" class="spp-input" placeholder="ComponentName">';
        $html .= '  </div>';
        $html .= '  <div class="form-group">';
        $html .= '    <label>Application Context (optional):</label>';
        $html .= '    <input type="text" id="arg-app" class="spp-input" placeholder="e.g. school">';
        $html .= '  </div>';
        $html .= '  <button class="spp-btn" onclick="executeMakeLiveComponent()">Generate Component</button>';
        $html .= '  <script>';
        $html .= '    function executeMakeLiveComponent() {';
        $html .= '      const compName = document.getElementById("arg-comp-name").value.trim();';
        $html .= '      const app = document.getElementById("arg-app").value.trim();';
        $html .= '      if (!compName) { alert("Component Name is required!"); return; }';
        $html .= '      let args = compName;';
        $html .= '      if (app) args += " --app=" + app;';
        $html .= '      window.executeCommand("' . $name . '", args);';
        $html .= '    }';
        $html .= '  </script>';
        $html .= '</div>';

        return $html;
    }
}
