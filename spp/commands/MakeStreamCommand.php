<?php

namespace SPP\CLI\Commands;

/**
 * Class MakeStreamCommand
 * Scaffolds a new external Turbo Stream template.
 */
class MakeStreamCommand extends BaseMakeCommand
{
    protected string $name = 'make:stream';
    protected string $description = 'Scaffold a new external Turbo Stream template';

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
            echo "Usage: php spp.php make:stream <StreamName.html|.php|.blade.php> [--app=AppName]\n";
            return;
        }

        $context = $this->getContext($args);
        $targetDir = $this->getTargetDir('pages/streams', $context);
        
        $fileName = $name;
        if (!preg_match('/\.(php|html|blade\.php)$/i', $fileName)) {
            $fileName .= '.html'; // Default to .html stream if no extension provided
        }
        
        $filePath = $targetDir . "/" . ltrim($fileName, '/\\');

        if (file_exists($filePath)) {
            echo "Error: Stream template {$name} already exists at {$filePath}.\n";
            return;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'php') {
            $content = <<<'STREAM'
<?php
/**
 * External Turbo Stream Template: {{STREAM_NAME}}
 * Context: {{CONTEXT}}
 * Designed to be streamed via ViewController::stream() to dynamically update, append, or replace DOM targets in real-time.
 */
?>
<turbo-stream action="append" target="stream-target-{{STREAM_ID}}">
    <template>
        <div class="spp-stream-item">
            <div class="stream-header">
                <h4>{{STREAM_NAME}}</h4>
                <span class="badge badge-success">{{CONTEXT}} Live Stream</span>
            </div>
            <div class="stream-body">
                <p>This live Turbo Stream was rendered externally without inline HTML string literals in controllers.</p>
                <?php if (isset($items)): ?>
                    <ul>
                        <?php foreach ($items as $item): ?>
                            <li><?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </template>
</turbo-stream>
STREAM;
        } else {
            $content = <<<'STREAM'
<!--
  External Turbo Stream Template: {{STREAM_NAME}}
  Context: {{CONTEXT}}
  Designed to be streamed via ViewController::stream() to dynamically update, append, or replace DOM targets in real-time.
-->
<turbo-stream action="append" target="stream-target-{{STREAM_ID}}">
    <template>
        <div class="spp-stream-item">
            <div class="stream-header">
                <h4>{{STREAM_NAME}}</h4>
                <span class="badge badge-success">{{CONTEXT}} Live Stream</span>
            </div>
            <div class="stream-body">
                <p>This static Turbo Stream was rendered externally without inline HTML string literals in controllers.</p>
            </div>
        </div>
    </template>
</turbo-stream>
STREAM;
        }

        $streamId = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', basename($name, ".$ext")));

        $content = str_replace(
            ['{{STREAM_NAME}}', '{{CONTEXT}}', '{{STREAM_ID}}'], 
            [basename($name), $context, $streamId], 
            $content
        );
        
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }
        
        file_put_contents($filePath, $content);
        echo "Success: External Turbo Stream template created at {$filePath}\n";
    }
}
