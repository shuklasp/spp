<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Class MakeFormCommand
 * Creates a new SPP form definition (YAML or XML).
 */
class MakeFormCommand extends BaseMakeCommand
{
    protected string $name = 'make:form';
    protected string $description = 'Create a new SPP form definition';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $formName = $this->getArgument($args, 0) ?? null;
        if (!$formName) {
            echo "Usage: php spp.php make:form <name> [--app=appname]\n";
            return;
        }

        $app = $this->getContext($args);
        $className = ucfirst($formName);
        if (strpos(strtolower($className), 'form') === false) {
             $className .= 'Form';
        }

        $namespace = $this->getNamespace('Forms', $app);
        $targetDir = $this->getTargetDir('forms', $app);
        $targetPath = "{$targetDir}/class." . strtolower($className) . ".php";

        $formRoute = strtolower(str_replace('Form', '', $className));

        $success = $this->buildFromStub('form', $targetPath, [
            'namespace' => $namespace,
            'className' => $className,
            'formName' => strtolower($className),
            'formRoute' => $formRoute
        ]);

        if ($success) {
            echo "Success: Modern PHP Form {$className} created at {$targetPath}\n";
        }
    }

    public function renderAdminUI(): string
    {
        $name = htmlspecialchars($this->getName());
        $desc = htmlspecialchars($this->getDescription());

        $html = '<div class="command-ui-container">';
        $html .= '  <h3><span class="view-icon">📝</span> <code>' . $name . '</code></h3>';
        $html .= '  <p>' . $desc . '</p>';
        $html .= '  <hr>';
        $html .= '  <div class="form-group">';
        $html .= '    <label>Form Name (e.g. UserForm, ContactForm):</label>';
        $html .= '    <input type="text" id="arg-form-name" class="spp-input" placeholder="FormName">';
        $html .= '  </div>';
        $html .= '  <div class="form-group">';
        $html .= '    <label>Application Context (optional):</label>';
        $html .= '    <input type="text" id="arg-app" class="spp-input" placeholder="e.g. school">';
        $html .= '  </div>';
        $html .= '  <button class="spp-btn" onclick="executeMakeForm()">Generate Form</button>';
        $html .= '  <script>';
        $html .= '    function executeMakeForm() {';
        $html .= '      const formName = document.getElementById("arg-form-name").value.trim();';
        $html .= '      const app = document.getElementById("arg-app").value.trim();';
        $html .= '      if (!formName) { alert("Form Name is required!"); return; }';
        $html .= '      let args = formName;';
        $html .= '      if (app) args += " --app=" + app;';
        $html .= '      window.executeCommand("' . $name . '", args);';
        $html .= '    }';
        $html .= '  </script>';
        $html .= '</div>';

        return $html;
    }
}
