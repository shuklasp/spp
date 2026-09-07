@extends('layouts.admin')

@section('title', 'Projects Dashboard')

@section('content')
<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">Projects Control Center</h1>
        <p class="adm-page-desc">Overview of all documentation repositories, issue trackers, and team workspaces.</p>
    </div>
    <button type="button" class="adm-btn adm-btn-primary" onclick="document.getElementById('createProjectModal').classList.add('active')">
        ➕ Create New Project
    </button>
</div>

<!-- Stat Cards -->
<div class="adm-stat-grid">
    <div class="adm-stat-card">
        <div>
            <div class="adm-stat-label">Managed Projects</div>
            <div class="adm-stat-val">{{ count($projects) }}</div>
        </div>
        <div class="adm-stat-icon">📁</div>
    </div>
    <div class="adm-stat-card">
        <div>
            <div class="adm-stat-label">Global Super Admins</div>
            <div class="adm-stat-val">{{ count($global_admins ?? ['admin']) }}</div>
        </div>
        <div class="adm-stat-icon">👑</div>
    </div>
    <div class="adm-stat-card">
        <div>
            <div class="adm-stat-label">Registered Accounts</div>
            <div class="adm-stat-val">{{ $total_users ?? 1 }}</div>
        </div>
        <div class="adm-stat-icon">👥</div>
    </div>
    <div class="adm-stat-card">
        <div>
            <div class="adm-stat-label">Platform Engine</div>
            <div class="adm-stat-val" style="font-size: 1.25rem;">SPP v0.7</div>
        </div>
        <div class="adm-stat-icon">⚡</div>
    </div>
</div>

<!-- Projects List -->
<div class="adm-card">
    <div class="adm-card-header">
        <h2 class="adm-card-title">Active Projects</h2>
    </div>

    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Storage Engine</th>
                    <th>Enabled Modules</th>
                    <th>Version</th>
                    <th style="text-align: right;">Operations</th>
                </tr>
            </thead>
            <tbody>
                @forelse($projects as $id => $p)
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            @if(!empty($p['logo']))
                                <img src="{{ $p['logo'] }}" alt="Logo" style="height: 32px; width: 32px; object-fit: contain; border-radius: 6px;" onerror="this.style.display='none'">
                            @else
                                <div style="width: 32px; height: 32px; border-radius: 6px; background: var(--adm-brand-soft); color: var(--adm-brand); display: flex; align-items: center; justify-content: center; font-weight: 700;">
                                    {{ strtoupper(substr($id, 0, 1)) }}
                                </div>
                            @endif
                            <div>
                                <a href="@url('admin/project/' . $id)" style="font-weight: 600; color: var(--adm-text-main); text-decoration: none; font-size: 1rem;">
                                    {{ $p['title'] ?? $id }}
                                </a>
                                <div style="font-size: 0.8rem; color: var(--adm-text-muted);">{{ $p['description'] ?? 'No description provided' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if(($p['storage'] ?? 'json') === 'sqlite')
                            <span class="adm-badge adm-badge-success">🗄️ SQLite (WAL Mode)</span>
                        @else
                            <span class="adm-badge adm-badge-info">📄 JSON (Indexed)</span>
                        @endif
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                            @if(!empty($p['features']['docs']) || !isset($p['features']['docs']))
                                <span class="adm-badge adm-badge-secondary">Docs</span>
                            @endif
                            @if(!empty($p['features']['issues']['enabled']) || !empty($p['features']['issues']))
                                <span class="adm-badge adm-badge-secondary">Issues</span>
                            @endif
                            @if(!empty($p['features']['forums']['enabled']) || !empty($p['features']['forums']))
                                <span class="adm-badge adm-badge-secondary">Forums</span>
                            @endif
                            @if(!empty($p['features']['milestones']))
                                <span class="adm-badge adm-badge-secondary">Milestones</span>
                            @endif
                        </div>
                    </td>
                    <td><code>{{ $p['default_version'] ?? 'v1' }}</code></td>
                    <td style="text-align: right;">
                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                            <a href="@url('admin/project/' . $id)" class="adm-btn adm-btn-secondary adm-btn-sm">
                                ⚙️ Configure
                            </a>
                            <a href="@url('project/' . $id)" target="_blank" class="adm-btn adm-btn-primary adm-btn-sm">
                                View &rarr;
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2rem; color: var(--adm-text-muted);">
                        No projects created yet. Click "+ Create New Project" to get started!
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Create Project Modal -->
<div class="adm-modal" id="createProjectModal">
    <div class="adm-modal-dialog">
        <div class="adm-modal-header">
            <h3 class="adm-modal-title">Create New Documentation Project</h3>
            <button type="button" class="adm-modal-close" onclick="document.getElementById('createProjectModal').classList.remove('active')">&times;</button>
        </div>
        <form action="@url('admin/project/create')" method="POST">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">

            <div class="adm-form-group">
                <label class="adm-form-label">Project Identifier (Slug)</label>
                <input type="text" name="project_id" placeholder="e.g. mobile-app" class="adm-form-input" required pattern="[a-zA-Z0-9\-_]+">
                <div class="adm-form-desc">Unique identifier used in URLs. Alphanumeric and hyphens only.</div>
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Project Title</label>
                <input type="text" name="title" placeholder="e.g. Mobile App Platform" class="adm-form-input" required>
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Short Tagline / Description</label>
                <input type="text" name="description" placeholder="Brief summary of this project" class="adm-form-input">
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Initial Storage Engine</label>
                <select name="storage" class="adm-form-select">
                    <option value="json" selected>JSON (Flat-File, Zero-Config)</option>
                    <option value="sqlite">SQLite (ACID, WAL-Mode Embedded Database)</option>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="button" class="adm-btn adm-btn-secondary" onclick="document.getElementById('createProjectModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="adm-btn adm-btn-primary">Create Project</button>
            </div>
        </form>
    </div>
</div>
@endsection
