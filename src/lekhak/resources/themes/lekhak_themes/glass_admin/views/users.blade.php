@extends('layout')

@section('actions')
<button onclick="openCreateModal()" class="btn btn-primary">
    <span>➕</span> Add New User
</button>
@endsection

@section('content')
<div class="lekhak-local-tasks-header" style="margin-bottom: 25px; border-bottom: 1px solid var(--glass-border); padding-bottom: 12px;">
    <h2 style="margin: 0; font-size: 1.5rem; color: var(--text-main);">{{ $title }}</h2>
    <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">{{ $subtitle }}</p>
</div>

<div class="glass-panel" style="padding: 20px; margin-bottom: 30px;">
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 1px solid var(--glass-border);">
                    <th style="padding: 12px 16px; color: var(--text-dim); font-weight: 600; font-size: 0.85rem; text-transform: uppercase;">Username</th>
                    <th style="padding: 12px 16px; color: var(--text-dim); font-weight: 600; font-size: 0.85rem; text-transform: uppercase;">Email</th>
                    <th style="padding: 12px 16px; color: var(--text-dim); font-weight: 600; font-size: 0.85rem; text-transform: uppercase;">Role</th>
                    <th style="padding: 12px 16px; color: var(--text-dim); font-weight: 600; font-size: 0.85rem; text-transform: uppercase;">Status</th>
                    <th style="padding: 12px 16px; color: var(--text-dim); font-weight: 600; font-size: 0.85rem; text-transform: uppercase;">Created At</th>
                    <th style="padding: 12px 16px; color: var(--text-dim); font-weight: 600; font-size: 0.85rem; text-transform: uppercase; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users ?? [] as $user)
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.02)'" onmouseout="this.style.backgroundColor='transparent'">
                    <td style="padding: 16px; font-weight: 600; color: var(--text-main);">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 1.2rem;">👤</span>
                            <span>{{ $user['username'] }}</span>
                        </div>
                    </td>
                    <td style="padding: 16px; color: var(--text-dim);">{{ $user['email'] }}</td>
                    <td style="padding: 16px;">
                        <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.3); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                            {{ $user['role_name'] ?? 'No Role Assigned' }}
                        </span>
                    </td>
                    <td style="padding: 16px;">
                        @if(($user['status'] ?? 'active') === 'active')
                            <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Active</span>
                        @else
                            <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Inactive</span>
                        @endif
                    </td>
                    <td style="padding: 16px; color: var(--text-dim); font-size: 0.85rem;">
                        {{ !empty($user['created_at']) ? date('M d, Y H:i', strtotime($user['created_at'])) : 'N/A' }}
                    </td>
                    <td style="padding: 16px; text-align: right;">
                        <div style="display: inline-flex; gap: 8px;">
                            <button onclick="openEditModal({{ json_encode($user) }})" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem;">
                                Edit
                            </button>
                            @if($user['username'] !== 'admin')
                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete this user?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="{{ $user['id'] }}">
                                <button type="submit" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem; color: var(--danger);">
                                    Delete
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
                @if(empty($users))
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-dim); padding: 40px;">No administrative users found.</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

