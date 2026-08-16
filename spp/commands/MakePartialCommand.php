<?php

namespace SPP\CLI\Commands;

/**
 * Class MakePartialCommand
 * Scaffolds a new external view partial template (e.g. HTML, PHP, JS).
 */
class MakePartialCommand extends BaseMakeCommand
{
    protected string $name = 'make:partial';
    protected string $description = 'Scaffold a new external view partial template (HTML/PHP/JS)';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $name = null;
        
        foreach ($args as $i => $arg) {
            if (strpos(strtolower($arg), '--name=') === 0) {
                $name = substr($arg, 7);
            } elseif ($i === 0 && strpos($arg, '--') !== 0) {
                $name = $arg;
            }
        }

        if (!$name) {
            echo "Usage: php spp.php make:partial <PartialName.html|.php|.js> [--app=AppName]\n";
            return;
        }

        $context = $this->getContext($args);
        $targetDir = SPP_APP_DIR . '/resources/views/partials';
        
        $fileName = $name;
        if (!preg_match('/\.(php|html|js)$/i', $fileName)) {
            $fileName .= '.html'; // Default to .html partial if no extension provided
        }
        
        $filePath = $targetDir . "/" . ltrim($fileName, '/\\');

        if (file_exists($filePath)) {
            echo "Error: Partial template {$name} already exists at {$filePath}.\n";
            return;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'php') {
            $content = <<<'PARTIAL'
<?php
/**
 * External View Partial: {{PARTIAL_NAME}}
 * Context: {{CONTEXT}}
 * Designed to be inserted or updated at a particular place in the main page via HTMX or ViewController::renderPartial().
 */
?>
<div class="spp-partial-container" id="partial-{{PARTIAL_ID}}">
    <div class="partial-header">
        <h4>{{PARTIAL_NAME}}</h4>
        <span class="badge badge-primary">{{CONTEXT}}</span>
    </div>
    <div class="partial-body">
        <p>This dynamic PHP partial was rendered externally without inline HTML string literals.</p>
        <?php if (isset($item)): ?>
            <div class="item-details">
                <pre><?= htmlspecialchars(json_encode($item, JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8') ?></pre>
            </div>
        <?php endif; ?>
    </div>
</div>
PARTIAL;
        } elseif ($ext === 'js') {
            $content = <<<'PARTIAL'
/**
 * External JS Component / Partial: {{PARTIAL_NAME}}
 * Context: {{CONTEXT}}
 */
export default function render{{PARTIAL_CLASS}}(data) {
    const container = document.createElement('div');
    container.className = 'spp-partial-container';
    container.id = 'partial-{{PARTIAL_ID}}';
    container.innerHTML = `
        <div class="partial-header">
            <h4>{{PARTIAL_NAME}}</h4>
            <span class="badge badge-primary">{{CONTEXT}}</span>
        </div>
        <div class="partial-body">
            <p>This dynamic JS component was rendered externally without inline HTML string literals in controllers.</p>
            <pre>${JSON.stringify(data, null, 2)}</pre>
        </div>
    `;
    return container;
}
PARTIAL;
        } else {
            $content = <<<'PARTIAL'
<!--
  External HTML Partial: {{PARTIAL_NAME}}
  Context: {{CONTEXT}}
  Designed to be inserted or updated at a particular place in the main page via HTMX or ViewController::renderStaticPartial().
-->
<div class="spp-partial-container" id="partial-{{PARTIAL_ID}}">
    <div class="partial-header">
        <h4>{{PARTIAL_NAME}}</h4>
        <span class="badge badge-primary">{{CONTEXT}}</span>
    </div>
    <div class="partial-body">
        <p>This static HTML partial was rendered externally without inline HTML string literals in controllers.</p>
    </div>
</div>
PARTIAL;
        }

        $partialId = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', basename($name, ".$ext")));
        $partialClass = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', basename($name, ".$ext"))));

        $content = str_replace(
            ['{{PARTIAL_NAME}}', '{{CONTEXT}}', '{{PARTIAL_ID}}', '{{PARTIAL_CLASS}}'], 
            [basename($name), $context, $partialId, $partialClass], 
            $content
        );
        
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }
        
        file_put_contents($filePath, $content);
        echo "Success: External partial template created at {$filePath}\n";
    }
}
