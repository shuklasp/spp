<?php
namespace SPP\CLI\Commands;

use SPP\Core\WorkflowManager;

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
        $workflowKey = $args[0] ?? null;
        if (!$workflowKey) {
            echo "Error: Please specify a workflow key (e.g. node.article or expense).\n";
            return;
        }

        $format = 'mermaid';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--format=')) {
                $format = substr($arg, 9);
            }
        }

        $parts = explode('.', $workflowKey);
        $entityType = $parts[0];
        $bundle = $parts[1] ?? 'default';

        $workflow = WorkflowManager::getWorkflow($entityType, $bundle);
        if (!$workflow) {
            echo "Error: Workflow definition not found for '{$workflowKey}'.\n";
            return;
        }

        echo "Dumping workflow '{$workflowKey}' in {$format} format:\n\n";

        if ($format === 'dot') {
            $this->dumpDot($workflowKey, $workflow);
        } else {
            $this->dumpMermaid($workflowKey, $workflow);
        }
    }

    protected function dumpMermaid(string $name, array $workflow): void
    {
        echo "```mermaid\n";
        echo "stateDiagram-v2\n";
        echo "    [*] --> " . ($workflow['states'][0] ?? 'start') . "\n";

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
        $cleanName = str_replace('.', '_', $name);
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
