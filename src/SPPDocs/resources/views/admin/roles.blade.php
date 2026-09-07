@extends('layouts.admin')

@section('title', !empty($project_id) ? ('Roles & Permissions — ' . ($project['title'] ?? $project_id)) : 'Roles & Permissions Governance')

@section('content')
<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">
            Roles &amp; Permissions Governance
            @if(!empty($project_id))
                <span class="adm-title-project-chip">📁 {{ $project['title'] ?? $project_id }}</span>
            @endif
        </h1>
        <p class="adm-page-desc">
            @if(!empty($project_id))
                Define custom authority roles, configure rights bundles, and assign permissions for project <strong>{{ $project['title'] ?? $project_id }}</strong> (<code>{{ $project_id }}</code>).
            @else
                Define custom authority roles, configure granular rights bundles, and assign permissions across platform resources.
            @endif
        </p>
    </div>
    <button type="button" class="adm-btn adm-btn-primary" onclick="openCreateRoleModal()">
        ➕ Create Custom Role
    </button>
</div>

@if(!empty($project_id))
<div class="adm-project-scope-banner">
    <div class="adm-project-scope-content">
        <div class="adm-project-scope-badge">📁 ACTIVE PROJECT SCOPE</div>
        <h2 class="adm-project-scope-title">{{ $project['title'] ?? $project_id }} <code class="adm-project-scope-id">{{ $project_id }}</code></h2>
        <p class="adm-project-scope-desc">
            You are managing role definitions and member authority specifically for project <strong>{{ $project['title'] ?? $project_id }}</strong>. Custom role schemas are platform-wide, while user assignments made here apply directly to this project.
        </p>
    </div>
    <div class="adm-project-scope-actions">
        <a href="@url('admin/project/' . $project_id . '?tab=team#tab-team')" class="adm-btn adm-btn-secondary adm-btn-sm">👥 Team Roster</a>
        <a href="@url('admin/roles')" class="adm-btn adm-btn-secondary adm-btn-sm" title="View global roles without project filter">🌐 View All Projects</a>
    </div>
</div>
@endif

@if(!empty($_SESSION['adm_flash_success']))
    <div class="adm-alert adm-alert-success">
        <span>✅</span>
        <div>{{ $_SESSION['adm_flash_success'] }}</div>
    </div>
    <?php unset($_SESSION['adm_flash_success']); ?>
@endif

@if(!empty($_SESSION['adm_flash_error']))
    <div class="adm-alert adm-alert-danger">
        <span>⚠️</span>
        <div>{{ $_SESSION['adm_flash_error'] }}</div>
    </div>
    <?php unset($_SESSION['adm_flash_error']); ?>
@endif

