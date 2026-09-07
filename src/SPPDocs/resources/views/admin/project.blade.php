@extends('layouts.admin')

@section('title', 'Manage ' . ($project['title'] ?? $project_id))

@section('content')
<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">
            Manage: {{ $project['title'] ?? $project_id }}
            <code class="adm-title-project-slug">{{ $project_id }}</code>
        </h1>
        <p class="adm-page-desc">Zero-config project settings, modular enterprise features, storage engine, and team RBAC governance.</p>
    </div>
    <div class="adm-page-actions">
        <a href="@url('project/' . $project_id)" target="_blank" class="adm-btn adm-btn-secondary">
            📖 View Live Docs &rarr;
        </a>
    </div>
</div>

<!-- Tab Navigation -->
@php
    $currentTab = $active_sub_tab ?? 'tab-general';
@endphp
<div class="adm-tabs" id="projectTabs">
    <div class="adm-tab-item {{ $currentTab === 'tab-general' ? 'active' : '' }}" data-tab="tab-general" onclick="switchProjectTab('tab-general')">⚙️ General & Branding</div>
    <div class="adm-tab-item {{ $currentTab === 'tab-features' ? 'active' : '' }}" data-tab="tab-features" onclick="switchProjectTab('tab-features')">🧩 Modular Features</div>
    <div class="adm-tab-item {{ $currentTab === 'tab-storage' ? 'active' : '' }}" data-tab="tab-storage" onclick="switchProjectTab('tab-storage')">🗄️ Storage Engine (Live Migration)</div>
    <div class="adm-tab-item {{ $currentTab === 'tab-links' ? 'active' : '' }}" data-tab="tab-links" onclick="switchProjectTab('tab-links')">🔗 External Links & Repo</div>
    <div class="adm-tab-item {{ $currentTab === 'tab-team' ? 'active' : '' }}" data-tab="tab-team" onclick="switchProjectTab('tab-team')">👥 Team & RBAC Rights</div>
    <div class="adm-tab-item {{ $currentTab === 'tab-releases' ? 'active' : '' }}" data-tab="tab-releases" onclick="switchProjectTab('tab-releases')">📦 Releases & Downloads</div>
    <div class="adm-tab-item {{ $currentTab === 'tab-danger' ? 'active' : '' }}" data-tab="tab-danger" onclick="switchProjectTab('tab-danger')">⚠️ Danger Zone</div>
</div>

<!-- Tab 1: General & Branding -->
<div id="tab-general" class="adm-tab-content" style="{{ $currentTab === 'tab-general' ? 'display: block;' : 'display: none;' }}">
    <form action="@url('admin/project/settings')" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
        <input type="hidden" name="project_id" value="{{ $project_id }}">

        <div class="adm-card">
            <div class="adm-card-header">
                <h2 class="adm-card-title">Project Identity</h2>
            </div>
            <div class="adm-form-grid">
                <div class="adm-form-group">
                    <label class="adm-form-label">Project Title</label>
                    <input type="text" name="settings[title]" value="{{ $project['title'] ?? '' }}" class="adm-form-input" required>
                </div>
                <div class="adm-form-group">
                    <label class="adm-form-label">Motto / Tagline</label>
                    <input type="text" name="settings[motto]" value="{{ $project['motto'] ?? '' }}" class="adm-form-input">
                </div>
                <div class="adm-form-group">
                    <label class="adm-form-label">Description</label>
                    <input type="text" name="settings[description]" value="{{ $project['description'] ?? '' }}" class="adm-form-input">
                </div>
                <div class="adm-form-group">
                    <label class="adm-form-label">Default Documentation Version</label>
                    <input type="text" name="settings[default_version]" value="{{ $project['default_version'] ?? 'v1' }}" class="adm-form-input">
                </div>
            </div>

            <div class="adm-form-group" style="margin-top: 1rem;">
                <label class="adm-form-label">Project Logo</label>
                <div style="display: flex; gap: 1rem; align-items: center; margin-top: 0.5rem;">
                    @if(!empty($project['logo']))
                        <img src="{{ $project['logo'] }}" alt="Logo" style="height: 48px; width: 48px; object-fit: contain; border-radius: 8px; border: 1px solid var(--adm-border);" onerror="this.style.display='none'">
                    @endif
                    <div style="flex: 1;">
                        <input type="text" name="settings[logo]" value="{{ $project['logo'] ?? '' }}" placeholder="Logo URL or relative path" class="adm-form-input">
                    </div>
                    <div>
                        <input type="file" name="logo_file" accept="image/*" class="adm-form-input" style="padding: 0.45rem;">
                    </div>
                </div>
            </div>

            <div class="adm-toggle-row" style="margin-top: 1.5rem;">
                <div class="adm-toggle-info">
                    <div class="adm-toggle-title">🔄 Bi-Directional Git Porcelain Sync</div>
                    <div class="adm-toggle-desc">Automatically create atomic Git commits whenever documentation is saved in the web editor.</div>
                </div>
                <label class="adm-switch">
                    <input type="checkbox" name="git_sync" value="1" {{ !empty($project['git_sync']) ? 'checked' : '' }}>
                    <span class="adm-slider"></span>
                </label>
            </div>

            <div class="adm-toggle-row">
                <div class="adm-toggle-info">
                    <div class="adm-toggle-title">🚀 Auto-Push to Remote</div>
                    <div class="adm-toggle-desc">Automatically push commits to upstream Git remote (origin HEAD) upon web save.</div>
                </div>
                <label class="adm-switch">
                    <input type="checkbox" name="git_auto_push" value="1" {{ !empty($project['git_auto_push']) ? 'checked' : '' }}>
                    <span class="adm-slider"></span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="submit" class="adm-btn adm-btn-primary">💾 Save General Settings</button>
            </div>
        </div>
    </form>
</div>