<!-- Glassmorphic Modal for Add/Edit User -->
<div id="userModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; padding: 20px;">
    <div class="glass-panel" style="max-width: 500px; width: 100%; padding: 30px; position: relative; animation: modalSlideIn 0.3s ease-out; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
        <button onclick="closeModal()" style="position: absolute; top: 20px; right: 20px; background: transparent; border: none; color: var(--text-dim); font-size: 1.25rem; cursor: pointer; transition: color 0.2s;" onmouseover="this.style.color='var(--text-main)'" onmouseout="this.style.color='var(--text-dim)'">✕</button>
        
        <h3 id="modalTitle" style="margin-top: 0; margin-bottom: 20px; font-size: 1.25rem; color: var(--text-main); font-family: 'Outfit', sans-serif;">Add New User</h3>
        
        <form id="userForm" method="POST" action="">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="user_id" id="userIdField">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 6px; color: var(--text-main); font-weight: 500; font-size: 0.85rem;">Username</label>
                <input type="text" name="username" id="usernameField" required class="form-control" style="width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 6px; outline: none; font-size: 0.9rem; transition: border-color 0.2s;">
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 6px; color: var(--text-main); font-weight: 500; font-size: 0.85rem;">Email Address</label>
                <input type="email" name="email" id="emailField" required class="form-control" style="width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 6px; outline: none; font-size: 0.9rem; transition: border-color 0.2s;">
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label id="passwordLabel" style="display: block; margin-bottom: 6px; color: var(--text-main); font-weight: 500; font-size: 0.85rem;">Password</label>
                <input type="password" name="password" id="passwordField" class="form-control" style="width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 6px; outline: none; font-size: 0.9rem; transition: border-color 0.2s;">
                <div id="passwordHelp" style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px; display: none;">Leave blank to keep the current password.</div>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 6px; color: var(--text-main); font-weight: 500; font-size: 0.85rem;">Role Assignment</label>
                <select name="role_id" id="roleField" class="form-control" style="width: 100%; padding: 10px 14px; background: #1e293b; border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 6px; outline: none; font-size: 0.9rem; cursor: pointer;">
                    @foreach($roles ?? [] as $role)
                        <option value="{{ $role['id'] }}">{{ $role['role_name'] }}</option>
                    @endforeach
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 25px;">
                <label style="display: block; margin-bottom: 6px; color: var(--text-main); font-weight: 500; font-size: 0.85rem;">Status</label>
                <select name="status" id="statusField" class="form-control" style="width: 100%; padding: 10px 14px; background: #1e293b; border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 6px; outline: none; font-size: 0.9rem; cursor: pointer;">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="closeModal()" class="btn btn-secondary" style="padding: 10px 20px; font-size: 0.9rem; border-radius: 6px;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px; font-size: 0.9rem; border-radius: 6px; background: var(--accent-primary); border: none; color: white; cursor: pointer; font-weight: 600;">Save User</button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes modalSlideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.form-control:focus {
    border-color: var(--accent-primary) !important;
    background: rgba(255,255,255,0.06) !important;
}
</style>

<script>
function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Add New User';
    document.getElementById('formAction').value = 'create';
    document.getElementById('userIdField').value = '';
    
    const usernameField = document.getElementById('usernameField');
    usernameField.value = '';
    usernameField.disabled = false;
    usernameField.required = true;
    
    document.getElementById('emailField').value = '';
    
    const passwordField = document.getElementById('passwordField');
    passwordField.value = '';
    passwordField.required = true;
    document.getElementById('passwordHelp').style.display = 'none';
    
    document.getElementById('roleField').selectedIndex = 0;
    document.getElementById('statusField').value = 'active';
    
    const modal = document.getElementById('userModal');
    modal.style.display = 'flex';
}

function openEditModal(user) {
    document.getElementById('modalTitle').textContent = 'Edit User: ' + user.username;
    document.getElementById('formAction').value = 'update';
    document.getElementById('userIdField').value = user.id;
    
    const usernameField = document.getElementById('usernameField');
    usernameField.value = user.username;
    usernameField.disabled = true; // Username is not editable
    usernameField.required = false;
    
    document.getElementById('emailField').value = user.email || '';
    
    const passwordField = document.getElementById('passwordField');
    passwordField.value = '';
    passwordField.required = false; // Optional on edit
    document.getElementById('passwordHelp').style.display = 'block';
    
    document.getElementById('roleField').value = user.role_id;
    document.getElementById('statusField').value = user.status || 'active';
    
    const modal = document.getElementById('userModal');
    modal.style.display = 'flex';
}

function closeModal() {
    document.getElementById('userModal').style.display = 'none';
}

// Close modal when clicking outside of it
window.addEventListener('click', function(event) {
    const modal = document.getElementById('userModal');
    if (event.target === modal) {
        closeModal();
    }
});
</script>
@endsection
