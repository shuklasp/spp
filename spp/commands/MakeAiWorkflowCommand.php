<?php

namespace SPP\CLI\Commands;

use SPP\Module;
use SPPMod\SPPAI\SPPAI;

/**
 * Class MakeAiWorkflowCommand
 * Synthesizes natural language prompts into valid sppworkflow YAML definitions.
 */
class MakeAiWorkflowCommand extends BaseMakeCommand
{
    protected string $name = 'ai:make:workflow';
    protected string $description = 'Synthesize natural language business requirements into valid sppworkflow YAML definitions';

    /**
     * Strict CLI SAPI Guarding.
     */
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $prompt = null;
        $workflowName = null;
        $provider = null;

        foreach ($args as $arg) {
            if ($arg === 'spp.php' || $arg === $this->name || str_starts_with($arg, '--')) continue;
            if (!$workflowName) {
                $workflowName = $arg;
            } elseif (!$prompt) {
                $prompt = $arg;
            }
        }

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--provider=')) {
                $provider = substr($arg, 11);
            }
        }

        if (!$workflowName || !$prompt) {
            echo "Usage: php spp.php ai:make:workflow <workflow_name> \"<prompt/description>\" [--app=AppName] [--provider=ollama]\n";
            return;
        }

        $context = $this->getContext($args);

        \SPP\Scheduler::withContext($context, function() use ($workflowName, $prompt, $provider, $context) {
            Module::loadModule('sppai');
            if (!class_exists('\\SPPMod\\SPPAI\\SPPAI')) {
                echo "Error: SPPAI module is not installed or available.\n";
                return;
            }

            // Configurable in app config, ollama by default
            $activeProvider = $provider ?: \SPP\App::getConfig('ai_workflow_provider', $context) ?: Module::getConfig('workflow_provider', 'sppai') ?: 'ollama';

            $cleanWorkflowName = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $workflowName));
            if (!str_ends_with($cleanWorkflowName, '.yml')) {
                $cleanWorkflowName .= '.yml';
            }

            $targetDir = SPP_APP_DIR . ($context === 'default' ? '/etc/workflows' : "/etc/apps/{$context}/workflows");
            $filePath = $targetDir . '/' . $cleanWorkflowName;

            if (file_exists($filePath)) {
                echo "Error: Workflow definition already exists at {$filePath}\n";
                return;
            }

            echo "Synthesizing workflow definition via AI ({$activeProvider})...\n";

            $aiPrompt = <<<PROMPT
You are an expert enterprise workflow architect for the SPP PHP Framework.
Create a complete YAML workflow definition based on the following business requirement:
"{$prompt}"

The YAML MUST include:
- `states`: A list of valid lifecycle stages (e.g. draft, pending_approval, active, cancelled).
- `transitions`: A list of allowed movements between states (with `from`, `to`, and optional `guard`).
- `timeout` & `timeout_transition`: SLA definitions for automated daemon escalation (e.g., timeout: 86400, timeout_transition: escalate).
- `compensations`: Saga pattern compensating transition callbacks for rollback support.

Respond with ONLY the raw YAML code block. Do not include introductory or explanatory text.
PROMPT;

            try {
                $response = SPPAI::using($activeProvider)::complete($aiPrompt);
                $cleanYaml = trim(preg_replace('/^```yaml|^```|```$/i', '', $response));

                $tutorialComments = <<<COMMENTS
# ==============================================================================
# SPP Workflow Definition: {$cleanWorkflowName}
# Context: {$context}
# ------------------------------------------------------------------------------
# TUTORIAL & CORE ORCHESTRATION CONCEPTS:
# 1. States: The valid lifecycle stages an entity can occupy.
# 2. Transitions: Allowed movements between states. Guards can enforce validation.
# 3. Parallel Markings: Entities can maintain multiple concurrent active states.
# 4. Saga Pattern: `compensations` define callbacks/transitions to rollback in-flight transactions.
# 5. SLA Timeouts: `timeout` (seconds) and `timeout_transition` specify auto-escalation via `spp workflow:process-timeouts`.
# ==============================================================================

COMMENTS;

                $finalContent = $tutorialComments . $cleanYaml . "\n";

                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }

                file_put_contents($filePath, $finalContent);
                echo "Success: AI-synthesized workflow definition created at {$filePath}\n";
            } catch (\Exception $e) {
                echo "Error generating workflow: " . $e->getMessage() . "\n";
            }
        });
    }
}
