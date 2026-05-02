/**
 * AccessView Component
 */

/**
 * AccessView Component
 * 
 * Manages Identity & Access Management (IAM), including Users, Roles, Rights, and Assignments.
 */
export default class AccessView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: true,
            activeTab: localStorage.getItem('spp_admin_iam_tab') || 'users',
            items: [],
            error: null,
            page: 1,
            pageSize: 10,
            filters: {}
        };
        await this.switchTab(this.state.activeTab, true);
    }

    async switchTab(tab, force = false) {
        if (!force && this.state.activeTab === tab) return;
        
        this.setState({ 
            activeTab: tab, 
            loading: true, 
            items: [],
            page: 1,
            filters: {}
        });
        localStorage.setItem('spp_admin_iam_tab', tab);

        try {
            let action = 'list_users';
            if (tab === 'roles') action = 'list_roles';
            if (tab === 'rights') action = 'list_rights';
            if (tab === 'modern_rbac') action = 'list_rbac';
            if (tab === 'assignments') action = 'list_entity_assignments';

            const res = await this.api(action);
            if (res.success) {
                let items = res.data.users || res.data.roles || res.data.rights || res.data.queue || res.data || [];
                
                // Normalize Modern RBAC (object map to array)
                if (tab === 'modern_rbac' && !Array.isArray(items)) {
                    items = Object.entries(items).map(([slug, data]) => ({
                        slug,
                        permissions: data.permissions || [],
                        ...data
                    }));
                }

                this.setState({ 
                    items: items, 
                    loading: false 
                });
            } else {
                throw new Error(res.message);
            }
        } catch (err) {
            this.setState({ loading: false, error: err.message });
        }
    }

    render() {
        const { loading, activeTab, items, error } = this.state;

        // Update Header
        const headerActions = document.getElementById('header-actions');
        if (headerActions) {
            headerActions.innerHTML = '';
            const btn = document.createElement('button');
            btn.className = 'btn primary-btn btn-sm';
            if (activeTab === 'assignments') {
                btn.innerHTML = '+ New Assignment';
                btn.onclick = () => this.openAssignmentEditor();
            } else if (activeTab === 'modern_rbac') {
                btn.innerHTML = '+ New Modern Role';
                btn.onclick = () => this.openModernRoleEditor();
            } else {
                btn.innerHTML = `+ New ${activeTab.slice(0, -1).charAt(0).toUpperCase() + activeTab.slice(1, -1)}`;
                btn.onclick = () => this.openEditor(activeTab);
            }
            headerActions.appendChild(btn);
        }

        return html`
            <div class="iam-workspace">
                <div class="tab-bar-secondary mb-4">
                    <button class="sub-tab-btn ${activeTab === 'users' ? 'active' : ''}" @click=${() => this.switchTab('users')}>👥 Users</button>
                    <button class="sub-tab-btn ${activeTab === 'roles' ? 'active' : ''}" @click=${() => this.switchTab('roles')}>🛡️ Legacy Roles</button>
                    <button class="sub-tab-btn ${activeTab === 'rights' ? 'active' : ''}" @click=${() => this.switchTab('rights')}>🔑 Legacy Rights</button>
                    <button class="sub-tab-btn ${activeTab === 'modern_rbac' ? 'active' : ''}" @click=${() => this.switchTab('modern_rbac')}>⚡ Modern RBAC</button>
                    <button class="sub-tab-btn ${activeTab === 'assignments' ? 'active' : ''}" @click=${() => this.switchTab('assignments')}>🔗 Assignments</button>
                </div>

                <div id="iam-content">
                    ${loading ? html`<div class="loading-state">Syncing security manifests...</div>` : ''}
                    ${error ? html`<div class="alert error">${error}</div>` : ''}
                    
                    ${!loading && !error ? this.renderTabContent() : ''}
                </div>
            </div>
        `;
    }

    renderTabContent() {
        const { activeTab, items, filters, page, pageSize } = this.state;
        
        if (items.length === 0) {
            return html`
                <div class="empty-state">
                    <div class="empty-icon">🛡️</div>
                    <h3>No Records Found</h3>
                    <p>Start by creating your first security entity in this context.</p>
                </div>
            `;
        }

        // 1. Apply Filtering
        const filteredItems = items.filter(item => {
            return Object.entries(filters).every(([field, val]) => {
                if (!val) return true;
                const itemVal = String(item[field] || '').toLowerCase();
                return itemVal.includes(val.toLowerCase());
            });
        });

        // 2. Apply Paging
        const totalItems = filteredItems.length;
        const totalPages = Math.ceil(totalItems / pageSize);
        const startIndex = (page - 1) * pageSize;
        const pagedItems = filteredItems.slice(startIndex, startIndex + pageSize);

        if (activeTab === 'assignments') {
            return this.renderAssignmentsTable(pagedItems, totalItems, totalPages);
        }

        if (activeTab === 'modern_rbac') {
            return this.renderModernRbacTable(pagedItems, totalItems, totalPages);
        }

        return this.renderStandardTable(pagedItems, totalItems, totalPages);
    }

    renderModernRbacTable(items, totalItems, totalPages) {
        // Items is already normalized to array in switchTab
        const roleList = items;

        return html`
            <div class="glass-panel">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Role Slug</th>
                            <th>Permissions Count</th>
                            <th>Resolved Permissions</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${roleList.map(role => html`
                            <tr>
                                <td><code>${role.slug}</code></td>
                                <td><span class="badge info">${role.permissions.length}</span></td>
                                <td>
                                    <div class="permission-cloud">
                                        ${role.permissions.slice(0, 5).map(p => html`<span class="p-tag">${p}</span>`)}
                                        ${role.permissions.length > 5 ? html`<span class="p-more">+${role.permissions.length - 5} more</span>` : ''}
                                    </div>
                                </td>
                                <td class="text-right">
                                    <button class="btn ghost-btn btn-sm" @click=${() => this.openModernRoleEditor(role.slug, role.permissions)}>Edit</button>
                                </td>
                            </tr>
                        `)}
                    </tbody>
                </table>
            </div>
            ${this.renderPagination(totalItems, totalPages)}
        `;
    }

    renderStandardTable(items, totalItems, totalPages) {
        const { activeTab, filters, page } = this.state;
        const columns = this.getTableColumns(activeTab);

        return html`
            <div class="glass-panel">
                <table class="data-table">
                    <thead>
                        <tr>
                            ${columns.map(col => html`<th>${col.label}</th>`)}
                            <th class="text-right">Actions</th>
                        </tr>
                        <tr class="filter-row">
                            ${columns.map(col => html`
                                <th>
                                    <input type="text" class="table-filter" placeholder="Filter ${col.label}..."
                                        value="${filters[col.key] || ''}"
                                        @input=${(e) => this.updateFilter(col.key, e.target.value)}>
                                </th>
                            `)}
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${items.map(item => {
                            const title = item.username || item.role_name || item.name;
                            return html`
                                <tr>
                                    ${columns.map((col, i) => {
                                        const val = item[col.key];
                                        if (i === 0) return html`<td><code>${val}</code></td>`;
                                        if (col.key === 'status') {
                                            if (activeTab === 'users') {
                                                return html`
                                                    <td>
                                                        <button class="status-toggle-pill ${val}" 
                                                            onclick="${() => this.toggleUserStatus(item.id, item.status)}">
                                                            ${val.toUpperCase()}
                                                        </button>
                                                    </td>`;
                                            }
                                            return html`<td><span class="status-badge ${val}">${val}</span></td>`;
                                        }
                                        return html`<td>${val}</td>`;
                                    })}
                                    <td class="text-right">
                                        <div class="action-links">
                                            ${activeTab === 'users' ? html`
                                                <button class="btn ghost-btn btn-sm" onclick="${() => this.openUserRolesEditor(item.id, title)}">Roles</button>
                                            ` : ''}
                                            ${activeTab === 'roles' ? html`
                                                <button class="btn ghost-btn btn-sm" onclick="${() => this.openMassUserAssignor(item.id, title)}">Users</button>
                                                <button class="btn ghost-btn btn-sm" onclick="${() => this.openRoleRightsEditor(item.id, title)}">Rights</button>
                                            ` : ''}
                                            <button class="btn ghost-btn btn-sm" onclick="${() => this.openEditor(activeTab, item.id, title)}">Edit</button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        })}
                    </tbody>
                </table>
            </div>
            ${this.renderPagination(totalItems, totalPages)}
        `;
    }

    renderAssignmentsTable(items, totalItems, totalPages) {
        const { filters } = this.state;
        return html`
            <div class="glass-panel">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Target Type</th>
                            <th>Target ID</th>
                            <th>Assigned Roles</th>
                            <th class="text-right">Actions</th>
                        </tr>
                        <tr class="filter-row">
                            <th><input type="text" class="table-filter" placeholder="Filter..." value="${filters.target_class || ''}" oninput="${(e) => this.updateFilter('target_class', e.target.value)}"></th>
                            <th><input type="text" class="table-filter" placeholder="Filter..." value="${filters.target_id || ''}" oninput="${(e) => this.updateFilter('target_id', e.target.value)}"></th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${items.map(asgn => {
                            const shortClass = asgn.target_class.split('\\').pop();
                            return html`
                                <tr>
                                    <td><span class="badge ${shortClass === 'SPPUser' ? 'info' : 'warning'}">${shortClass}</span></td>
                                    <td><code>${asgn.target_id}</code></td>
                                    <td>
                                        <div class="item-tags">
                                            ${asgn.roles.map(role => html`
                                                <div class="role-tag" style="background: rgba(255,255,255,0.05); padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 6px; margin-right: 4px;">
                                                    <span>${role.name}</span>
                                                    <span class="remove-role" style="cursor: pointer; opacity: 0.6;" 
                                                        onclick="${() => this.removeAssignment(asgn.target_class, asgn.target_id, role.id)}">✕</span>
                                                </div>
                                            `)}
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <button class="btn ghost-btn btn-sm" onclick="${() => this.openAssignmentEditor(asgn.target_class, asgn.target_id)}">Edit</button>
                                    </td>
                                </tr>
                            `;
                        })}
                    </tbody>
                </table>
            </div>
            ${this.renderPagination(totalItems, totalPages)}
        `;
    }

    getTableColumns(tab) {
        if (tab === 'users') return [
            { key: 'id', label: 'ID' },
            { key: 'username', label: 'Username' },
            { key: 'email', label: 'Email' },
            { key: 'status', label: 'Status' }
        ];
        if (tab === 'roles') return [
            { key: 'id', label: 'ID' },
            { key: 'role_name', label: 'Role Name' },
            { key: 'description', label: 'Description' }
        ];
        if (tab === 'rights') return [
            { key: 'id', label: 'ID' },
            { key: 'name', label: 'Name' },
            { key: 'description', label: 'Description' }
        ];
        return [];
    }

    renderPagination(total, totalPages) {
        const { page, pageSize } = this.state;
        if (totalPages <= 1) return '';

        const pages = [];
        for (let i = 1; i <= totalPages; i++) {
            pages.push(i);
        }

        return html`
            <div class="pagination-bar">
                <div class="pagination-info">
                    Showing <strong>${(page - 1) * pageSize + 1}</strong> to <strong>${Math.min(page * pageSize, total)}</strong> of <strong>${total}</strong> records
                </div>
                <div class="pagination-controls">
                    <button class="page-btn" ?disabled="${page === 1}" onclick="${() => this.setState({ page: page - 1 })}">«</button>
                    ${pages.map(p => html`
                        <button class="page-btn ${page === p ? 'active' : ''}" onclick="${() => this.setState({ page: p })}">${p}</button>
                    `)}
                    <button class="page-btn" ?disabled="${page === totalPages}" onclick="${() => this.setState({ page: page + 1 })}">»</button>
                </div>
            </div>
        `;
    }

    updateFilter(key, val) {
        const newFilters = { ...this.state.filters };
        if (val) newFilters[key] = val;
        else delete newFilters[key];
        
        this.setState({ filters: newFilters, page: 1 });
    }

    async openEditor(type, id = null, name = '') {
        const title = id ? `Edit ${type.slice(0, -1)}: ${name}` : `Create New ${type.slice(0, -1)}`;
        this.openModal(title, html`<div class="loader">Fetching framework form for ${type}...</div>`.toString());

        const saveBtn = document.getElementById('modal-save');
        saveBtn.textContent = 'Save Changes';
        saveBtn.onclick = () => this.save(type, id);

        // Map tab to form name
        let formName = 'user_edit';
        if (type === 'roles') formName = 'role_edit';
        if (type === 'rights') formName = 'right_edit';

        try {
            const fd = new FormData();
            fd.append('action', 'get_form_html');
            fd.append('form', formName);
            if (id) fd.append('id', id);

            const res = await this.apiPost(fd);
            if (res.success) {
                document.getElementById('modal-body').innerHTML = `
                    <div class="spp-form-wrapper">
                        ${res.data.html}
                    </div>
                `;
            } else {
                throw new Error(res.message);
            }
        } catch (err) {
            this.notify('Failed to load form: ' + err.message, 'error');
        }
    }

    async save(type, id) {
        const form = document.querySelector('#modal-body form');
        if (!form) return;

        const fd = new FormData(form);
        let action = 'save_user';
        if (type === 'roles') action = 'save_role';
        if (type === 'rights') action = 'save_right';
        
        fd.append('action', action);
        if (id) fd.append('id', id);

        const res = await this.apiPost(fd);
        if (res.success) {
            this.notify(res.message, 'success');
            this.closeModal();
            this.switchTab(type, true);
        } else {
            this.handleApiErrors(res);
        }
    }

    async openAssignmentEditor(targetClass = null, targetId = null) {
        this.openModal(targetId ? 'Edit Role Assignment' : 'New Role Assignment', html`<div class="loader">Preparing assignment form...</div>`.toString());
        
        try {
            const rolesRes = await this.api.listRoles();
            if (!rolesRes.success) throw new Error(rolesRes.message);

            // Fetch current roles if editing
            let assignedRoleIds = [];
            if (targetClass && targetId) {
                const detailsFd = new FormData();
                detailsFd.append('action', 'get_iam_details');
                detailsFd.append('type', targetClass.includes('SPPUser') ? 'users' : 'roles'); // Defaulting to users if unknown check
                // Wait, if it's a group, I might need another detail fetcher.
                // However, the targetClass could be anything.
                // For now, let's assume we can fetch it if it's a User.
                if (targetClass.includes('SPPUser')) {
                    detailsFd.append('id', targetId);
                    const detRes = await SPPUX.apiPost(detailsFd);
                    if (detRes.success) assignedRoleIds = detRes.data.assigned_ids || [];
                }
            }

            document.getElementById('modal-body').innerHTML = html`
                <form id="assignment-form" class="assignment-form">
                    <div class="form-group">
                        <label>1. Select Entity Type</label>
                        <select name="target_class" id="asgn-class" class="spp-element" ${targetId ? 'disabled' : ''}>
                            <option value="SPPMod\\SPPAuth\\SPPUser" ${targetClass === 'SPPMod\\SPPAuth\\SPPUser' ? 'selected' : ''}>User</option>
                            <option value="SPPMod\\SPPEntity\\SPPGroup" ${targetClass === 'SPPMod\\SPPEntity\\SPPGroup' ? 'selected' : ''}>Group</option>
                        </select>
                        ${targetId ? html`<input type="hidden" name="target_class" value="${targetClass}">` : ''}
                    </div>
                    <div class="form-group">
                        <label>2. Search & Select Entity</label>
                        <div class="searchable-entity-picker" style="position: relative;">
                            <input type="text" id="asgn-search" class="spp-element" placeholder="${targetId || 'Type to search...'}" 
                                value="${targetId || ''}" ${targetId ? 'readonly' : ''} autocomplete="off">
                            <input type="hidden" name="target_id" id="asgn-id" value="${targetId || ''}">
                            <div id="asgn-suggestions" class="search-suggestions-dropdown"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>3. Select Roles (Multiple)</label>
                        <select name="role_id[]" id="asgn-roles" class="spp-element" multiple style="height: 120px;">
                            ${rolesRes.data.roles.map(r => html`<option value="${r.id}" ?selected="${assignedRoleIds.includes(r.id)}">${r.role_name}</option>`)}
                        </select>
                        <small style="opacity: 0.6; display: block; margin-top: 4px;">Hold Ctrl/Cmd to select multiple</small>
                    </div>
                </form>
            `.toString();

            const searchInput = document.getElementById('asgn-search');
            const classSelect = document.getElementById('asgn-class');
            const suggestionsList = document.getElementById('asgn-suggestions');
            const idInput = document.getElementById('asgn-id');

            searchInput.oninput = async (e) => {
                const q = e.target.value.trim();
                if (q.length < 1) {
                    suggestionsList.innerHTML = '';
                    suggestionsList.classList.remove('active');
                    return;
                }

                const fd = new FormData();
                fd.append('action', 'search_entities');
                fd.append('type', classSelect.value);
                fd.append('q', q);

                const res = await SPPUX.apiPost(fd);
                const results = (res.data && res.data.results) || res.data || [];

                if (res.success && results.length > 0) {
                    suggestionsList.innerHTML = results.map(item => `
                        <div class="suggestion-item" onclick="document.getElementById('asgn-search').value='${item.label || item.name}'; document.getElementById('asgn-id').value='${item.id}'; document.getElementById('asgn-suggestions').innerHTML=''; document.getElementById('asgn-suggestions').classList.remove('active');">
                            ${item.label || item.name} <small style="opacity:0.5">(ID: ${item.id})</small>
                        </div>
                    `).join('');
                    suggestionsList.classList.add('active');
                } else {
                    suggestionsList.innerHTML = '<div class="empty-suggestion">No entities found</div>';
                    suggestionsList.classList.add('active');
                }
            };

            const saveBtn = document.getElementById('modal-save');
            saveBtn.textContent = 'Create Assignments';
            saveBtn.onclick = () => this.saveAssignment();

        } catch (err) {
            document.getElementById('modal-body').innerHTML = html`<div class="alert error">${err.message}</div>`.toString();
        }
    }

    async saveAssignment() {
        const form = document.getElementById('assignment-form');
        const fd = new FormData(form);
        
        if (!fd.get('target_id')) {
            this.notify('Please select an entity from suggestions.', 'error');
            return;
        }

        fd.append('action', 'assign_role_to_entity');
        const res = await SPPUX.apiPost(fd);
        if (res.success) {
            this.notify('Assignments created.', 'success');
            SPPUX.Modal.close();
            this.switchTab('assignments', true);
        } else {
            this.handleApiErrors(res);
        }
    }

    async removeAssignment(targetClass, targetId, roleId) {
        if (!confirm('Remove this role assignment?')) return;

        const fd = new FormData();
        fd.append('action', 'remove_role_from_entity');
        fd.append('target_class', targetClass);
        fd.append('target_id', targetId);
        fd.append('role_id', roleId);

        const res = await SPPUX.apiPost(fd);
        if (res.success) {
            this.notify('Assignment removed.', 'success');
            this.switchTab('assignments', true);
        } else {
            this.handleApiErrors(res);
        }
    }

    async openUserRolesEditor(userId, userName) {
        this.openModal(`Manage Roles: ${userName}`, html`<div class="loader">Fetching role manifest...</div>`.toString());
        
        try {
            const fd = new FormData();
            fd.append('action', 'get_iam_details');
            fd.append('type', 'users');
            fd.append('id', userId);

            const res = await SPPUX.apiPost(fd);
            if (!res.success) throw new Error(res.message);

            const { assigned_ids, available } = res.data;
            document.getElementById('modal-body').innerHTML = html`
                <div class="iam-management-grid">
                    <p class="mb-4 text-dim">Toggle roles assigned to this user. Changes are persisted immediately.</p>
                    <div class="glass-panel" style="padding: 1.5rem;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                            ${available.map(role => html`
                                <label class="checkbox-item" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                    <input type="checkbox" ?checked="${assigned_ids.includes(role.id)}"
                                        onchange="${(e) => this.toggleIAMRelation('role', userId, role.id, e.target.checked)}">
                                    <span>${role.role_name}</span>
                                </label>
                            `)}
                        </div>
                    </div>
                </div>
            `.toString();
            
            document.getElementById('modal-save').style.display = 'none';
            document.getElementById('modal-close').textContent = 'Close';

        } catch (err) {
            this.notify(err.message, 'error');
        }
    }

    async openRoleRightsEditor(roleId, roleName) {
        this.openModal(`Manage Rights: ${roleName}`, html`<div class="loader">Fetching permission table...</div>`.toString());
        
        try {
            const fd = new FormData();
            fd.append('action', 'get_iam_details');
            fd.append('type', 'roles');
            fd.append('id', roleId);

            const res = await SPPUX.apiPost(fd);
            if (!res.success) throw new Error(res.message);

            const { assigned_ids, available } = res.data;
            document.getElementById('modal-body').innerHTML = html`
                <div class="iam-management-grid">
                    <p class="mb-4 text-dim">Grant or revoke permissions for this role.</p>
                    <div class="glass-panel" style="padding: 1.5rem;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem;">
                            ${available.map(rt => html`
                                <label class="checkbox-item" style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                                    <input type="checkbox" style="margin-top: 4px;" ?checked="${assigned_ids.includes(rt.id)}"
                                        onchange="${(e) => this.toggleIAMRelation('right', roleId, rt.id, e.target.checked)}">
                                    <div>
                                        <div style="font-weight: 500;">${rt.name}</div>
                                        <div style="font-size: 0.75rem; color: var(--text-dim);">${rt.description || 'No description'}</div>
                                    </div>
                                </label>
                            `)}
                        </div>
                    </div>
                </div>
            `.toString();
            
            document.getElementById('modal-save').style.display = 'none';
        } catch (err) {
            this.notify(err.message, 'error');
        }
    }

    async toggleIAMRelation(type, targetId, relationId, isChecked) {
        const fd = new FormData();
        if (type === 'role') {
            fd.append('action', isChecked ? 'assign_role_to_entity' : 'remove_role_from_entity');
            fd.append('target_class', 'SPPMod\\SPPAuth\\SPPUser');
            fd.append('target_id', targetId);
            fd.append('role_id', relationId);
        } else {
            fd.append('action', isChecked ? 'assign_right_to_role' : 'remove_right_from_role');
            fd.append('role_id', targetId);
            fd.append('right_id', relationId);
        }

        const res = await SPPUX.apiPost(fd);
        if (res.success) {
            this.notify('Permission updated.', 'success');
        } else {
            this.handleApiErrors(res);
        }
    }

    async openMassUserAssignor(roleId, roleName) {
        this.openModal(`Assign Users to Role: ${roleName}`, html`
            <div class="mass-assignor">
                <p class="mb-4 text-dim">Search for users and add them to the selection list to assign this role in bulk.</p>
                <div class="form-group">
                    <label>Search Users</label>
                    <div style="position: relative;">
                        <input type="text" id="mass-search" class="spp-element" placeholder="Type username or email..." autocomplete="off">
                        <div id="mass-suggestions" class="search-suggestions-dropdown"></div>
                    </div>
                </div>
                <div class="form-group mt-4">
                    <label>Selected Users</label>
                    <div id="selected-users-list" class="glass-panel" style="min-height: 100px; padding: 1rem; display: flex; flex-wrap: wrap; gap: 8px;">
                        <span class="text-dim" style="font-size: 0.85rem;">No users selected yet.</span>
                    </div>
                </div>
            </div>
        `.toString());

        const searchInput = document.getElementById('mass-search');
        const suggestionsList = document.getElementById('mass-suggestions');
        const selectedList = document.getElementById('selected-users-list');
        const selectedIds = new Set();

        searchInput.oninput = async (e) => {
            const q = e.target.value.trim();
            if (q.length < 1) {
                suggestionsList.innerHTML = '';
                suggestionsList.classList.remove('active');
                return;
            }

            const fd = new FormData();
            fd.append('action', 'search_entities');
            fd.append('type', 'SPPMod\\SPPAuth\\SPPUser');
            fd.append('q', q);

            const res = await SPPUX.apiPost(fd);
            const results = (res.data && res.data.results) || res.data || [];

            if (res.success && results.length > 0) {
                suggestionsList.innerHTML = results.map(item => `
                    <div class="suggestion-item" data-id="${item.id}" data-name="${item.label || item.name}">
                        ${item.label || item.name} <small style="opacity:0.5">ID: ${item.id}</small>
                    </div>
                `).join('');
                suggestionsList.classList.add('active');

                // Attach click handlers to suggestions
                suggestionsList.querySelectorAll('.suggestion-item').forEach(el => {
                    el.onclick = () => {
                        const id = el.dataset.id;
                        const name = el.dataset.name;
                        if (!selectedIds.has(id)) {
                            selectedIds.add(id);
                            this.updateSelectedUsersUI(selectedList, selectedIds, name, id);
                        }
                        searchInput.value = '';
                        suggestionsList.innerHTML = '';
                        suggestionsList.classList.remove('active');
                    };
                });
            }
        };

        const saveBtn = document.getElementById('modal-save');
        saveBtn.style.display = 'block';
        saveBtn.textContent = 'Apply Assignments';
        saveBtn.onclick = async () => {
            if (selectedIds.size === 0) {
                this.notify('Select at least one user.', 'error');
                return;
            }
            
            const promises = Array.from(selectedIds).map(userId => {
                const fd = new FormData();
                fd.append('action', 'assign_role_to_entity');
                fd.append('target_class', 'SPPMod\\SPPAuth\\SPPUser');
                fd.append('target_id', userId);
                fd.append('role_id', roleId);
                return this.apiPost(fd);
            });

            await Promise.all(promises);
            this.notify(`Role ${roleName} assigned to ${selectedIds.size} users.`, 'success');
            this.closeModal();
            this.switchTab(this.state.activeTab, true);
        };
    }

    updateSelectedUsersUI(container, idSet, name, id) {
        if (idSet.size === 1 && container.querySelector('.text-dim')) {
            container.innerHTML = '';
        }

        const tag = document.createElement('div');
        tag.className = 'role-tag';
        tag.style.cssText = 'background: var(--primary-subtle); padding: 4px 10px; border-radius: 4px; font-size: 0.85rem; display: flex; align-items: center; gap: 8px;';
        tag.innerHTML = `<span>${name}</span> <span style="cursor:pointer; opacity:0.6;">✕</span>`;
        tag.querySelector('span:last-child').onclick = () => {
            idSet.delete(id);
            tag.remove();
            if (idSet.size === 0) container.innerHTML = '<span class="text-dim" style="font-size: 0.85rem;">No users selected yet.</span>';
        };
        container.appendChild(tag);
    }

    /**
     * toggleUserStatus: Asynchronously updates account active/inactive state.
     */
    async toggleUserStatus(userId, currentStatus) {
        const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
        
        // 1. Optimistic Update in State
        const updatedItems = this.state.items.map(user => {
            if (user.id === userId) return { ...user, status: newStatus };
            return user;
        });
        this.setState({ items: updatedItems });

        try {
            const fd = new FormData();
            fd.append('action', 'toggle_user_status');
            fd.append('id', userId);
            fd.append('status', newStatus);

            const res = await SPPUX.apiPost(fd);
            if (res.success) {
                this.notify(`User status updated to ${newStatus}.`, 'success');
            } else {
                throw new Error(res.message || 'Update failed');
            }
        } catch (err) {
            // 2. Rollback on failure
            this.notify(`Failed to update status: ${err.message}`, 'error');
            const rolledBackItems = this.state.items.map(user => {
                if (user.id === userId) return { ...user, status: currentStatus };
                return user;
            });
            this.setState({ items: rolledBackItems });
        }
    }

    async openModernRoleEditor(slug = '', permissions = []) {
        const title = slug ? `Edit Modern Role: ${slug}` : 'Create Modern Role';
        this.openModal(title, html`
            <form id="modern-role-form">
                <div class="form-group">
                    <label>Role Slug (Registry Key)</label>
                    <input type="text" name="slug" class="spp-element" value="${slug}" ${slug ? 'readonly' : ''} placeholder="e.g. editor, manager">
                </div>
                <div class="form-group mt-4">
                    <label>Permissions (Atomic Identifiers)</label>
                    <textarea name="permissions" class="spp-element" style="height: 150px;" placeholder="One permission per line, e.g.\nposts.create\nposts.edit\n*">${permissions.join('\n')}</textarea>
                    <small class="text-dim">Use '*' for super-admin access.</small>
                </div>
            </form>
        `.toString());

        const saveBtn = document.getElementById('modal-save');
        saveBtn.textContent = 'Save Role';
        saveBtn.onclick = () => this.saveModernRole();
    }

    async saveModernRole() {
        const form = document.getElementById('modern-role-form');
        const fd = new FormData(form);
        
        // Convert newline-separated textarea to array
        const permsText = fd.get('permissions');
        const permsArray = permsText.split('\n').map(p => p.trim()).filter(p => p.length > 0);
        
        const apiFd = new FormData();
        apiFd.append('action', 'save_rbac_role');
        apiFd.append('slug', fd.get('slug'));
        permsArray.forEach(p => apiFd.append('permissions[]', p));

        const res = await this.apiPost(apiFd);
        if (res.success) {
            this.notify('Modern role saved to Registry.', 'success');
            this.closeModal();
            this.switchTab('modern_rbac', true);
        } else {
            this.handleApiErrors(res);
        }
    }
}
