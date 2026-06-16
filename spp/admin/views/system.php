<?php
/**
 * System Dashboard View (PHP-Hybrid)
 * EXACT PARITY WITH system.js
 */
$la = $params['la'] ?? null;
$data = $params['data'] ?? [];
$selectedApp = $params['selectedApp'] ?? 'default';

$system = $data['system'] ?? [];
$bridge = $data['bridge'] ?? [];
$apps = $data['apps'] ?? [];
$stats = $system['stats'] ?? [];
$orion = $system['orion'] ?? [];
$health = $system['health_report'] ?? ['score' => 100, 'checks' => []];

$activeApp = null;
foreach($apps as $a) {
    if ($a['name'] === $selectedApp) {
        $activeApp = $a;
        break;
    }
}
$activeApp = $activeApp ?? [];

$truncatePath = function($path, $len) {
    if (!$path) return 'N/A';
    return strlen($path) > $len ? '...' . substr($path, -$len) : $path;
};

$getStatusTheme = function($status) {
    switch ($status) {
        case 'OK': return 'active';
        case 'WARN': return 'warning';
        case 'ERROR': return 'inactive';
        default: return 'neutral';
    }
};

$getBadgeTheme = function($status) {
    switch ($status) {
        case 'OK': return 'success';
        case 'WARN': return 'warning';
        case 'ERROR': return 'danger';
        default: return 'secondary';
    }
};

$scoreColor = '#10b981';
if ($health['score'] < 90) $scoreColor = '#f59e0b';
if ($health['score'] < 70) $scoreColor = '#ef4444';

// Mock settings if missing from server response for parity with JS UI
$settings = $data['settings'] ?? [
    'parsed' => ['settings' => ['debug' => true], 'prototyping' => ['auto_evolution' => 'manual', 'view_generation' => 'php_html']],
    'raw' => "settings:\n  debug: true\nprototyping:\n  auto_evolution: manual\n  view_generation: php_html"
];
$proto = $settings['parsed']['prototyping'] ?? ['auto_evolution' => 'manual', 'view_generation' => 'php_html'];

?>