<!-- Roles List Card -->
<div class="adm-card">
    <div class="adm-card-header">
        <h2 class="adm-card-title">
            Defined Roles &amp; Access Levels ({{ count($roles) }})
            @if(!empty($project_id))
                <span class="adm-card-subtitle-badge">Filtered: {{ $project['title'] ?? $project_id }}</span>
            @endif
        </h2>
    </div>

    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Role Details</th>
                    <th>Role Type</th>
                    <th>Rights & Permissions</th>
                    <th>Project Usage</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($roles as $roleId => $r)
                <tr>
                    <td>
                        <div class="adm-role-meta">
                            <strong class="adm-role-name">{{ $r['name'] }}</strong>
                            <code class="adm-role-slug">{{ $roleId }}</code>
                        </div>
                        <p class="adm-role-desc">{{ $r['description'] }}</p>
                    </td>
                    <td>
                        @if(!empty($r['is_system']))
                            <span class="adm-badge adm-badge-admin">🔒 System Role</span>
                        @else
                            <span class="adm-badge adm-badge-info">✨ Custom Role</span>
                        @endif
                    </td>
                    <td>
                        @if(in_array('*', $r['permissions'] ?? []))
                            <span class="adm-badge adm-badge-success">👑 Full Authority (*)</span>
                        @else
                            <span class="adm-badge adm-badge-secondary">{{ count($r['permissions'] ?? []) }} Granular Rights</span>
                            <div class="adm-perms-preview">
                                {{ implode(', ', array_slice($r['permissions'] ?? [], 0, 3)) }}
                                @if(count($r['permissions'] ?? []) > 3)
                                    <em>+{{ count($r['permissions']) - 3 }} more</em>
                                @endif
                            </div>
                        @endif
                    </td>
                    <td>
                        @if(!empty($project_id))
                            <button type="button" class="adm-role-member-count-btn" onclick='openRoleMembersModal("{{ $roleId }}", "{{ $r['name'] }}", @json($role_assignments[$roleId] ?? []), "{{ $project_id }}")' title="View assigned members in this project">
                                <span class="adm-badge adm-badge-success">
                                    📁 {{ $role_project_usage[$roleId] ?? 0 }} in project
                                </span>
                                <span class="adm-badge adm-badge-neutral">
                                    👥 {{ $role_usage[$roleId] ?? 0 }} total
                                </span>
                            </button>
                        @else
                            <button type="button" class="adm-role-member-count-btn" onclick='openRoleMembersModal("{{ $roleId }}", "{{ $r['name'] }}", @json($role_assignments[$roleId] ?? []))' title="View assigned members across projects">
                                <span class="adm-badge adm-badge-neutral">
                                    👥 {{ $role_usage[$roleId] ?? 0 }} members
                                </span>
                            </button>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="adm-action-btn-group">
                            <button type="button" class="adm-btn adm-btn-secondary adm-btn-sm" onclick='openAssignUserToRoleModal("{{ $roleId }}", "{{ $r['name'] }}", "{{ $project_id ?? '' }}", "{{ addslashes($project['title'] ?? '') }}")' title="Assign a user to this role">
                                👤+ Assign User
                            </button>
                            <button type="button" class="adm-btn adm-btn-secondary adm-btn-sm" onclick='openEditRoleModal(@json($r))'>
                                ⚙️ Edit Rights
                            </button>
                            @if(empty($r['is_system']))
                                <form action="@url('admin/roles/delete')" method="POST" class="adm-inline-form" onsubmit="return confirm('Are you sure you want to delete the custom role \'{{ $r['name'] }}\'? Any team members with this role will fall back to Developer.');">
                                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                                    <input type="hidden" name="role_id" value="{{ $roleId }}">
                                    <input type="hidden" name="return_project_id" value="{{ $project_id ?? '' }}">
                                    <button type="submit" class="adm-btn adm-btn-danger adm-btn-sm">
                                        🗑️ Delete
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Create / Edit Role -->
<div class="adm-modal" id="roleModal">
    <div class="adm-modal-dialog adm-modal-dialog-large">
        <div class="adm-modal-header">
            <h3 class="adm-modal-title" id="roleModalTitle">Create Custom Role</h3>
            <button type="button" class="adm-modal-close" onclick="closeRoleModal()">&times;</button>
        </div>
        <form action="@url('admin/roles/save')" method="POST" id="roleForm">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">

            <div class="adm-form-grid">
                <div class="adm-form-group adm-db-col-6">
                    <label class="adm-form-label">Role Identifier (Slug)</label>
                    <input type="text" name="role_id" id="roleInputId" class="adm-form-input" placeholder="e.g. qa_engineer" required pattern="[a-zA-Z0-9_\-]+">
                    <div class="adm-form-desc">Alphanumeric, hyphens, and underscores only.</div>
                </div>

                <div class="adm-form-group adm-db-col-6">
                    <label class="adm-form-label">Role Display Name</label>
                    <input type="text" name="name" id="roleInputName" class="adm-form-input" placeholder="e.g. QA Engineer / Tester" required>
                    <div class="adm-form-desc">Human-friendly name shown across team selectors.</div>
                </div>

                <div class="adm-form-group adm-db-col-12">
                    <label class="adm-form-label">Role Scope & Description</label>
                    <input type="text" name="description" id="roleInputDesc" class="adm-form-input" placeholder="Summarize role authority and team responsibility...">
                    <div class="adm-form-desc">Explains what users assigned to this role are permitted to do.</div>
                </div>
            </div>

            <!-- Permission Matrix Selection -->
            <div class="adm-perm-matrix-section">
                <div class="adm-perm-matrix-header">
                    <h4 class="adm-perm-matrix-title">🔑 Granular Rights & Capabilities Matrix</h4>
                    <div class="adm-perm-quick-actions">
                        <button type="button" class="adm-btn adm-btn-secondary adm-btn-xs" onclick="toggleAllPermissions(true)">Select All</button>
                        <button type="button" class="adm-btn adm-btn-secondary adm-btn-xs" onclick="toggleAllPermissions(false)">Deselect All</button>
                    </div>
                </div>

                <div class="adm-perm-modules-grid">
                    @foreach($permissions_catalog as $moduleKey => $module)
                    <div class="adm-perm-module-card">
                        <div class="adm-perm-module-header">
                            <div>
                                <strong class="adm-perm-module-label">{{ $module['label'] }}</strong>
                                <span class="adm-perm-module-desc">{{ $module['description'] }}</span>
                            </div>
                            <button type="button" class="adm-btn-text" onclick="toggleModulePermissions('{{ $moduleKey }}')">Toggle</button>
                        </div>
                        <div class="adm-perm-checklist" data-module="{{ $moduleKey }}">
                            @foreach($module['permissions'] as $pKey => $pDef)
                            <label class="adm-perm-item">
                                <input type="checkbox" name="permissions[]" value="{{ $pKey }}" class="adm-perm-checkbox" data-module="{{ $moduleKey }}">
                                <div>
                                    <div class="adm-perm-name">{{ $pDef['label'] }} <code class="adm-perm-code">{{ $pKey }}</code></div>
                                    <div class="adm-perm-subdesc">{{ $pDef['desc'] }}</div>
                                </div>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="adm-form-actions">
                <button type="button" class="adm-btn adm-btn-secondary" onclick="closeRoleModal()">Cancel</button>
                <button type="submit" class="adm-btn adm-btn-primary" id="roleSubmitBtn">💾 Save Role & Rights</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: View Role Members -->
