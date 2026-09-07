@extends('layouts.admin')

@section('title', 'Global Platform Settings')

@section('content')
<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">Global Platform Settings</h1>
        <p class="adm-page-desc">Configure platform-wide defaults, authentication modes, and global governance for all projects.</p>
    </div>
</div>

<form action="@url('admin/global-settings/save')" method="POST">
    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">

    <!-- Branding & Identity -->
    <div class="adm-card">
        <div class="adm-card-header">
            <h2 class="adm-card-title">🌐 Platform Identity & Branding</h2>
        </div>
        <div class="adm-form-grid">
            <div class="adm-form-group">
                <label class="adm-form-label">Platform Portal Title</label>
                <div class="adm-form-desc">Global branding title displayed in topbars and page titles.</div>
                <input type="text" name="portal_title" value="{{ $config['portal_title'] ?? 'SatyaLab Developer Portal' }}" class="adm-form-input" required>
            </div>
            <div class="adm-form-group">
                <label class="adm-form-label">Global Logo Path or URL</label>
                <div class="adm-form-desc">Default image asset or external URL for the header logo.</div>
                <input type="text" name="portal_logo" value="{{ $config['portal_logo'] ?? '' }}" placeholder="/school1/sppdocs/assets/logo.jpg" class="adm-form-input">
            </div>
        </div>
    </div>

    <!-- Authentication & Security Mode -->
    <div class="adm-card">
        <div class="adm-card-header">
            <h2 class="adm-card-title">🔐 Authentication & Security Governance</h2>
        </div>
        <div class="adm-form-grid">
            <div class="adm-form-group">
                <label class="adm-form-label">Global Auth Mode</label>
                <div class="adm-form-desc">Select how user credentials and sessions are authenticated.</div>
                <select name="auth_mode" class="adm-form-select">
                    <option value="sppauth" {{ ($config['auth_mode'] ?? '') === 'sppauth' ? 'selected' : '' }}>SPPAuth (Integrated Session & Polyglot Database)</option>
                    <option value="flatfile" {{ ($config['auth_mode'] ?? '') === 'flatfile' ? 'selected' : '' }}>Flat-File Session (Isolated portable session)</option>
                </select>
            </div>
            <div class="adm-form-group">
                <label class="adm-form-label">Default Project Storage Backend</label>
                <div class="adm-form-desc">Storage engine provisioned by default for newly created projects.</div>
                <select name="default_storage" class="adm-form-select">
                    <option value="json" {{ ($config['default_storage'] ?? '') === 'json' ? 'selected' : '' }}>JSON (Indexed flat-file, Zero dependencies)</option>
                    <option value="sqlite" {{ ($config['default_storage'] ?? '') === 'sqlite' ? 'selected' : '' }}>SQLite (Embedded ACID WAL-mode database)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Default Modular Feature Toggles -->
    <div class="adm-card">
        <div class="adm-card-header">
            <h2 class="adm-card-title">🧩 Platform Default Feature Flags</h2>
        </div>
        <p class="adm-form-desc" style="margin-bottom: 1.25rem;">Toggle which enterprise capabilities are enabled by default for all managed projects.</p>

        <div class="adm-toggle-row">
            <div class="adm-toggle-info">
                <div class="adm-toggle-title">Documentation Engine (docs)</div>
                <div class="adm-toggle-desc">Markdown doc viewer, navigation discovery, version switcher, and full-text indexing.</div>
            </div>
            <label class="adm-switch">
                <input type="checkbox" name="features[docs]" value="1" {{ !empty($config['default_features']['docs']) || !isset($config['default_features']['docs']) ? 'checked' : '' }}>
                <span class="adm-slider"></span>
            </label>
        </div>

        <div class="adm-toggle-row">
            <div class="adm-toggle-info">
                <div class="adm-toggle-title">Issue Tracker & Kanban Board (issues)</div>
                <div class="adm-toggle-desc">Linear-grade issue tracking, Kanban drag-and-drop, due dates, and keyboard navigation.</div>
            </div>
            <label class="adm-switch">
                <input type="checkbox" name="features[issues]" value="1" {{ !empty($config['default_features']['issues']) || !isset($config['default_features']['issues']) ? 'checked' : '' }}>
                <span class="adm-slider"></span>
            </label>
        </div>

        <div class="adm-toggle-row">
            <div class="adm-toggle-info">
                <div class="adm-toggle-title">Milestones & Sprint Planning (milestones)</div>
                <div class="adm-toggle-desc">Sprint target dates, progress fraction calculations, and issue linking.</div>
            </div>
            <label class="adm-switch">
                <input type="checkbox" name="features[milestones]" value="1" {{ !empty($config['default_features']['milestones']) || !isset($config['default_features']['milestones']) ? 'checked' : '' }}>
                <span class="adm-slider"></span>
            </label>
        </div>

        <div class="adm-toggle-row">
            <div class="adm-toggle-info">
                <div class="adm-toggle-title">Project Management Dashboard & Roadmap (pm_dashboard)</div>
                <div class="adm-toggle-desc">High-level velocity metrics, priority breakdown, and timeline visualization.</div>
            </div>
            <label class="adm-switch">
                <input type="checkbox" name="features[pm_dashboard]" value="1" {{ !empty($config['default_features']['pm_dashboard']) || !isset($config['default_features']['pm_dashboard']) ? 'checked' : '' }}>
                <span class="adm-slider"></span>
            </label>
        </div>

        <div class="adm-toggle-row">
            <div class="adm-toggle-info">
                <div class="adm-toggle-title">Community Discussions & Forums (forums)</div>
                <div class="adm-toggle-desc">Threaded discussion categories, accepted answers, and emoji reactions.</div>
            </div>
            <label class="adm-switch">
                <input type="checkbox" name="features[forums]" value="1" {{ !empty($config['default_features']['forums']) || !isset($config['default_features']['forums']) ? 'checked' : '' }}>
                <span class="adm-slider"></span>
            </label>
        </div>

        <div class="adm-toggle-row">
            <div class="adm-toggle-info">
                <div class="adm-toggle-title">Git Webhooks & CI/CD Outbox (webhooks)</div>
                <div class="adm-toggle-desc">Inbound smart commit parsing and outbound transactional dispatching.</div>
            </div>
            <label class="adm-switch">
                <input type="checkbox" name="features[webhooks]" value="1" {{ !empty($config['default_features']['webhooks']) || !isset($config['default_features']['webhooks']) ? 'checked' : '' }}>
                <span class="adm-slider"></span>
            </label>
        </div>
    </div>

    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem;">
        <a href="@url('admin')" class="adm-btn adm-btn-secondary">Cancel</a>
        <button type="submit" class="adm-btn adm-btn-primary">💾 Save Global Platform Settings</button>
    </div>
</form>
@endsection
