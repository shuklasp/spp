<?php
/**
 * System Lifecycle & Deployment View (PHP-Hybrid Fallback)
 * This is the server-rendered fallback. The primary view is lifecycle.js.
 */
$la = $params['la'] ?? null;
$data = $params['data'] ?? [];
$environments = $data['environments'] ?? [];
$target = 'production';

$envOptions = '';
foreach($environments as $name => $env) {
    $selected = ($name === $target) ? 'selected' : '';
    $envOptions .= "<option value='{$name}' {$selected}>{$name}</option>";
}
?>

<div class="view-header">
    <div class="view-title">
        <span class="icon">⚡</span>
        <h1>SPPDeploy — Enterprise Deployment Command Center</h1>
    </div>
    <div class="view-actions">
        <select id="target-env" class="form-control" style="width: 180px;">
            <?php echo $envOptions ?: '<option value="production">production</option>'; ?>
        </select>
        <button class="btn btn-secondary" onclick="admin.api('lifecycle_backup')">
            <span class="icon">📦</span> Backup
        </button>
        <button class="btn btn-primary" onclick="admin.runSystemUpdate()">
            <span class="icon">🔍</span> Check Sync
        </button>
    </div>
</div>

<div class="lifecycle-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="text-align: center; padding: 1.5rem;">
        <div style="font-size: 1.8rem;">🚀</div>
        <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-bright);">—</div>
        <div style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase;">Deployments</div>
    </div>
    <div class="card" style="text-align: center; padding: 1.5rem;">
        <div style="font-size: 1.8rem;">✅</div>
        <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-bright);">—</div>
        <div style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase;">Last Deploy</div>
    </div>
    <div class="card" style="text-align: center; padding: 1.5rem;">
        <div style="font-size: 1.8rem;">📦</div>
        <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-bright);">—</div>
        <div style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase;">Backups</div>
    </div>
    <div class="card" style="text-align: center; padding: 1.5rem;">
        <div style="font-size: 1.8rem;">⏱️</div>
        <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-bright);">—</div>
        <div style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase;">Latency</div>
    </div>
</div>

<p style="color: var(--text-dim); text-align: center; padding: 2rem;">
    This is a simplified server-rendered fallback. For the full interactive deployment dashboard with live log tailing,
    remote command execution, cluster management, and webhook configuration, ensure JavaScript is enabled.
</p>
