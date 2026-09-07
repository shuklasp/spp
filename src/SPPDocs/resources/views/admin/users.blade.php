@extends('layouts.admin')

@section('title', 'User & Access Governance')

@section('content')
<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">User & Access Governance</h1>
        <p class="adm-page-desc">Manage registered user accounts, designate Global Super Admins, and audit cross-project privileges.</p>
    </div>
    <button type="button" class="adm-btn adm-btn-primary" onclick="document.getElementById('createUserModal').classList.add('active')">
        ➕ Create New User
    </button>
</div>

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

<!-- Users Table Card -->
<div class="adm-card">
    <div class="adm-card-header">
        <h2 class="adm-card-title">Registered Accounts ({{ count($users) }})</h2>
    </div>
    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Global Authority</th>
                    <th>Assigned Project Roles</th>
                    <th>Created</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $u)
                <tr>
                    <td>
                        <div class="adm-user-cell">
                            <div class="adm-user-avatar">{{ strtoupper(substr($u['username'], 0, 1)) }}</div>
                            <div class="adm-user-meta">
                                <strong class="adm-user-name">{{ $u['display_name'] ?? $u['username'] }}</strong>
                                <span class="adm-user-handle">{{ '@' . $u['username'] }}</span>
                            </div>
                        </div>
                    </td>
                    <td>{{ !empty($u['email']) ? $u['email'] : '—' }}</td>
                    <td>
                        @if(!empty($u['is_global_admin']))
                            <span class="adm-badge adm-badge-admin">👑 Global Super Admin</span>
                        @else
                            <span class="adm-badge adm-badge-secondary">Standard User</span>
                        @endif
                    </td>
                    <td>
                        <div class="adm-role-pill-list">
                            @if(!empty($u['is_global_admin']))
                                <div class="adm-role-pill adm-role-pill-admin" title="Global Super Admin: Root access across all projects">
                                    <span class="adm-role-pill-proj">All Projects</span>
                                    <span class="adm-role-pill-role">👑 Super Admin</span>
                                </div>
                            @endif

                            @php $hasProjectRoles = false; @endphp
                            @foreach($u['assigned_roles'] ?? [] as $pRole)
                                @if(($pRole['project_id'] ?? '') === '*')
                                    @continue
                                @endif
                                @php
                                    $hasProjectRoles = true;
                                    $pId = $pRole['project_id'];
                                    $roleDefs = $pRole['roles'] ?? [];
                                    if (empty($roleDefs) && !empty($pRole['role_id'])) {
                                        $roleDefs = [['id' => $pRole['role_id'], 'name' => $pRole['role_name'] ?? ucfirst($pRole['role_id']), 'is_custom' => !empty($pRole['is_custom'])]];
                                    }
                                @endphp
                                <div class="adm-role-group-cluster" title="{{ $pRole['project_title'] }}">
                                    <span class="adm-role-cluster-proj">{{ $pId }}</span>
                                    <div class="adm-role-cluster-badges">
                                        @foreach($roleDefs as $r)
                                            @php
                                                $rId = $r['id'] ?? '';
                                                $bClass = 'adm-role-cluster-reporter';
                                                if ($rId === 'admin') $bClass = 'adm-role-cluster-admin';
                                                elseif ($rId === 'maintainer') $bClass = 'adm-role-cluster-maintainer';
                                                elseif ($rId === 'developer') $bClass = 'adm-role-cluster-developer';
                                                elseif ($rId === 'viewer') $bClass = 'adm-role-cluster-viewer';
                                                elseif (!empty($r['is_custom'])) $bClass = 'adm-role-cluster-custom';
                                            @endphp
                                            <span class="adm-role-cluster-badge {{ $bClass }}">{{ $r['name'] ?? ucfirst($rId) }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach

                            @if(empty($u['is_global_admin']) && !$hasProjectRoles)
                                <span class="adm-role-empty-badge">— No project roles assigned —</span>
                            @endif
                        </div>
                    </td>
                    <td>{{ date('M d, Y', $u['created_at']) }}</td>
                    <td class="text-right">
                        <div class="adm-action-btn-group">
                            <button type="button" class="adm-btn adm-btn-secondary adm-btn-sm" onclick='openAssignRolesModal(@json($u))'>
                                🛡️ Assign Roles
                            </button>

                            <form action="@url('admin/users/toggle-global-admin')" method="POST" class="adm-inline-form">
                                <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                                <input type="hidden" name="username" value="{{ $u['username'] }}">
                                @if(!empty($u['is_global_admin']))
                                    <input type="hidden" name="action" value="revoke">
                                    <button type="submit" class="adm-btn adm-btn-danger adm-btn-sm" {{ $u['username'] === 'admin' ? 'disabled title="Cannot revoke default root admin"' : '' }}>
                                        Revoke Global Admin
                                    </button>
                                @else
                                    <input type="hidden" name="action" value="grant">
                                    <button type="submit" class="adm-btn adm-btn-secondary adm-btn-sm">
                                        Promote to Global Admin
                                    </button>
                                @endif
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Assign Roles Across Projects -->
<div class="adm-modal" id="assignRolesModal">
    <div class="adm-modal-dialog adm-modal-dialog-large">
        <div class="adm-modal-header">
            <h3 class="adm-modal-title">🛡️ Manage Project Role Assignments: <span id="assignModalUsername"></span></h3>
            <button type="button" class="adm-modal-close" onclick="closeAssignRolesModal()">&times;</button>
        </div>
        <form action="@url('admin/users/assign-roles')" method="POST">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
            <input type="hidden" name="username" id="assignModalUserInput" value="">

            <p class="adm-page-desc">
                Assign one or multiple roles to this user per documentation project. Conforms to SPP's native RBAC engine where permissions are computed as the cumulative union of all assigned roles.
            </p>

            <div class="adm-assign-project-grid">
                @foreach($all_projects as $pId => $pInfo)
                <input type="hidden" name="project_ids[]" value="{{ $pId }}">
                <div class="adm-assign-project-row">
                    <div class="adm-assign-project-header">
                        <div class="adm-assign-project-info">
                            <span class="adm-assign-project-title">{{ $pInfo['title'] }}</span>
                            <code class="adm-assign-project-id">{{ $pId }}</code>
                        </div>
                        <button type="button" class="adm-assign-project-clear" onclick="clearProjectRoles('{{ $pId }}')">✕ Clear Roles</button>
                    </div>
                    <div class="adm-assign-role-pills" id="project_roles_{{ $pId }}">
                        @foreach($all_roles as $rId => $rDef)
                            @php
                                $isSystem = !empty($rDef['is_system']);
                                $tagVariant = 'adm-role-tag-' . ($isSystem ? $rId : 'custom');
                            @endphp
                            <label class="adm-role-tag {{ $tagVariant }}" title="{{ $rDef['description'] ?? '' }}">
                                <input type="checkbox" name="roles[{{ $pId }}][]" value="{{ $rId }}" id="role_{{ $pId }}_{{ $rId }}" class="adm-role-cb-{{ $pId }}">
                                <span class="adm-role-tag-inner">
                                    <span class="adm-role-tag-check-icon">✓</span>
                                    <span>{{ $isSystem ? '' : '✨ ' }}{{ $rDef['name'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>

            <div class="adm-form-actions">
                <button type="button" class="adm-btn adm-btn-secondary" onclick="closeAssignRolesModal()">Cancel</button>
                <button type="submit" class="adm-btn adm-btn-primary">💾 Save Role Assignments</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Create User Account -->
<div class="adm-modal" id="createUserModal">
    <div class="adm-modal-dialog">
        <div class="adm-modal-header">
            <h3 class="adm-modal-title">Create New Account</h3>
            <button type="button" class="adm-modal-close" onclick="document.getElementById('createUserModal').classList.remove('active')">&times;</button>
        </div>
        <form action="@url('admin/users/create')" method="POST">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">

            <div class="adm-form-group">
                <label class="adm-form-label">Username</label>
                <input type="text" name="username" placeholder="e.g. jdoe" class="adm-form-input" required pattern="[a-zA-Z0-9_\-\.]+">
                <div class="adm-form-desc">Alphanumeric, dots, hyphens, and underscores only.</div>
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Display Name</label>
                <input type="text" name="display_name" placeholder="e.g. Jane Doe" class="adm-form-input">
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Email Address</label>
                <input type="email" name="email" placeholder="jane@example.com" class="adm-form-input">
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Password</label>
                <input type="password" name="password" placeholder="Minimum 4 characters" class="adm-form-input" required minlength="4">
            </div>

            <!-- Initial Project Role Assignment -->
            <div class="adm-form-grid">
                <div class="adm-form-group adm-db-col-6">
                    <label class="adm-form-label">Initial Project</label>
                    <select name="initial_project" class="adm-form-select">
                        <option value="">-- None (Do not assign) --</option>
                        @foreach($all_projects as $pId => $pInfo)
                            <option value="{{ $pId }}">{{ $pInfo['title'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="adm-form-group adm-db-col-6">
                    <label class="adm-form-label">Initial Role</label>
                    <select name="initial_role" class="adm-form-select">
                        <option value="developer">Developer</option>
                        @foreach($all_roles as $rId => $rDef)
                            @if($rId !== 'developer')
                                <option value="{{ $rId }}">{{ $rDef['name'] }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="adm-form-group">
                <label class="adm-checkbox-label">
                    <input type="checkbox" name="is_global_admin" value="1" class="adm-perm-checkbox">
                    Grant Global Super Administrator Rights
                </label>
                <div class="adm-form-desc">
                    Global admins have unrestricted access to platform configurations, all projects, and user governance.
                </div>
            </div>

            <div class="adm-form-actions">
                <button type="button" class="adm-btn adm-btn-secondary" onclick="document.getElementById('createUserModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="adm-btn adm-btn-primary">Create Account</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
function openAssignRolesModal(user) {
    document.getElementById('assignModalUsername').textContent = '@' + user.username;
    document.getElementById('assignModalUserInput').value = user.username;

    // Reset all checkboxes across all projects
    document.querySelectorAll('.adm-assign-role-pills input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });

    // Populate current assigned roles (array of role IDs)
    const assigned = user.assigned_roles || {};
    for (const [pId, pData] of Object.entries(assigned)) {
        if (pId === '*') continue;
        const roleIds = pData.role_ids || (pData.role_id ? pData.role_id.split(',').map(s => s.trim()) : []);
        for (const rId of roleIds) {
            const cb = document.getElementById('role_' + pId + '_' + rId);
            if (cb) {
                cb.checked = true;
            }
        }
    }

    document.getElementById('assignRolesModal').classList.add('active');
}

function clearProjectRoles(pId) {
    document.querySelectorAll('.adm-role-cb-' + pId).forEach(cb => {
        cb.checked = false;
    });
}

function closeAssignRolesModal() {
    document.getElementById('assignRolesModal').classList.remove('active');
}
</script>
@endsection