<style>
    .system-view-container {
        animation: fadeIn 0.5s ease-out;
    }
    .hero-context {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(167, 139, 250, 0.05) 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 24px;
        padding: 2.5rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }
    .hero-context::after {
        content: '🎯';
        position: absolute;
        right: -20px;
        bottom: -20px;
        font-size: 12rem;
        opacity: 0.03;
        transform: rotate(-15deg);
    }
    .hero-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 2rem;
    }
    .hero-title h2 {
        font-size: 2rem;
        margin: 0;
        background: linear-gradient(to right, #EA580C, var(--accent));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .hero-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 2rem;
    }
    .hero-stat-item label {
        display: block;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: var(--text-dim);
        margin-bottom: 0.5rem;
    }
    .hero-stat-item .val {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-main);
    }

    .dashboard-compact-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    @media (max-width: 1200px) { .dashboard-compact-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 600px) { .dashboard-compact-grid { grid-template-columns: 1fr; } }

    .compact-card {
        background: var(--panel-bg);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        transition: all 0.3s var(--transition);
    }
    .compact-card:hover {
        border-color: var(--primary);
        transform: translateY(-4px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    }
    .card-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .card-icon-sm {
        font-size: 1.5rem;
        width: 40px;
        height: 40px;
        background: rgba(255,255,255,0.03);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .card-label {
        font-size: 0.8rem;
        color: var(--text-dim);
        font-weight: 500;
    }
    .card-value {
        font-size: 1.5rem;
        font-weight: 700;
    }

    .section-title-bar {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 3rem 0 1.5rem 0;
    }
    .section-title-bar h3 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 600;
        letter-spacing: -0.01em;
    }
    .section-line {
        flex: 1;
        height: 1px;
        background: linear-gradient(to right, var(--glass-border), transparent);
    }
    
    .elegant-table {
        width: 100%;
        border-collapse: collapse;
    }
    .elegant-table td, .elegant-table th {
        padding: 12px 20px;
        text-align: left;
    }
    
    .code-badge {
        padding: 4px 8px;
        border-radius: 6px;
        font-family: var(--font-mono);
        font-size: 0.85rem;
    }
    .code-badge.primary { background: rgba(99, 102, 241, 0.1); color: var(--primary); }
    .code-badge.warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
</style>

<div class="system-view-container">
    <!-- Hero Context Section -->
    <div class="hero-context">
        <div class="hero-header">
            <div class="hero-title">
                <label style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.2em; color: var(--primary);">System Environment</label>
                <h2>Active Context: <?php echo htmlspecialchars($selectedApp); ?></h2>
            </div>
            <div class="hero-actions">
                <span class="tag <?php echo !empty($activeApp['db_config']) ? 'warning-tag' : 'success-tag'; ?>" style="padding: 6px 12px; font-size: 0.8rem;">
                    <?php echo !empty($activeApp['db_config']) ? '🚀 Custom DB Isolation' : '🛡️ System Default DB'; ?>
                </span>
            </div>
        </div>
        
        <div class="hero-grid">
            <div class="hero-stat-item">
                <label>Base URL</label>
                <div class="val"><code class="code-badge primary"><?php echo htmlspecialchars($activeApp['base_url'] ?? '/'); ?></code></div>
            </div>
            <div class="hero-stat-item">
                <label>Table Prefix</label>
                <div class="val"><code class="code-badge warning"><?php echo htmlspecialchars($activeApp['table_prefix'] ?? '(none)'); ?></code></div>
            </div>
            <div class="hero-stat-item">
                <label>Shared Resource Group</label>
                <div class="val"><span class="tag info-tag"><?php echo htmlspecialchars($activeApp['shared_group'] ?? 'Isolated'); ?></span></div>
            </div>
            <div class="hero-stat-item">
                <label>Asset Bundling</label>
                <div class="val"><span class="badge <?php echo ($stats['bundling_enabled'] ?? true) ? 'success' : 'secondary'; ?>"><?php echo ($stats['bundling_enabled'] ?? true) ? 'ENABLED' : 'DISABLED'; ?></span></div>
            </div>
        </div>
    </div>

    <!-- Compact Stats Grid -->
    <div class="dashboard-compact-grid">
        <div class="compact-card">
            <div class="card-top">
                <span class="card-label">Middleware</span>
                <div class="card-icon-sm" style="color: var(--primary);">🔀</div>
            </div>
            <div class="card-value"><?php echo $stats['middleware_count'] ?? 0; ?></div>
            <div style="margin-top: auto;">
                <button class="btn ghost-btn btn-xs" onclick="location.hash = 'middleware'">View Pipeline</button>
            </div>
        </div>
        <div class="compact-card">
            <div class="card-top">
                <span class="card-label">Queued Tasks</span>
                <div class="card-icon-sm" style="color: var(--accent);">🕒</div>
            </div>
            <div class="card-value"><?php echo $stats['queue_size'] ?? 0; ?></div>
            <div style="margin-top: auto;">
                <button class="btn ghost-btn btn-xs" onclick="location.hash = 'queue'">Manage Queue</button>
            </div>
        </div>
        <div class="compact-card">
            <div class="card-top">
                <span class="card-label">Orion Cache</span>
                <div class="card-icon-sm" style="color: var(--info);">⚡</div>
            </div>
            <div class="card-value"><?php echo $orion['cache_size'] ?? '0KB'; ?></div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top: auto;">
                <span class="badge <?php echo ($orion['cache_exists'] ?? false) ? 'success' : 'danger'; ?>" style="font-size: 0.6rem;"><?php echo ($orion['cache_exists'] ?? false) ? 'OPTIMIZED' : 'LEGACY'; ?></span>
                <button class="btn ghost-btn btn-xs" onclick="admin.api('compile_registry')">Rebuild</button>
            </div>
        </div>
        <div class="compact-card">
            <div class="card-top">
                <span class="card-label">DB Status</span>
                <div class="card-icon-sm" style="color: var(--success);">💾</div>
            </div>
            <div class="card-value" style="font-size: 1.2rem; color: <?php echo ($system['db_status'] ?? 'Connected') === 'Connected' ? 'var(--success)' : 'var(--danger)'; ?>;">
                <?php echo $system['db_status'] ?? 'Unknown'; ?>
            </div>
            <div style="margin-top: auto; font-size: 0.7rem; opacity: 0.5;">Framework v<?php echo $system['spp_version'] ?? '0.0'; ?></div>
        </div>
    </div>

    <!-- Health Report -->
    <div class="section-title-bar">
        <h3>System Health Report</h3>
        <div class="section-line"></div>
        <div class="health-score" style="display:flex; align-items:center; gap:10px; background: rgba(0,0,0,0.2); padding: 5px 15px; border-radius: 20px;">
            <span style="font-size:0.75rem; opacity:0.8; text-transform: uppercase;">Overall Health:</span>
            <strong style="font-size:1.1rem; color: <?php echo $scoreColor; ?>; text-shadow: 0 0 10px <?php echo $scoreColor; ?>44;"><?php echo $health['score']; ?>%</strong>
        </div>
    </div>

    <div class="health-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
        <?php foreach($health['checks'] as $check): ?>
            <div class="health-item-card glass-panel" style="padding: 1.5rem; display: flex; align-items: center; gap: 15px; transition: all 0.3s var(--transition);">
                <div class="status-indicator <?php echo $getStatusTheme($check['status']); ?>" style="width: 12px; height: 12px; box-shadow: 0 0 10px currentColor;"></div>
                <div style="flex: 1;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 4px;">
                        <div style="font-weight: 600; font-size: 0.95rem;"><?php echo $check['name']; ?></div>
                        <span class="badge <?php echo $getBadgeTheme($check['status']); ?>" style="font-size: 0.6rem; padding: 2px 6px;"><?php echo $check['status']; ?></span>
                    </div>
                    <div style="font-size: 0.8rem; opacity: 0.6;"><?php echo $check['detail']; ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Environment Diagnostics -->
    <div class="section-title-bar">
        <h3>Environment Diagnostics</h3>
        <div class="section-line"></div>
    </div>

    <div class="glass-panel" style="overflow: hidden; padding: 0;">
        <table class="data-table elegant-table" style="margin: 0;">
            <tr><th style="width: 250px; background: rgba(255,255,255,0.02);">Parameter</th><th>Value</th></tr>
            <tr><td style="background: rgba(255,255,255,0.01); font-weight: 500;">PHP Version</td><td><?php echo $system['php_version'] ?? 'N/A'; ?></td></tr>
            <tr><td style="background: rgba(255,255,255,0.01); font-weight: 500;">Operating System</td><td><?php echo $system['os'] ?? 'N/A'; ?></td></tr>
            <tr><td style="background: rgba(255,255,255,0.01); font-weight: 500;">Server Software</td><td><?php echo $system['server_software'] ?? 'N/A'; ?></td></tr>
            <tr><td style="background: rgba(255,255,255,0.01); font-weight: 500;">Framework Root</td><td><code class="path-label" style="font-size: 0.8rem;"><?php echo $system['spp_base'] ?? '/'; ?></code></td></tr>
        </table>
    </div>

    <!-- Identity & Security (IAM) -->
    <div class="section-title-bar">
        <h3>Identity & Security (IAM)</h3>
        <div class="section-line"></div>
        <button class="btn ghost-btn btn-xs" onclick="location.hash = 'iam'">Manage IAM</button>
    </div>
    
    <div class="dashboard-compact-grid">
        <div class="compact-card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, transparent 100%);">
            <div class="card-top">
                <span class="card-label">OAuth Clients</span>
                <div class="card-icon-sm" style="color: #3b82f6;">🔑</div>
            </div>
            <div class="card-value" id="iam_oauth_count">--</div>
            <div style="margin-top: auto;">
                <button class="btn primary-btn btn-xs" onclick="admin.api('IAM_ListOAuthClients', {}, function(res){ console.log('OAuth Clients', res.data); alert('Check console for OAuth clients list.'); })">View Clients</button>
                <button class="btn ghost-btn btn-xs" onclick="let name=prompt('App Name:'); let uri=prompt('Redirect URI:'); if(name&&uri) admin.api('IAM_SaveOAuthClient', {id: 'client_'+Math.random().toString(36).substr(2,6), name:name, redirect_uri:uri}, function(r){ if(r.status==='success') alert('Client Created! Secret: ' + r.data.client_secret); location.reload(); });">Create New</button>
            </div>
        </div>
        
        <div class="compact-card" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.05) 0%, transparent 100%);">
            <div class="card-top">
                <span class="card-label">ABAC Policies</span>
                <div class="card-icon-sm" style="color: #ef4444;">🛡️</div>
            </div>
            <div class="card-value" id="iam_abac_count">--</div>
            <div style="margin-top: auto;">
                <button class="btn primary-btn btn-xs" onclick="admin.api('IAM_ListABAC', {}, function(res){ console.log('ABAC Policies', res.data); alert('Check console for ABAC policies list.'); })">View Policies</button>
                <button class="btn ghost-btn btn-xs" onclick="let perm=prompt('Permission (e.g. read:data):'); let logic=prompt('Condition Logic:'); if(perm&&logic) admin.api('IAM_SaveABAC', {permission:perm, condition_logic:logic, status:'active'}, function(){ location.reload(); });">New Policy</button>
            </div>
        </div>
        
        <div class="compact-card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, transparent 100%);">
            <div class="card-top">
                <span class="card-label">RBAC Roles</span>
                <div class="card-icon-sm" style="color: #10b981;">👥</div>
            </div>
            <div class="card-value" id="iam_role_count">--</div>
            <div style="margin-top: auto;">
                <button class="btn primary-btn btn-xs" onclick="admin.api('IAM_ListRoles', {}, function(res){ console.log('Roles', res.data); alert('Check console for Roles list.'); })">Manage Roles</button>
            </div>
        </div>
    </div>
    <script>
        // Auto-fetch basic IAM stats
        setTimeout(() => {
            admin.api('IAM_ListOAuthClients', {}, function(r) {
                if(r.data && r.data.sources && r.data.sources[0]) {
                    document.getElementById('iam_oauth_count').innerText = r.data.sources[0].items.length;
                }
            });
            admin.api('IAM_ListABAC', {}, function(r) {
                if(r.data && r.data.sources && r.data.sources[0]) {
                    document.getElementById('iam_abac_count').innerText = r.data.sources[0].items.length;
                }
            });
            admin.api('IAM_ListRoles', {}, function(r) {
                if(r.data && r.data.sources && r.data.sources[0]) {
                    document.getElementById('iam_role_count').innerText = r.data.sources[0].items.length;
                }
            });
        }, 1000);
    </script>

    <!-- Polyglot Bridge -->
    <?php if($bridge): ?>
        <div class="section-title-bar">
            <h3>Polyglot Resource Bridge</h3>
            <div class="section-line"></div>
            <button class="btn ghost-btn btn-xs" onclick="admin.loadView('system')">🔄 Sync Environment</button>
        </div>
        <div class="bridge-stats-bar glass-panel" style="display: flex; gap: 40px; padding: 1.5rem; margin-bottom: 1.5rem; background: linear-gradient(to right, rgba(99, 102, 241, 0.05), transparent);">
            <div class="hero-stat-item">
                <label>Shared Data Root</label>
                <code class="path-label" title="<?php echo $bridge['shared_dir']; ?>"><?php echo $truncatePath($bridge['shared_dir'], 50); ?></code>
            </div>
            <div class="hero-stat-item">
                <label>Config Status</label>
                <span class="tag <?php echo $bridge['config_exists'] ? 'success-tag' : 'danger-tag'; ?>"><?php echo $bridge['config_exists'] ? 'ACTIVE' : 'MISSING'; ?></span>
            </div>
            <div class="hero-stat-item">
                <label>Last Synchronization</label>
                <strong><?php echo $bridge['last_sync'] ?? 'Never'; ?></strong>
            </div>
        </div>

        <div class="runtime-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
            <?php foreach(($bridge['runtimes'] ?? []) as $id => $r): ?>
                <div class="runtime-card glass-panel" style="padding: 1.5rem; transition: all 0.3s var(--transition); position: relative; overflow: hidden;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 1rem;">
                        <div>
                            <div style="font-weight: 700; font-size: 1.1rem; color: var(--text-main);"><?php echo $r['name']; ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase;">Runtime Engine</div>
                        </div>
                        <div class="status-indicator <?php echo $r['path'] ? 'active' : 'inactive'; ?>" style="width: 10px; height: 10px;"></div>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <div style="font-size: 0.7rem; color: var(--text-dim); margin-bottom: 4px;">Executable Path</div>
                        <code style="font-size: 0.75rem; display: block; background: rgba(0,0,0,0.2); padding: 6px; border-radius: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo $r['path']; ?>">
                            <?php echo $r['path'] ? $truncatePath($r['path'], 40) : 'Not Discovered'; ?>
                        </code>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div class="badge <?php echo $r['path'] ? 'success' : 'secondary'; ?>" style="font-size: 0.65rem;">
                            <?php echo ($r['version'] && $r['version'] !== 'Unknown') ? $r['version'] : ($r['path'] ? 'Detected' : 'Offline'); ?>
                        </div>
                        <?php if($r['path']): ?>
                            <button class="btn primary-btn btn-xs" style="padding: 4px 12px; font-size: 0.7rem;" onclick="admin.testRuntime('<?php echo $id; ?>')">
                                ⚡ Test Bridge
                            </button>
                        <?php else: ?>
                            <span style="font-size: 0.7rem; opacity: 0.4;">Binary missing</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="section-title-bar">
        <h3>Framework Configuration</h3>
        <div class="section-line"></div>
    </div>

    <!-- Settings Editor Parity -->
    <div class="details-section glass-panel mt-4">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <h3><span class="view-icon">⚙️</span> Framework Configuration</h3>
            <div style="display:flex; gap:10px;">
                <button class="btn accent-btn btn-sm" onclick="admin.notify('Saving restricted in hybrid mode', 'warning')">
                    💾 Save Settings
                </button>
            </div>
        </div>

        <div class="settings-form" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <div class="form-group">
                <label style="display:block; margin-bottom:8px; font-weight:600; opacity:0.8;">Framework Debug Mode</label>
                <select class="form-input" style="width:100%;">
                    <option value="true" <?php echo ($settings['parsed']['settings']['debug'] ?? true) ? 'selected' : ''; ?>>Enabled (Show Debug Bar)</option>
                    <option value="false" <?php echo !($settings['parsed']['settings']['debug'] ?? true) ? 'selected' : ''; ?>>Disabled (Hide Debug Bar)</option>
                </select>
            </div>
            <div class="form-group">
                <label style="display:block; margin-bottom:8px; font-weight:600; opacity:0.8;">Auto-Evolution (Schema Sync)</label>
                <select class="form-input" style="width:100%;">
                    <option value="manual" <?php echo ($proto['auto_evolution'] === 'manual') ? 'selected' : ''; ?>>Manual (CLI command required)</option>
                    <option value="automatic" <?php echo ($proto['auto_evolution'] === 'automatic') ? 'selected' : ''; ?>>Automatic (On entity save)</option>
                </select>
            </div>
            <div class="form-group">
                <label style="display:block; margin-bottom:8px; font-weight:600; opacity:0.8;">Scaffold View Generation</label>
                <select class="form-input" style="width:100%;">
                    <option value="php_html" <?php echo ($proto['view_generation'] === 'php_html') ? 'selected' : ''; ?>>Legacy PHP/HTML Templates</option>
                    <option value="mod_comp" <?php echo ($proto['view_generation'] === 'mod_comp') ? 'selected' : ''; ?>>Modern Admin Components</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Action Banner -->
    <div class="action-banner glass-panel mt-4" style="display: flex; justify-content: space-between; align-items: center; padding: 2rem; background: linear-gradient(90deg, rgba(99, 102, 241, 0.05) 0%, transparent 100%);">
        <div>
            <h4 style="margin: 0; font-size: 1.1rem;">SPP Developer Workbench</h4>
            <p style="margin: 5px 0 0 0; color: var(--text-dim); font-size: 0.9rem;">Manage all applications and database sharing in the dedicated section.</p>
        </div>
        <div style="display:flex; gap:12px;">
            <button class="btn ghost-btn" onclick="admin.api('execute_command', {cmd: 'cache:clear'})">🧹 Clear Framework Cache</button>
            <button class="btn ghost-btn" onclick="location.hash = 'apps'">📱 Manage Applications</button>
            <button type="button" class="btn accent-btn" onclick="admin.runSystemUpdate()" style="background: var(--accent-gradient); color: white; border: none;">🚀 Update System</button>
        </div>
    </div>
</div>