<div class="adm-modal" id="roleMembersModal">
    <div class="adm-modal-dialog">
        <div class="adm-modal-header">
            <h3 class="adm-modal-title" id="roleMembersModalTitle">👥 Members Assigned to Role</h3>
            <button type="button" class="adm-modal-close" onclick="closeRoleMembersModal()">&times;</button>
        </div>
        <div class="adm-member-assignment-list" id="roleMembersList">
            <!-- Populated via JS -->
        </div>
        <div class="adm-form-actions">
            <button type="button" class="adm-btn adm-btn-secondary" onclick="closeRoleMembersModal()">Close</button>
        </div>
    </div>
</div>

<!-- Modal: Assign User to Role -->
<div class="adm-modal" id="assignUserToRoleModal">
    <div class="adm-modal-dialog">
        <div class="adm-modal-header">
            <h3 class="adm-modal-title" id="assignUserModalTitle">👤+ Assign User to Role</h3>
            <button type="button" class="adm-modal-close" onclick="closeAssignUserToRoleModal()">&times;</button>
        </div>
        <form action="@url('admin/roles/assign-member')" method="POST">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
            <input type="hidden" name="role_id" id="assignRoleIdInput" value="">
            <input type="hidden" name="return_project_id" value="{{ $project_id ?? '' }}">

            <div id="assignModalProjectBanner" class="adm-modal-context-box {{ !empty($project_id) ? 'active' : '' }}">
                <span class="adm-modal-context-label">Target Project Scope:</span>
                <strong id="assignModalProjectTitle" class="adm-modal-context-title">{{ $project['title'] ?? 'Platform Scope' }}</strong>
                <code id="assignModalProjectId" class="adm-modal-context-code">({{ $project_id ?? 'all-projects' }})</code>
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Select Registered User</label>
                <select name="username" class="adm-form-select" required>
                    <option value="">-- Choose User --</option>
                    @foreach($all_users as $u)
                        <option value="{{ $u['username'] }}">{{ $u['display_name'] ?? $u['username'] }} ({{ '@' . $u['username'] }})</option>
                    @endforeach
                </select>
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Target Documentation Project</label>
                <select name="project_id" id="assignModalProjectSelect" class="adm-form-select" required>
                    <option value="">-- Choose Project --</option>
                    @foreach($all_projects as $pId => $pInfo)
                        <option value="{{ $pId }}" {{ (!empty($project_id) && $project_id === $pId) ? 'selected' : '' }}>
                            {{ $pInfo['title'] }} ({{ $pId }})
                        </option>
                    @endforeach
                </select>
                <div class="adm-form-desc">Role privileges granted will apply to this project.</div>
            </div>

            <div class="adm-form-actions">
                <button type="button" class="adm-btn adm-btn-secondary" onclick="closeAssignUserToRoleModal()">Cancel</button>
                <button type="submit" class="adm-btn adm-btn-primary" id="assignModalSubmitBtn">Confirm Role Assignment</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
function openCreateRoleModal() {
    document.getElementById('roleModalTitle').textContent = '➕ Create Custom Role';
    document.getElementById('roleSubmitBtn').textContent = '💾 Create Custom Role';
    document.getElementById('roleInputId').value = '';
    document.getElementById('roleInputId').readOnly = false;
    document.getElementById('roleInputName').value = '';
    document.getElementById('roleInputDesc').value = '';
    
    // Clear checkboxes
    document.querySelectorAll('.adm-perm-checkbox').forEach(cb => cb.checked = false);

    document.getElementById('roleModal').classList.add('active');
}

