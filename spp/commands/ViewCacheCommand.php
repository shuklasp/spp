<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPPMod\SPPView\ViewCompiler;

/**
 * Class ViewCacheCommand
 * Pre-compiles all AST views for production performance.
 */
class ViewCacheCommand extends Command
{
    protected string $name = 'view:cache';
    protected string $description = 'Pre-compiles all AST views into PHP for optimal performance';

    public function execute(array $args): void
    {
        $app = null;
        foreach ($args as $arg) {
            if (strpos($arg, '--app=') === 0) {
                $app = substr($arg, 6);
            }
        }

        $appDir = $app ? SPP_APP_DIR . '/apps/' . $app : SPP_APP_DIR;
        
        $this->info("Scanning for views in {$appDir}...");

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appDir));
        $count = 0;

        foreach ($files as $file) {
            if ($file->isFile()) {
                $ext = $file->getExtension();
                if ($ext === 'html' || $ext === 'php') {
                    // Skip certain directories like vendor or cache itself
                    if (strpos($file->getPathname(), 'var/cache') !== false || strpos($file->getPathname(), 'vendor') !== false) {
                        continue;
                    }
                    
                    try {
                        ViewCompiler::compile($file->getPathname());
                        $count++;
                    } catch (\Exception $e) {
                        $this->error("Failed to compile: " . $file->getPathname());
                        $this->error($e->getMessage());
                    }
                }
            }
        }

        $this->info("Successfully pre-compiled {$count} views.");
    }

    public function renderAdminUI(): string
    {
        return <<<HTML
<div class="sppux-form-group">
    <label class="sppux-label">Target App (Optional)</label>
    <input type="text" class="sppux-input sppux-input-primary" id="viewCacheTargetApp" placeholder="e.g. frontend" />
    <small class="sppux-help">Leave blank to scan the entire SPP_APP_DIR.</small>
</div>
<button class="sppux-btn sppux-btn-primary" onclick="executeViewCache()">Pre-compile Views</button>

<script>
function executeViewCache() {
    const app = document.getElementById('viewCacheTargetApp').value.trim();
    const args = app ? ['--app=' + app] : [];
    
    // Using the global executeCommand function provided by SPP Admin Command Center
    window.executeCommand('view:cache', args);
}
</script>
HTML;
    }
}
