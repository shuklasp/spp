<?php
// Extracted from DevLifecycleCommand.php
echo '
        <div class="form-group">
            <label style="display:block; margin-bottom: 10px; font-weight: 600;">Deployment Environments (YAML)</label>
            <textarea id="sync-config-raw" class="form-control" style="font-family: \'JetBrains Mono\', monospace; height: 350px; width: 100%; background: rgba(0,0,0,0.2); color: #e2e8f0; padding: 15px; border-radius: 8px; border: 1px solid var(--glass-border);">' . htmlspecialchars($content) . '</textarea>
            <div style="margin-top: 10px; font-size: 0.8rem; opacity: 0.6;">
                ⚠️ Modifying this file will update the remote synchronization targets for all applications.
            </div>
        </div>
    ';