function openEditRoleModal(role) {
    document.getElementById('roleModalTitle').textContent = '⚙️ Edit Role: ' + role.name;
    document.getElementById('roleSubmitBtn').textContent = '💾 Update Role & Rights';
    document.getElementById('roleInputId').value = role.id;
    // Prevent editing system role identifier
    document.getElementById('roleInputId').readOnly = !!role.is_system;
    document.getElementById('roleInputName').value = role.name;
    document.getElementById('roleInputDesc').value = role.description || '';

    const perms = role.permissions || [];
    const hasWildcard = perms.includes('*');

    document.querySelectorAll('.adm-perm-checkbox').forEach(cb => {
        if (hasWildcard) {
            cb.checked = true;
        } else {
            cb.checked = perms.includes(cb.value);
        }
    });

    document.getElementById('roleModal').classList.add('active');
}

function closeRoleModal() {
    document.getElementById('roleModal').classList.remove('active');
}

function toggleAllPermissions(check) {
    document.querySelectorAll('.adm-perm-checkbox').forEach(cb => cb.checked = check);
}

function toggleModulePermissions(moduleKey) {
    const checkboxes = document.querySelectorAll(`.adm-perm-checkbox[data-module="${moduleKey}"]`);
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
}

function openRoleMembersModal(roleId, roleName, assignments, activeProjectId) {
    const projId = activeProjectId || '{{ $project_id ?? '' }}';
    const projTitle = '{{ addslashes($project['title'] ?? '') }}';
    
    if (projId) {
        document.getElementById('roleMembersModalTitle').textContent = '👥 ' + roleName + ' Members in: ' + (projTitle || projId);
    } else {
        document.getElementById('roleMembersModalTitle').textContent = '👥 Members Assigned to: ' + roleName;
    }
    
    const list = document.getElementById('roleMembersList');
    list.innerHTML = '';

    if (!assignments || assignments.length === 0) {
        list.innerHTML = '<div class="adm-assignment-empty-box">No users are currently assigned to this role across projects.</div>';
    } else {
        // Sort assignments so members of activeProjectId appear first
        const sorted = [...assignments].sort((a, b) => {
            if (projId && a.project_id === projId && b.project_id !== projId) return -1;
            if (projId && b.project_id === projId && a.project_id !== projId) return 1;
            return (a.username || '').localeCompare(b.username || '');
        });

        sorted.forEach(a => {
            const isCurrentProj = projId && a.project_id === projId;
            const item = document.createElement('div');
            item.className = 'adm-member-assignment-item' + (isCurrentProj ? ' adm-member-item-highlight' : '');
            item.innerHTML = `
                <div>
                    <strong class="adm-member-name">@${a.username}</strong>
                    <div class="adm-member-proj-title">${a.project_title}</div>
                </div>
                <div>
                    ${isCurrentProj ? '<span class="adm-badge adm-badge-success">Active Project Scope</span> ' : ''}
                    <span class="adm-badge adm-badge-secondary">${a.project_id}</span>
                </div>
            `;
            list.appendChild(item);
        });
    }

    document.getElementById('roleMembersModal').classList.add('active');
}

function closeRoleMembersModal() {
    document.getElementById('roleMembersModal').classList.remove('active');
}

function openAssignUserToRoleModal(roleId, roleName, defaultProjectId, defaultProjectTitle) {
    document.getElementById('assignRoleIdInput').value = roleId;
    
    const projId = defaultProjectId || '{{ $project_id ?? '' }}';
    const projTitle = defaultProjectTitle || '{{ addslashes($project['title'] ?? '') }}';
    
    const banner = document.getElementById('assignModalProjectBanner');
    const titleEl = document.getElementById('assignModalProjectTitle');
    const idEl = document.getElementById('assignModalProjectId');
    const selectEl = document.getElementById('assignModalProjectSelect');
    const modalTitle = document.getElementById('assignUserModalTitle');
    const submitBtn = document.getElementById('assignModalSubmitBtn');

    if (projId) {
        modalTitle.textContent = '👤+ Assign User to ' + roleName + ' in ' + (projTitle || projId);
        submitBtn.textContent = 'Confirm Assignment to ' + (projTitle || projId);
        banner.classList.add('active');
        titleEl.textContent = projTitle || projId;
        idEl.textContent = '(' + projId + ')';
        selectEl.value = projId;
    } else {
        modalTitle.textContent = '👤+ Assign User to Role: ' + roleName;
        submitBtn.textContent = 'Confirm Role Assignment';
        banner.classList.remove('active');
        if (selectEl.querySelector('option[value=""]')) {
            selectEl.value = '';
        }
    }

    document.getElementById('assignUserToRoleModal').classList.add('active');
}

function closeAssignUserToRoleModal() {
    document.getElementById('assignUserToRoleModal').classList.remove('active');
}
</script>
@endsection