<!-- Tab 2: Modular Features & Drivers -->
<div id="tab-features" class="adm-tab-content" style="{{ $currentTab === 'tab-features' ? 'display: block;' : 'display: none;' }}">
    @if(!empty($_SESSION['adm_flash_warning']))
        <div class="adm-alert" style="background: rgba(245, 158, 11, 0.1); border: 1px solid #f59e0b; color: #b45309; padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1.25rem;">
            ⚠️ {{ $_SESSION['adm_flash_warning'] }}
            <?php unset($_SESSION['adm_flash_warning']); ?>
        </div>
    @endif

    <!-- Presets Control Bar -->
    <div class="adm-card" style="margin-bottom: 1.5rem; background: var(--adm-card-bg, #ffffff); border: 1px solid var(--adm-border, #e2e8f0);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.75rem;">
            <div>
                <h3 style="margin: 0 0 0.25rem 0; font-size: 1.05rem; color: var(--adm-text-main, #0f172a);">
                    ⚡ Quick Configuration Presets
                </h3>
                <p style="margin: 0; font-size: 0.85rem; color: var(--adm-text-muted, #64748b);">
                    Switch between pre-configured modular profiles or create your own custom presets to declutter the interface.
                </p>
            </div>
            <button type="button" class="adm-btn adm-btn-secondary adm-btn-sm" onclick="document.getElementById('customPresetModal').style.display='flex'">
                ➕ Save Current as Custom Preset
            </button>
        </div>

        <div style="display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center;">
            @php $allPresets = $presets ?? \App\SPPDocs\Services\FeatureManager::getAllPresets(); @endphp
            @foreach($allPresets as $pKey => $pDef)
                <div style="display: inline-flex; align-items: center; background: var(--adm-bg-soft, #f8fafc); border: 1px solid var(--adm-border, #e2e8f0); border-radius: 8px; padding: 0.35rem 0.6rem; gap: 0.4rem;">
                    <button type="button" 
                            onclick="applyPreset('{{ $pKey }}')" 
                            class="adm-btn adm-btn-secondary adm-btn-sm" 
                            style="border: none; background: transparent; padding: 0.1rem 0.3rem; font-size: 0.82rem; font-weight: 600; cursor: pointer; color: var(--adm-text-main, #0f172a);"
                            title="{{ $pDef['description'] ?? '' }}">
                        {{ $pDef['icon'] ?? '⚙️' }} {{ $pDef['title'] ?? ucfirst($pKey) }}
                    </button>
                    @if(!empty($pDef['is_custom']))
                        <form action="@url('admin/project/preset/delete')" method="POST" onsubmit="return confirm('Delete preset \'{{ $pDef['title'] ?? $pKey }}\'?');" style="margin: 0; display: inline;">
                            <input type="hidden" name="project_id" value="{{ $project_id }}">
                            <input type="hidden" name="preset_key" value="{{ $pKey }}">
                            <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 0.8rem; padding: 0 0.2rem;" title="Delete custom preset">&times;</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
        <div id="presetNotice" style="display: none; margin-top: 0.75rem; padding: 0.5rem 0.75rem; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; font-size: 0.82rem; color: #1d4ed8;"></div>
    </div>

    <!-- Main Features Form -->
    <form id="featuresForm" action="@url('admin/project/save-features')" method="POST">
        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
        <input type="hidden" name="project_id" value="{{ $project_id }}">

        @php
            $catalog = $feature_catalog ?? \App\SPPDocs\Services\FeatureManager::getFeatureCatalog();
            $currFeatures = $project['features'] ?? [];
        @endphp

        @foreach($catalog as $groupId => $group)
            <div class="adm-card" style="margin-bottom: 1.5rem; background: var(--adm-card-bg, #ffffff); border: 1px solid var(--adm-border, #e2e8f0);">
                <div class="adm-card-header" style="border-bottom: 1px solid var(--adm-border, #e2e8f0); padding-bottom: 0.75rem; margin-bottom: 1.25rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 1.25rem;">{{ $group['icon'] ?? '📦' }}</span>
                        <div>
                            <h2 class="adm-card-title" style="margin: 0; font-size: 1.1rem; color: var(--adm-text-main, #0f172a);">
                                {{ $group['title'] }}
                            </h2>
                            <p class="adm-form-desc" style="margin: 0.15rem 0 0 0; font-size: 0.82rem; color: var(--adm-text-muted, #64748b);">
                                {{ $group['description'] }}
                            </p>
                        </div>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    @foreach($group['features'] as $fKey => $fDef)
                        @php
                            $isEnabled = false;
                            if (isset($currFeatures[$fKey])) {
                                if (is_bool($currFeatures[$fKey])) {
                                    $isEnabled = $currFeatures[$fKey];
                                } elseif (is_array($currFeatures[$fKey])) {
                                    $isEnabled = !empty($currFeatures[$fKey]['enabled']);
                                }
                            } else {
                                $isEnabled = !empty($fDef['default']);
                            }
                        @endphp

                        <div class="adm-toggle-row" style="padding: 0.75rem; border-radius: 8px; background: var(--adm-bg-soft, #f8fafc); border: 1px solid var(--adm-border, #e2e8f0);">
                            <div class="adm-toggle-info" style="flex: 1;">
                                <div class="adm-toggle-title" style="display: flex; align-items: center; gap: 0.4rem; font-weight: 600; color: var(--adm-text-main, #0f172a);">
                                    <span>{{ $fDef['icon'] ?? '🔹' }}</span>
                                    <span>{{ $fDef['name'] }}</span>
                                    @if(!empty($fDef['required']))
                                        <span style="font-size: 0.7rem; background: #e0e7ff; color: #3730a3; padding: 0.1rem 0.35rem; border-radius: 4px; font-weight: 700;">Core Required</span>
                                    @endif
                                </div>
                                <div class="adm-toggle-desc" style="font-size: 0.82rem; color: var(--adm-text-muted, #64748b); margin-top: 0.2rem;">
                                    {{ $fDef['desc'] }}
                                </div>

                                {{-- Driver Configuration for Issues --}}
                                @if($fKey === 'issues')
                                    <div style="margin-top: 0.6rem; display: flex; gap: 0.5rem; align-items: center;">
                                        <span style="font-size: 0.8rem; font-weight: 600; color: var(--adm-text-muted, #64748b);">Tracker Driver:</span>
                                        <select name="issue_driver" class="adm-form-select" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.82rem; border-radius: 4px; border: 1px solid var(--adm-border, #e2e8f0); background: var(--adm-input-bg, #ffffff);">
                                            <option value="internal" {{ ($currFeatures['issues']['driver'] ?? 'internal') === 'internal' ? 'selected' : '' }}>Native Internal Tracker</option>
                                            <option value="github" {{ ($currFeatures['issues']['driver'] ?? '') === 'github' ? 'selected' : '' }}>GitHub Issues (Mirror / Sync)</option>
                                            <option value="external" {{ ($currFeatures['issues']['driver'] ?? '') === 'external' ? 'selected' : '' }}>External Outsourced (Jira/Linear Link)</option>
                                        </select>
                                    </div>
                                @endif

                                {{-- Driver Configuration for Forums --}}
                                @if($fKey === 'forums')
                                    <div style="margin-top: 0.6rem; display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                                            <span style="font-size: 0.8rem; font-weight: 600; color: var(--adm-text-muted, #64748b);">Forum Driver:</span>
                                            <select name="forum_driver" class="adm-form-select" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.82rem; border-radius: 4px; border: 1px solid var(--adm-border, #e2e8f0); background: var(--adm-input-bg, #ffffff);">
                                                <option value="internal" {{ ($currFeatures['forums']['driver'] ?? 'internal') === 'internal' ? 'selected' : '' }}>Native Internal Forum</option>
                                                <option value="external" {{ ($currFeatures['forums']['driver'] ?? '') === 'external' ? 'selected' : '' }}>External Outsourced (Discourse/Discord)</option>
                                            </select>
                                        </div>
                                        <label style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.82rem; cursor: pointer; color: var(--adm-text-main, #0f172a);">
                                            <input type="checkbox" name="forums_guest_posting" value="1" {{ !empty($project['forums_guest_posting']) ? 'checked' : '' }}>
                                            Allow Guest Posting
                                        </label>
                                    </div>
                                @endif
                            </div>

                            <label class="adm-switch">
                                @if(!empty($fDef['required']))
                                    <input type="hidden" name="features[{{ $fKey }}]" value="1">
                                    <input type="checkbox" checked disabled id="toggle_{{ $fKey }}">
                                @elseif($fKey === 'issues')
                                    <input type="checkbox" name="features[issues][enabled]" value="1" id="toggle_{{ $fKey }}" {{ $isEnabled ? 'checked' : '' }}>
                                @elseif($fKey === 'forums')
                                    <input type="checkbox" name="features[forums][enabled]" value="1" id="toggle_{{ $fKey }}" {{ $isEnabled ? 'checked' : '' }}>
                                @else
                                    <input type="checkbox" name="features[{{ $fKey }}]" value="1" id="toggle_{{ $fKey }}" {{ $isEnabled ? 'checked' : '' }}>
                                @endif
                                <span class="adm-slider"></span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem; position: sticky; bottom: 1rem; z-index: 100; background: var(--adm-card-bg, #ffffff); padding: 1rem; border-radius: 8px; border: 1px solid var(--adm-border, #e2e8f0); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <button type="submit" class="adm-btn adm-btn-primary" style="padding: 0.6rem 1.5rem; font-size: 0.95rem;">
                💾 Save Feature Configuration
            </button>
        </div>
    </form>
</div>

<!-- Modal: Save Current Toggles as Custom Preset -->
<div id="customPresetModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 10000; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: var(--adm-card-bg, #ffffff); border-radius: 10px; width: 500px; max-width: 90vw; padding: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); border: 1px solid var(--adm-border, #e2e8f0);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--adm-border, #e2e8f0); padding-bottom: 0.5rem;">
            <h3 style="margin: 0; font-size: 1.15rem; color: var(--adm-text-main, #0f172a);">
                💾 Save Custom Feature Preset
            </h3>
            <button type="button" onclick="document.getElementById('customPresetModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--adm-text-muted, #64748b);">&times;</button>
        </div>

        <form action="@url('admin/project/preset/save')" method="POST" onsubmit="return syncCustomPresetFeatures(this);">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
            <input type="hidden" name="project_id" value="{{ $project_id }}">
            <div id="presetFeaturesContainer"></div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--adm-text-main, #0f172a);">Preset Title</label>
                <input type="text" name="preset_title" required placeholder="e.g. My Team Workflow" style="width: 100%; box-sizing: border-box; padding: 0.45rem 0.6rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 6px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a);">
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--adm-text-main, #0f172a);">Icon Emoji</label>
                <input type="text" name="preset_icon" value="⚡" style="width: 80px; box-sizing: border-box; padding: 0.45rem 0.6rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 6px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a); text-align: center; font-size: 1.1rem;">
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--adm-text-main, #0f172a);">Description</label>
                <textarea name="preset_desc" rows="2" placeholder="Brief summary of when to use this preset..." style="width: 100%; box-sizing: border-box; padding: 0.45rem 0.6rem; border: 1px solid var(--adm-border, #e2e8f0); border-radius: 6px; background: var(--adm-input-bg, #ffffff); color: var(--adm-text-main, #0f172a);"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="adm-btn adm-btn-secondary" onclick="document.getElementById('customPresetModal').style.display='none'">Cancel</button>
                <button type="submit" class="adm-btn adm-btn-primary">Save Preset</button>
            </div>
        </form>
    </div>
</div>

<script>
const PRESETS_DATA = <?php echo json_encode($allPresets, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function applyPreset(presetKey) {
    const preset = PRESETS_DATA[presetKey];
    if (!preset || !preset.features) return;

    for (const [feat, val] of Object.entries(preset.features)) {
        const toggle = document.getElementById('toggle_' + feat);
        if (toggle && !toggle.disabled) {
            toggle.checked = Boolean(val);
        }
    }

    const notice = document.getElementById('presetNotice');
    if (notice) {
        notice.style.display = 'block';
        notice.innerHTML = `✓ Applied <strong>${preset.title || presetKey}</strong> preset. Review the toggles below and click <strong>Save Feature Configuration</strong> to persist.`;
        notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function syncCustomPresetFeatures(form) {
    const container = document.getElementById('presetFeaturesContainer');
    container.innerHTML = '';
    const mainForm = document.getElementById('featuresForm');
    const inputs = mainForm.querySelectorAll('input[type="checkbox"]');
    inputs.forEach(input => {
        if (input.checked && input.id && input.id.startsWith('toggle_')) {
            const featName = input.id.replace('toggle_', '');
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = `features[${featName}]`;
            hidden.value = '1';
            container.appendChild(hidden);
        }
    });
    return true;
}
</script>

<!-- Tab 3: Storage Engine & Live Migration -->
<div id="tab-storage" class="adm-tab-content" style="{{ $currentTab === 'tab-storage' ? 'display: block;' : 'display: none;' }}">
    <form action="@url('admin/project/save-storage')" method="POST">
        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
        <input type="hidden" name="project_id" value="{{ $project_id }}">

        <div class="adm-card">
            <div class="adm-card-header">
                <h2 class="adm-card-title">Hybrid Storage Architecture</h2>
            </div>
            
            <?php 
                $currentStorage = $project['storage'] ?? 'json'; 
                $dbConfig = $project['db_config'] ?? [];
            ?>

            <div class="adm-storage-grid">
                <!-- Flat-File JSON -->
                <div id="storage-card-json" class="adm-storage-card {{ $currentStorage === 'json' ? 'adm-storage-card-active' : '' }}" onclick="selectStorageCard('json')">
                    <div class="adm-storage-header">
                        <h3 class="adm-storage-title">📄 Flat-File JSON</h3>
                        @if($currentStorage === 'json')
                            <span class="adm-badge adm-badge-success">Active</span>
                        @endif
                    </div>
                    <p class="adm-storage-desc">
                        Stores issues and milestones as individual human-readable JSON files with an incremental <code>index.json</code> cache. Zero external dependencies.
                    </p>
                    <label class="adm-storage-label" onclick="event.stopPropagation()">
                        <input type="radio" class="adm-storage-radio" name="target_storage" value="json" {{ $currentStorage === 'json' ? 'checked' : '' }} onchange="selectStorageCard('json')">
                        Select JSON Storage
                    </label>
                </div>

                <!-- SQLite WAL -->
                <div id="storage-card-sqlite" class="adm-storage-card {{ $currentStorage === 'sqlite' ? 'adm-storage-card-active' : '' }}" onclick="selectStorageCard('sqlite')">
                    <div class="adm-storage-header">
                        <h3 class="adm-storage-title">🗄️ Embedded SQLite (WAL)</h3>
                        @if($currentStorage === 'sqlite')
                            <span class="adm-badge adm-badge-success">Active</span>
                        @endif
                    </div>
                    <p class="adm-storage-desc">
                        Embedded ACID database with Write-Ahead Logging (<code>WAL</code>) and B-Tree indexes. High performance with zero DB server setup.
                    </p>
                    <label class="adm-storage-label" onclick="event.stopPropagation()">
                        <input type="radio" class="adm-storage-radio" name="target_storage" value="sqlite" {{ $currentStorage === 'sqlite' ? 'checked' : '' }} onchange="selectStorageCard('sqlite')">
                        Select SQLite Storage
                    </label>
                </div>

                <!-- MySQL / MariaDB -->
                <div id="storage-card-mysql" class="adm-storage-card {{ in_array($currentStorage, ['mysql', 'mariadb']) ? 'adm-storage-card-active' : '' }}" onclick="selectStorageCard('mysql', '3306')">
                    <div class="adm-storage-header">
                        <h3 class="adm-storage-title">🐬 MySQL / MariaDB</h3>
                        @if(in_array($currentStorage, ['mysql', 'mariadb']))
                            <span class="adm-badge adm-badge-success">Active</span>
                        @endif
                    </div>
                    <p class="adm-storage-desc">
                        Enterprise relational engine with row-level transaction locks (<code>FOR UPDATE</code>) and full-text indexing. Perfect for team concurrency.
                    </p>
                    <label class="adm-storage-label" onclick="event.stopPropagation()">
                        <input type="radio" class="adm-storage-radio" name="target_storage" value="mysql" {{ in_array($currentStorage, ['mysql', 'mariadb']) ? 'checked' : '' }} onchange="selectStorageCard('mysql', '3306')">
                        Select MySQL Storage
                    </label>
                </div>

                <!-- PostgreSQL -->
                <div id="storage-card-pgsql" class="adm-storage-card {{ in_array($currentStorage, ['pgsql', 'postgres']) ? 'adm-storage-card-active' : '' }}" onclick="selectStorageCard('pgsql', '5432')">
                    <div class="adm-storage-header">
                        <h3 class="adm-storage-title">🐘 PostgreSQL</h3>
                        @if(in_array($currentStorage, ['pgsql', 'postgres']))
                            <span class="adm-badge adm-badge-success">Active</span>
                        @endif
                    </div>
                    <p class="adm-storage-desc">
                        Advanced open-source relational database with ACID transactions, JSONB indexing, and high-throughput connection pooling.
                    </p>
                    <label class="adm-storage-label" onclick="event.stopPropagation()">
                        <input type="radio" class="adm-storage-radio" name="target_storage" value="pgsql" {{ in_array($currentStorage, ['pgsql', 'postgres']) ? 'checked' : '' }} onchange="selectStorageCard('pgsql', '5432')">
                        Select PostgreSQL
                    </label>
                </div>
            </div>

            <!-- Database Configuration Panel -->
            <div id="adm-db-config-panel" class="adm-db-config-box" style="{{ in_array($currentStorage, ['mysql', 'mariadb', 'pgsql', 'postgres']) ? '' : 'display: none;' }}">
                <div class="adm-db-config-header">
                    <div>
                        <div class="adm-db-config-title">
                            <span class="adm-db-config-title-icon">⚙️</span>
                            <span>Relational Database Credentials & Parameters</span>
                        </div>
                        <div class="adm-db-config-subtitle">
                            Configure connection parameters for MySQL, MariaDB, or PostgreSQL storage engine.
                        </div>
                    </div>
                    <div class="adm-db-badge-secure">
                        <span>🔒</span> Credential Isolation Active
                    </div>
                </div>

                <div class="adm-db-config-grid">
                    <div class="adm-form-group adm-db-col-4">
                        <label class="adm-form-label">
                            <span class="adm-field-icon">🌐</span> Database Host
                        </label>
                        <input type="text" name="db_host" class="adm-form-input" value="{{ $dbConfig['host'] ?? 'localhost' }}" placeholder="localhost or 127.0.0.1">
                        <div class="adm-form-desc">IP address or hostname of DB server</div>
                    </div>
                    <div class="adm-form-group adm-db-col-2">
                        <label class="adm-form-label">
                            <span class="adm-field-icon">🔌</span> Port
                        </label>
                        <input type="number" id="db_port_input" name="db_port" class="adm-form-input" value="{{ $dbConfig['port'] ?? ($currentStorage === 'pgsql' ? '5432' : '3306') }}" placeholder="3306">
                        <div class="adm-form-desc">Default: 3306 / 5432</div>
                    </div>
                    <div class="adm-form-group adm-db-col-6">
                        <label class="adm-form-label">
                            <span class="adm-field-icon">📁</span> Database Name
                        </label>
                        <input type="text" name="db_name" class="adm-form-input" value="{{ $dbConfig['database'] ?? 'sppdocs' }}" placeholder="e.g. sppdocs">
                        <div class="adm-form-desc">Target database name on server</div>
                    </div>
                    <div class="adm-form-group adm-db-col-4">
                        <label class="adm-form-label">
                            <span class="adm-field-icon">👤</span> Username
                        </label>
                        <input type="text" name="db_user" class="adm-form-input" value="{{ $dbConfig['username'] ?? 'root' }}" placeholder="root">
                        <div class="adm-form-desc">Database user with DDL & DML permissions</div>
                    </div>
                    <div class="adm-form-group adm-db-col-4">
                        <label class="adm-form-label">
                            <span class="adm-field-icon">🔑</span> Password
                        </label>
                        <div class="adm-input-password-wrapper">
                            <input type="password" id="db_pass_input" name="db_pass" class="adm-form-input" value="{{ $dbConfig['password'] ?? '' }}" placeholder="••••••••">
                            <button type="button" class="adm-pwd-toggle-btn" onclick="togglePasswordVisibility('db_pass_input', this)" title="Show/Hide Password">👁️</button>
                        </div>
                        <div class="adm-form-desc">Database user authentication password</div>
                    </div>
                    <div class="adm-form-group adm-db-col-4">
                        <label class="adm-form-label">
                            <span class="adm-field-icon">🏷️</span> Table Prefix
                        </label>
                        <input type="text" name="table_prefix" class="adm-form-input" value="{{ $dbConfig['table_prefix'] ?? 'sppdocs_' }}" placeholder="sppdocs_">
                        <div class="adm-form-desc">Prefix to isolate tables (e.g. sppdocs_)</div>
                    </div>
                </div>
            </div>

            <div class="adm-alert adm-alert-info">
                <span>🔄</span>
                <div>
                    <strong>Zero Data-Loss Live 3-Way Migration:</strong>
                    Switching between Flat-File JSON, SQLite, and MySQL/PostgreSQL automatically migrates all issues, comments, milestones, and metadata into the target engine instantly without downtime.
                </div>
            </div>

            <div class="adm-form-actions">
                <button type="submit" class="adm-btn adm-btn-primary">💾 Apply Storage Engine & Migrate Data</button>
            </div>
        </div>
    </form>
</div>

<!-- Tab 4: External Links & Repo -->
<div id="tab-links" class="adm-tab-content" style="{{ $currentTab === 'tab-links' ? 'display: block;' : 'display: none;' }}">
    <form action="@url('admin/project/save-links')" method="POST">
        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
        <input type="hidden" name="project_id" value="{{ $project_id }}">

        <div class="adm-card">
            <div class="adm-card-header">
                <h2 class="adm-card-title">External Resources & Repository Integration</h2>
            </div>
            <p class="adm-form-desc">Configure outbound web links. Links missing protocol schemes will be automatically normalized with <code>https://</code> by the framework URL engine.</p>

            <div class="adm-form-grid">
                <div class="adm-form-group">
                    <label class="adm-form-label">Official Website URL</label>
                    <input type="text" name="links[website]" value="{{ $project['links']['website'] ?? '' }}" placeholder="https://example.com" class="adm-form-input">
                </div>

                <div class="adm-form-group">
                    <label class="adm-form-label">Source Code / GitHub Repository</label>
                    <input type="text" name="links[source]" value="{{ $project['links']['source'] ?? '' }}" placeholder="https://github.com/org/repo" class="adm-form-input">
                </div>

                <div class="adm-form-group">
                    <label class="adm-form-label">Community / Forum URL</label>
                    <input type="text" name="links[forum]" value="{{ $project['links']['forum'] ?? '' }}" placeholder="/school1/sppdocs/forums?projectId={{ $project_id }} or https://discourse.example.com" class="adm-form-input">
                </div>

                <div class="adm-form-group">
                    <label class="adm-form-label">Issue Tracker URL</label>
                    <input type="text" name="links[issues]" value="{{ $project['links']['issues'] ?? '' }}" placeholder="/school1/sppdocs/issues?projectId={{ $project_id }} or https://linear.app/..." class="adm-form-input">
                </div>
            </div>

            <div class="adm-section-divider"></div>

            <div class="adm-links-header">
                <div>
                    <h3 class="adm-section-title">Additional External Links</h3>
                    <p class="adm-form-desc">Configure arbitrary outbound links (such as Discord, Slack, API reference, Docker Hub, Package Registry) displayed across the project sidebar, home page, and top bar.</p>
                </div>
                <button type="button" class="adm-btn adm-btn-secondary" onclick="addCustomLinkRow()">➕ Add Link</button>
            </div>

            <div class="adm-table-wrap">
                <table class="adm-table custom-links-table">
                    <thead>
                        <tr>
                            <th class="col-icon">Icon</th>
                            <th class="col-title">Link Title / Label</th>
                            <th class="col-url">Target URL</th>
                            <th class="col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody id="custom-links-tbody">
                        @if(!empty($project['links']['custom']) && is_array($project['links']['custom']))
                            @foreach($project['links']['custom'] as $clink)
                                <tr class="custom-link-row">
                                    <td class="col-icon">
                                        <input type="text" name="custom_links[icon][]" value="{{ $clink['icon'] ?? '🔗' }}" class="adm-form-input custom-link-icon-input" placeholder="🔗" maxlength="4">
                                    </td>
                                    <td class="col-title">
                                        <input type="text" name="custom_links[title][]" value="{{ $clink['title'] ?? '' }}" class="adm-form-input" placeholder="e.g. Discord Community" required>
                                    </td>
                                    <td class="col-url">
                                        <input type="text" name="custom_links[url][]" value="{{ $clink['url'] ?? '' }}" class="adm-form-input" placeholder="https://..." required>
                                    </td>
                                    <td class="col-action">
                                        <button type="button" class="adm-btn adm-btn-danger adm-btn-sm" onclick="this.closest('tr').remove();">Remove</button>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>

            <div class="adm-card-footer">
                <button type="submit" class="adm-btn adm-btn-primary">💾 Save Resource Links</button>
            </div>
        </div>
    </form>
</div>

<!-- Template for Dynamic Custom Link Rows -->
<template id="custom-link-row-template">
    <tr class="custom-link-row">
        <td class="col-icon">
            <input type="text" name="custom_links[icon][]" value="🔗" class="adm-form-input custom-link-icon-input" placeholder="🔗" maxlength="4">
        </td>
        <td class="col-title">
            <input type="text" name="custom_links[title][]" value="" class="adm-form-input" placeholder="e.g. Discord Community, API Docs" required>
        </td>
        <td class="col-url">
            <input type="text" name="custom_links[url][]" value="" class="adm-form-input" placeholder="https://..." required>
        </td>
        <td class="col-action">
            <button type="button" class="adm-btn adm-btn-danger adm-btn-sm" onclick="this.closest('tr').remove();">Remove</button>
        </td>
    </tr>
</template>

<!-- Tab 5: Team & RBAC Rights -->
<div id="tab-team" class="adm-tab-content" style="{{ $currentTab === 'tab-team' ? 'display: block;' : 'display: none;' }}">
    <form action="@url('admin/project/save-team')" method="POST">
        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
        <input type="hidden" name="project_id" value="{{ $project_id }}">

        <div class="adm-card">
            <?php $allDefinedRoles = \App\SPPDocs\Services\PermissionManager::getAllRoles(); ?>
            <div class="adm-card-header adm-card-header-spaced">
                <h2 class="adm-card-title">Project Team Members & Roles ({{ count($members ?? []) }})</h2>
                <a href="@url('admin/roles?projectId=' . $project_id)" class="adm-btn adm-btn-secondary adm-btn-sm">🛡️ Manage Custom Roles & Rights &rarr;</a>
            </div>
            <p class="adm-form-desc adm-mb-4">Assign project-level roles to registered users. Global Super Admins inherit full permissions across all projects automatically.</p>

            <div class="adm-table-wrap">
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th class="adm-w-160">User</th>
                            <th>Assigned Project Roles (Multi-Role)</th>
                            <th class="adm-w-260">Effective Capabilities</th>
                            <th class="text-right adm-w-90">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($members as $m)
                        <tr>
                            <td>
                                <strong>{{ $m['username'] }}</strong>
                                @if($m['is_global'])
                                    <span class="adm-badge adm-badge-admin" style="display: block; width: fit-content; margin-top: 0.25rem;">Global Admin</span>
                                @endif
                            </td>
                            <td>
                                @if($m['is_global'])
                                    <span class="adm-badge adm-badge-admin">Admin (Inherited - All Projects)</span>
                                @else
                                    @php
                                        $userRoles = $m['roles'] ?? (array)($m['role'] ?? []);
                                    @endphp
                                    <div class="adm-assign-role-pills">
                                        @foreach($allDefinedRoles as $rId => $rDef)
                                            @php
                                                $isSystem = !empty($rDef['is_system']);
                                                $tagVariant = 'adm-role-tag-' . ($isSystem ? $rId : 'custom');
                                                $isChecked = in_array($rId, $userRoles, true);
                                            @endphp
                                            <label class="adm-role-tag {{ $tagVariant }}" title="{{ $rDef['description'] ?? '' }}">
                                                <input type="checkbox" name="user_roles[{{ $m['username'] }}][]" value="{{ $rId }}" {{ $isChecked ? 'checked' : '' }}>
                                                <span class="adm-role-tag-inner">
                                                    <span class="adm-role-tag-check-icon">✓</span>
                                                    <span>{{ $isSystem ? '' : '✨ ' }}{{ $rDef['name'] }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($m['is_global'])
                                    <span style="font-size: 0.82rem; color: var(--adm-text-muted);">Unrestricted access across all documentation, issues, milestones, releases, and settings.</span>
                                @else
                                    <span style="font-size: 0.82rem; color: var(--adm-text-muted);">Cumulative permission union across all selected roles.</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                @if(!$m['is_global'])
                                    <button type="button" class="adm-btn adm-btn-danger adm-btn-sm" onclick="this.closest('tr').remove();">Remove</button>
                                @else
                                    <span style="font-size: 0.8rem; color: var(--adm-text-sub);">Locked</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Add Member Form with Multi-Role Selectors -->
            <div style="margin-top: 1.5rem; padding: 1.25rem; background: var(--adm-bg); border: 1px solid var(--adm-border); border-radius: 8px;">
                <h4 style="margin: 0 0 0.85rem 0; font-size: 0.95rem; color: var(--adm-text-main);">➕ Add Team Member with Multiple Roles</h4>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div style="max-width: 400px;">
                        <label class="adm-form-label">Select Registered User</label>
                        <select id="newMemberUser" class="adm-form-select">
                            <option value="">Select user...</option>
                            @foreach($registered_users as $ru)
                                <option value="{{ is_array($ru) ? $ru['username'] : $ru }}">{{ is_array($ru) ? ($ru['display_name'] ?? $ru['username']) . ' (@' . $ru['username'] . ')' : $ru }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="adm-form-label">Select Roles to Assign</label>
                        <div class="adm-assign-role-pills" id="newMemberRolePills">
                            @foreach($allDefinedRoles as $rId => $rDef)
                                @php
                                    $isSystem = !empty($rDef['is_system']);
                                    $tagVariant = 'adm-role-tag-' . ($isSystem ? $rId : 'custom');
                                @endphp
                                <label class="adm-role-tag {{ $tagVariant }}" title="{{ $rDef['description'] ?? '' }}">
                                    <input type="checkbox" value="{{ $rId }}" class="new-member-role-cb" {{ $rId === 'developer' ? 'checked' : '' }}>
                                    <span class="adm-role-tag-inner">
                                        <span class="adm-role-tag-check-icon">✓</span>
                                        <span>{{ $isSystem ? '' : '✨ ' }}{{ $rDef['name'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <button type="button" class="adm-btn adm-btn-secondary" onclick="addProjectMemberRow()">➕ Add Member to Table</button>
                    </div>
                </div>
            </div>

            <div class="adm-form-actions">
                <button type="submit" class="adm-btn adm-btn-primary">💾 Save Team & Roles</button>
            </div>
        </div>
    </form>
</div>

<!-- Tab 6: Releases & Downloads -->
<div id="tab-releases" class="adm-tab-content" style="{{ $currentTab === 'tab-releases' ? 'display: block;' : 'display: none;' }}">
    <form action="@url('admin/project/save-releases')" method="POST">
        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
        <input type="hidden" name="project_id" value="{{ $project_id }}">

        <div class="adm-card">
            <div class="adm-card-header">
                <h2 class="adm-card-title">Software Releases & Downloads</h2>
            </div>
            <p class="adm-form-desc" style="margin-bottom: 1.5rem;">Manage published release artifacts, version numbers, and changelog notes.</p>

            <div id="releasesContainer">
                @forelse($project['releases'] ?? [] as $index => $rel)
                <div class="adm-form-grid" style="padding: 1rem; background: var(--adm-bg); border-radius: 8px; margin-bottom: 1rem; position: relative;">
                    <div class="adm-form-group">
                        <label class="adm-form-label">Version Tag</label>
                        <input type="text" name="releases[{{ $index }}][version]" value="{{ $rel['version'] ?? '' }}" class="adm-form-input" required>
                    </div>
                    <div class="adm-form-group">
                        <label class="adm-form-label">Release Title</label>
                        <input type="text" name="releases[{{ $index }}][title]" value="{{ $rel['title'] ?? '' }}" class="adm-form-input">
                    </div>
                    <div class="adm-form-group">
                        <label class="adm-form-label">Download / Artifact URL</label>
                        <input type="text" name="releases[{{ $index }}][url]" value="{{ $rel['url'] ?? '' }}" class="adm-form-input">
                    </div>
                    <div class="adm-form-group">
                        <label class="adm-form-label">Changelog Notes</label>
                        <input type="text" name="releases[{{ $index }}][notes]" value="{{ $rel['notes'] ?? '' }}" class="adm-form-input">
                    </div>
                </div>
                @empty
                <p style="color: var(--adm-text-muted); margin-bottom: 1rem;">No release versions configured yet.</p>
                @endforelse
            </div>

            <button type="button" class="adm-btn adm-btn-secondary" onclick="addReleaseRow()">➕ Add Release Version</button>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="submit" class="adm-btn adm-btn-primary">💾 Save Releases</button>
            </div>
        </div>
    </form>
</div>

<!-- Tab 7: Danger Zone -->
<div id="tab-danger" class="adm-tab-content" style="{{ $currentTab === 'tab-danger' ? 'display: block;' : 'display: none;' }}">
    <div class="adm-card" style="border-color: #fca5a5;">
        <div class="adm-card-header" style="border-bottom-color: #fee2e2;">
            <h2 class="adm-card-title" style="color: #dc2626;">⚠️ Danger Zone</h2>
        </div>
        <p style="color: var(--adm-text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
            Deleting this project will deregister it from the platform and archive its data. This operation requires Global Super Administrator privileges.
        </p>
        <form action="@url('admin/project/delete')" method="POST" onsubmit="return confirm('Are you absolutely sure you want to delete this project? This cannot be undone.');">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
            <input type="hidden" name="project_id" value="{{ $project_id }}">
            <button type="submit" class="adm-btn adm-btn-danger">🗑️ Permanently Delete Project</button>
        </form>
    </div>
</div>

<template id="team-member-row-template">
    <tr>
        <td><strong class="member-user-text"></strong></td>
        <td>
            <select class="adm-form-select member-role-select">
                @foreach($allDefinedRoles as $rId => $rDef)
                    @if($rId !== 'guest')
                        <option value="{{ $rId }}" {{ $rId === 'developer' ? 'selected' : '' }}>{{ $rDef['name'] }}</option>
                    @endif
                @endforeach
            </select>
        </td>
        <td><span class="member-assigned-tag">Assigned</span></td>
        <td style="text-align: right;"><button type="button" class="adm-btn adm-btn-danger adm-btn-sm" onclick="this.closest('tr').remove();">Remove</button></td>
    </tr>
</template>

<template id="release-card-template">
    <div class="adm-form-grid release-row-grid">
        <div class="adm-form-group">
            <label class="adm-form-label">Version Tag</label>
            <input type="text" placeholder="v1.1.0" class="adm-form-input release-version-input" required>
        </div>
        <div class="adm-form-group">
            <label class="adm-form-label">Release Title</label>
            <input type="text" placeholder="Summer Update" class="adm-form-input release-title-input">
        </div>
        <div class="adm-form-group">
            <label class="adm-form-label">Download / Artifact URL</label>
            <input type="text" placeholder="https://..." class="adm-form-input release-url-input">
        </div>
        <div class="adm-form-group">
            <label class="adm-form-label">Changelog Notes</label>
            <input type="text" placeholder="Key features" class="adm-form-input release-notes-input">
        </div>
    </div>
</template>
@endsection

@section('scripts')
<script>
function switchProjectTab(tabId) {
    if (!tabId) return;
    if (!tabId.startsWith('tab-')) {
        tabId = 'tab-' + tabId;
    }
    const pane = document.getElementById(tabId);
    if (!pane) return;

    document.querySelectorAll('#projectTabs .adm-tab-item').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.adm-tab-content').forEach(el => el.style.display = 'none');

    const activeBtn = document.querySelector(`#projectTabs .adm-tab-item[data-tab="${tabId}"]`)
        || Array.from(document.querySelectorAll('#projectTabs .adm-tab-item')).find(b => (b.getAttribute('onclick') || '').includes(tabId));

    if (activeBtn) activeBtn.classList.add('active');
    pane.style.display = 'block';

    if (window.history && window.history.replaceState) {
        window.history.replaceState(null, null, '#' + tabId);
    }
}

function selectStorageCard(storageType, port) {
    const radio = document.querySelector(`input[name="target_storage"][value="${storageType}"]`);
    if (radio) {
        radio.checked = true;
    }
    document.querySelectorAll('.adm-storage-card').forEach(card => {
        card.classList.remove('adm-storage-card-active');
    });
    const selectedCard = document.getElementById('storage-card-' + storageType);
    if (selectedCard) {
        selectedCard.classList.add('adm-storage-card-active');
    }
    const isRelational = ['mysql', 'mariadb', 'pgsql', 'postgres'].includes(storageType);
    toggleDbConfig(isRelational, port);
}

function toggleDbConfig(show, port) {
    const panel = document.getElementById('adm-db-config-panel');
    if (panel) {
        panel.style.display = show ? 'block' : 'none';
    }
    if (port) {
        const portInput = document.getElementById('db_port_input');
        if (portInput && (!portInput.value || portInput.value === '3306' || portInput.value === '5432')) {
            portInput.value = port;
        }
    }
}

function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙈';
    } else {
        input.type = 'password';
        btn.textContent = '👁️';
    }
}

function addCustomLinkRow() {
    const tpl = document.getElementById('custom-link-row-template');
    if (!tpl) return;
    const clone = tpl.content.cloneNode(true);
    document.getElementById('custom-links-tbody').appendChild(clone);
}

function addProjectMemberRow() {
    const userSelect = document.getElementById('newMemberUser');
    const user = userSelect.value;
    if (!user) { alert('Please select a user.'); return; }

    // Check if user is already present in table
    const existing = document.querySelector(`input[name="user_roles[${user}][]"]`);
    if (existing) {
        alert('User @' + user + ' is already listed in the project team table.');
        return;
    }

    // Collect checked roles
    const checkedRoles = [];
    document.querySelectorAll('#newMemberRolePills input.new-member-role-cb:checked').forEach(cb => {
        checkedRoles.push(cb.value);
    });

    if (checkedRoles.length === 0) {
        checkedRoles.push('developer');
    }

    const tbody = document.querySelector('#tab-team tbody');
    const tr = document.createElement('tr');

    const allRoles = @json($allDefinedRoles ?? []);
    let pillsHtml = '<div class="adm-assign-role-pills">';
    for (const [rId, rDef] of Object.entries(allRoles)) {
        const isSystem = !!rDef.is_system;
        const tagVariant = 'adm-role-tag-' + (isSystem ? rId : 'custom');
        const isChecked = checkedRoles.includes(rId) ? 'checked' : '';
        const iconPrefix = isSystem ? '' : '✨ ';
        pillsHtml += `
            <label class="adm-role-tag ${tagVariant}" title="${rDef.description || ''}">
                <input type="checkbox" name="user_roles[${user}][]" value="${rId}" ${isChecked}>
                <span class="adm-role-tag-inner">
                    <span class="adm-role-tag-check-icon">✓</span>
                    <span>${iconPrefix}${rDef.name}</span>
                </span>
            </label>
        `;
    }
    pillsHtml += '</div>';

    tr.innerHTML = `
        <td><strong>${user}</strong></td>
        <td>${pillsHtml}</td>
        <td><span style="font-size: 0.82rem; color: var(--adm-text-muted);">Cumulative permission union across all selected roles.</span></td>
        <td style="text-align: right;"><button type="button" class="adm-btn adm-btn-danger adm-btn-sm" onclick="this.closest('tr').remove();">Remove</button></td>
    `;

    tbody.appendChild(tr);
    userSelect.value = '';
}

function addReleaseRow() {
    const tpl = document.getElementById('release-card-template');
    if (!tpl) return;
    const clone = tpl.content.cloneNode(true);
    const idx = Date.now();
    clone.querySelector('.release-version-input').name = 'releases[' + idx + '][version]';
    clone.querySelector('.release-title-input').name = 'releases[' + idx + '][title]';
    clone.querySelector('.release-url-input').name = 'releases[' + idx + '][url]';
    clone.querySelector('.release-notes-input').name = 'releases[' + idx + '][notes]';

    document.getElementById('releasesContainer').appendChild(clone);
}

function applyHashTab() {
    const raw = window.location.hash.replace('#', '') || (new URLSearchParams(window.location.search)).get('tab');
    if (raw) {
        const tabId = raw.startsWith('tab-') ? raw : ('tab-' + raw);
        if (document.getElementById(tabId)) {
            switchProjectTab(tabId);
        }
    }
}

if (document.readyState === 'complete' || document.readyState === 'interactive') {
    applyHashTab();
}
window.addEventListener('DOMContentLoaded', applyHashTab);
window.addEventListener('hashchange', applyHashTab);
document.body.addEventListener('htmx:load', applyHashTab);
document.body.addEventListener('htmx:afterSettle', applyHashTab);
</script>
@endsection
