<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class MakeWizardCommand
 * Scaffolds a modern SPP WizardController with its YAML workflow and external partials.
 */
class MakeWizardCommand extends BaseMakeCommand
{
    protected string $name = 'make:wizard';
    protected string $description = 'Scaffold a modern WizardController, workflow config, and partials';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $wizardName = $this->getArgument($args, 0) ?? null;
        if (!$wizardName) {
            echo "Usage: php spp.php make:wizard <WizardName> [--app=AppName]\n";
            return;
        }

        $app = $this->getContext($args);
        $className = ucfirst($wizardName);
        if (strpos(strtolower($className), 'wizard') === false) {
             $className .= 'Wizard';
        }

        // 1. Scaffold Controller
        $namespace = $this->getNamespace('Controllers', $app);
        $targetDir = $this->getTargetDir('controllers', $app);
        $targetPath = "{$targetDir}/class." . strtolower($className) . ".php";

        $success = $this->buildFromStub('wizard_controller', $targetPath, [
            'namespace' => $namespace,
            'className' => $className,
            'wizardName' => strtolower(str_replace('Wizard', '', $className))
        ]);

        if ($success) {
            echo "Success: WizardController {$className} created at {$targetPath}\n";
        }

        // 2. Scaffold Workflow YAML
        $workflowDir = ($app === 'default') ? SPP_APP_DIR . "/etc/workflows" : SPP_APP_DIR . "/etc/apps/{$app}/workflows";
        $workflowName = strtolower(str_replace('Wizard', '', $className));
        $workflowPath = "{$workflowDir}/{$workflowName}.yml";

        if (!is_dir($workflowDir)) {
            mkdir($workflowDir, 0777, true);
        }

        $workflowSuccess = $this->buildFromStub('wizard_workflow.yml', $workflowPath, [
            'wizardName' => $workflowName
        ]);

        if ($workflowSuccess) {
            echo "Success: Workflow YAML definition created at {$workflowPath}\n";
        }

        // 3. Scaffold HTML Partials
        $partialsDir = $this->getTargetDir('views/partials', $app);
        if (!is_dir($partialsDir)) {
            mkdir($partialsDir, 0777, true);
        }

        $steps = ['step_1', 'step_2', 'step_3', 'completed', 'error'];
        foreach ($steps as $step) {
            $partialPath = "{$partialsDir}/wizard_{$step}.html";
            if (!file_exists($partialPath)) {
                $content = "<!-- SPP External Partial: {$workflowName} wizard {$step} -->\n";
                $content .= "<div class=\"spp-wizard-step\" id=\"wizard-step-{$step}\">\n";
                $content .= "    <h2>Wizard Step: " . ucfirst(str_replace('_', ' ', $step)) . "</h2>\n";
                $content .= "    <p>Context Entity Data: {{ item|json_encode }}</p>\n";
                if ($step !== 'completed' && $step !== 'error') {
                    $content .= "    <form @submit.prevent=\"\$store.workflow.submitStep('{$workflowName}', '{$step}')\">\n";
                    $content .= "        <button type=\"submit\" class=\"spp-btn\">Continue</button>\n";
                    $content .= "    </form>\n";
                }
                $content .= "</div>\n";
                file_put_contents($partialPath, $content);
                echo "Success: Wizard partial created at {$partialPath}\n";
            }
        }
        
        echo "\nWizard {$className} scaffolded successfully.\n";
        echo "Check the generated workflow YAML file for SPP orchestration tutorials and best practices.\n";
    }

    public function renderAdminUI(): string
    {
        return '<p>Please use the CLI for this command: <code>php spp.php make:wizard &lt;Name&gt;</code></p>';
    }
}
