<?php
/**
 * System Lifecycle & Updates View (PHP-Hybrid)
 * EXACT PARITY WITH lifecycle.js
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
        <span class="icon">🚀</span>
        <h1>Lifecycle & Deployment</h1>
    </div>
    <div class="view-actions">
        <button class="btn btn-secondary" onclick="admin.api('lifecycle_backup')">
            <span class="icon">📦</span> Local Backup
        </button>
        <button class="btn btn-primary" onclick="admin.runSystemUpdate()">
            <span class="icon">🔍</span> Check Sync Status
        </button>
    </div>
</div>

<div class="lifecycle-grid">
    <div class="card env-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3>Environment Target</h3>
            <button class="btn btn-sm ghost-btn" onclick="admin.api('lifecycle_config_target')" title="Configure Target">⚙️</button>
        </div>
        <div class="form-group">
            <label>Target Server</label>
            <select id="target-env" class="form-control">
                <?php echo $envOptions ?: '<option value="production">production</option>'; ?>
            </select>
        </div>
        <div id="env-status" class="status-indicator">
            Status: <span class="badge badge-neutral">Unknown</span>
        </div>
    </div>

    <div class="card security-card">
        <h3>Security & Authorization</h3>
        <div class="form-group">
            <label>Local Deployment Token</label>
            <div class="input-group-with-btn" style="display: flex; gap: 8px;">
                <input type="password" id="local-token" class="form-control" readonly value="••••••••••••••••">
                <button class="btn btn-sm btn-secondary" onclick="admin.notify('Vault access restricted', 'info')" title="Show/Hide">👁️</button>
                <button class="btn btn-sm btn-outline-danger" onclick="admin.syncDeploymentToken()" title="Rotate Token">🔄</button>
            </div>
            <small class="muted">This token is required by remote servers to authorize pushes from this machine.</small>
        </div>
    </div>

    <div class="card remote-config-card">
        <h3>Remote Config Management</h3>
        <div id="remote-config-status">
            <button class="btn btn-sm btn-outline-primary" onclick="admin.notify('Fetching production settings...', 'info')">
                Fetch Production Settings
            </button>
        </div>
        <div id="remote-config-list" style="margin-top: 15px; display: none;">
            <div class="muted" style="font-size: 0.8rem; margin-bottom: 5px;">Manage production settings without leaving local workbench.</div>
            <div class="remote-config-table-wrapper" style="max-height: 200px; overflow-y: auto;">
                <table class="table table-sm" id="remote-config-table">
                    <tbody id="remote-config-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card delta-card" id="delta-container" style="display: none;">
    <h3>Pending Changes</h3>
    <div class="delta-list-wrapper">
        <table class="table" id="delta-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Path / Resource</th>
                    <th>Action</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="delta-body"></tbody>
        </table>
    </div>
    <div class="view-footer">
        <button class="btn btn-success" id="btn-sync-all">
            Deploy All Changes
        </button>
    </div>
</div>
