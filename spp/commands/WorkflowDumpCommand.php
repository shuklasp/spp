<?php
namespace SPP\CLI\Commands;

use SPPMod\SPPWorkflow\SPPWorkflowManager;

class WorkflowDumpCommand extends \SPP\CLI\Command
{
    public function getName(): string
    {
        return 'workflow:dump';
    }

    public function getDescription(): string
    {
        return 'Dump a workflow definition as a visual state graph (Mermaid.js or Graphviz DOT)';
    }

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        require_once SPP_MODULES_DIR . '/spp/sppworkflow/src/WorkflowManager.php';
        $workflowKey = $this->getArgument($args, 0) ?? null;


        $format = 'mermaid';
        $filePath = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--format=')) {
                $format = substr($arg, 9);
            }
            if (str_starts_with($arg, '--file=')) {
                $filePath = substr($arg, 7);
            }
        }

        $workflow = null;
        
        if ($filePath) {
            if (!file_exists($filePath)) {
                echo "Error: File not found '{$filePath}'.\n";
                return;
            }
            if (!class_exists('\Symfony\Component\Yaml\Yaml')) {
                require_once SPP_APP_DIR . '/vendor/autoload.php';
            }
            $parsed = \Symfony\Component\Yaml\Yaml::parseFile($filePath);
            
            // If the user provided a key, find it inside the file
            if ($workflowKey && isset($parsed[$workflowKey]) && is_array($parsed[$workflowKey])) {
                $workflow = $parsed[$workflowKey];
            } elseif (isset($parsed['states']) || isset($parsed['transitions'])) {
                $workflow = $parsed;
                if (!$workflowKey) {
                    $workflowKey = $parsed['name'] ?? pathinfo($filePath, PATHINFO_FILENAME);
                }
            } else {
                // Find first workflow definition array in the file
                foreach ($parsed as $k => $val) {
                    if (is_array($val) && (isset($val['states']) || isset($val['transitions']))) {
                        $workflow = $val;
                        if (!$workflowKey) {
                            $workflowKey = (string)$k;
                        }
                        break;
                    }
                }
            }

            if (!$workflowKey) {
                $workflowKey = pathinfo($filePath, PATHINFO_FILENAME) ?: 'workflow';
            }
        } else {
            if (!$workflowKey) {
                echo "Error: Please specify a workflow key (e.g. node.article or expense) or a --file.\n";
                return;
            }
            $parts = explode('.', $workflowKey);
            $entityType = $parts[0];
            $bundle = $parts[1] ?? 'default';

            $workflow = SPPWorkflowManager::getWorkflow($entityType, $bundle);
        }

        if (!$workflow || !is_array($workflow)) {
            echo "Error: Workflow definition not found.\n";
            return;
        }

        echo "Dumping workflow '{$workflowKey}' in {$format} format:\n\n";

        if ($format === 'dot') {
            $this->dumpDot((string)$workflowKey, $workflow);
        } else {
            $this->dumpMermaid((string)$workflowKey, $workflow);
        }
    }

    protected function dumpMermaid(string $name, array $workflow): void
    {
        $initialState = 'start';
        if (isset($workflow['initial_state'])) {
            $initialState = (string)$workflow['initial_state'];
        } elseif (isset($workflow['states']) && is_array($workflow['states'])) {
            if (array_is_list($workflow['states'])) {
                $initialState = (string)($workflow['states'][0] ?? 'start');
            } else {
                $initialState = (string)(array_key_first($workflow['states']) ?? 'start');
            }
        }

        echo "```mermaid\n";
        echo "stateDiagram-v2\n";
        echo "    [*] --> " . $initialState . "\n";

        $transitions = $workflow['transitions'] ?? [];
        foreach ($transitions as $tName => $meta) {
            $froms = (array)($meta['from'] ?? []);
            $to = $meta['to'] ?? 'end';
            foreach ($froms as $from) {
                $cleanFrom = ($from === '*') ? '[*]' : $from;
                echo "    {$cleanFrom} --> {$to} : {$tName}\n";
            }
        }
        echo "```\n";
    }

    protected function dumpDot(string $name, array $workflow): void
    {
        $cleanName = str_replace(['.', '-', ' '], '_', $name);
        echo "digraph {$cleanName} {\n";
        echo "    rankdir=LR;\n";
        echo "    node [shape = circle];\n";

        $transitions = $workflow['transitions'] ?? [];
        foreach ($transitions as $tName => $meta) {
            $froms = (array)($meta['from'] ?? []);
            $to = $meta['to'] ?? 'end';
            foreach ($froms as $from) {
                $cleanFrom = ($from === '*') ? 'any' : $from;
                echo "    \"{$cleanFrom}\" -> \"{$to}\" [ label = \"{$tName}\" ];\n";
            }
        }
        echo "}\n";
    }
}
