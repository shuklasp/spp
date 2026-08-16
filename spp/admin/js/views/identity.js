/**
 * IdentityView Component
 * 
 * Unified Identity & Access Management module.
 * Merges GroupsView (group management, polymorphic membership) and
 * AccessView (Users, Roles, Rights, Modern RBAC, Assignments) into
 * a single component with top-level tabs.
 */
export default class IdentityView extends BaseComponent {
    async onInit() {
        this.state = {
            // --- Top-level tab ---
            activeMainTab: localStorage.getItem('spp_admin_identity_main_tab') || 'groups',

            // --- Shared ---
            loading: true,
            error: null,

            // --- Groups state ---
            groupSources: [],
            searchQuery: '',
            searchResults: [],
            currentGroupId: null,
            currentMembers: [],

            // --- IAM (Access) state ---
            iamActiveTab: localStorage.getItem('spp_admin_iam_tab') || 'users',
            iamSources: [],
            items: [],
            page: 1,
            pageSize: 10,
            filters: {}
        };

        // Load data for whichever main tab is active
        if (this.state.activeMainTab === 'groups') {
            await this.fetchGroupData();
        } else {
            await this.switchIamTab(this.state.iamActiveTab, true);
        }
    }

    // =========================================================================
    //  TOP-LEVEL TAB SWITCHING
    // =========================================================================

    async switchMainTab(tab) {
        if (this.state.activeMainTab === tab) return;

        localStorage.setItem('spp_admin_identity_main_tab', tab);
        this.setState({ activeMainTab: tab, loading: true, error: null });

        if (tab === 'groups') {
            await this.fetchGroupData();
        } else {
            await this.switchIamTab(this.state.iamActiveTab, true);
        }
    }

    // =========================================================================
    //  GROUPS: DATA LOADING
    // =========================================================================

    async fetchGroupData() {
        try {
            const res = await this.api('list_groups');
            if (res.success) {
                this.setState({
                    groupSources: res.data.sources || [],
                    loading: false
                });
            } else {
                throw new Error(res.message);
            }
        } catch (err) {
            this.setState({ loading: false, error: err.message });
        }
    }

    // =========================================================================
    //  IAM: TAB SWITCHING & DATA LOADING  (was AccessView.switchTab)
    // =========================================================================

    async switchIamTab(tab, force = false) {
        if (!force && this.state.iamActiveTab === tab) return;

        this.setState({
            iamActiveTab: tab,
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
            if (tab === 'abac') action = 'list_abac_policies';

            const res = await this.api(action);
            if (res.success) {
                let sources = res.data.sources || [];

                if (tab === 'assignments') {
                    sources = [{
                        label: 'Database (IAM Relations)',
                        type: 'database',
                        items: res.data || []
                    }];
                }

                // Normalize Modern RBAC (object map to array within sources)
                if (tab === 'modern_rbac') {
                    sources.forEach(src => {
                        if (!Array.isArray(src.items)) {
                            src.items = Object.entries(src.items).map(([slug, data]) => ({
                                slug,
                                permissions: data.permissions || [],
                                ...data
                            }));
                        }
                    });
                }

                this.setState({
                    iamSources: sources,
                    loading: false
                });
            } else {
                throw new Error(res.message);
            }
        } catch (err) {
            this.setState({ loading: false, error: err.message });
        }
    }

    // =========================================================================
    //  MAIN RENDER
    // =========================================================================

    render() {
        const { loading, error, activeMainTab } = this.state;

        // Update Header
        const headerActions = document.getElementById('header-actions');
        if (headerActions) {
            const headerHtml = html`
                <div class="spp-tabs" style="margin-right: 1rem; display: inline-flex;">
                    <div class="tab ${activeMainTab === 'groups' ? 'active' : ''}" @click=${() => this.switchMainTab('groups')}>Groups</div>
                    <div class="tab ${activeMainTab === 'access' ? 'active' : ''}" @click=${() => this.switchMainTab('access')}>Access Control (IAM)</div>
                </div>
                ${activeMainTab === 'groups' ? html`<button type="button" class="btn primary-btn btn-sm" @click=${() => this.openCreateModal()}>+ Create Group</button>` : ''}
            `;
            headerActions.innerHTML = headerHtml.toString();

            // Re-attach listeners for the tabs since we bypassed lit-html events
            const tabs = headerActions.querySelectorAll('.tab');
            if (tabs[0]) tabs[0].onclick = () => this.switchMainTab('groups');
            if (tabs[1]) tabs[1].onclick = () => this.switchMainTab('access');
            const btn = headerActions.querySelector('.primary-btn');
            if (btn) btn.onclick = () => this.openCreateModal();
        }

        if (activeMainTab === 'access') {
            return this.renderAccess();
        }

        // --- Groups tab ---
        return this.renderGroups();
    }

    // =========================================================================
    //  GROUPS: RENDER
    // =========================================================================

    renderGroups() {
        const { loading, groupSources, error } = this.state;

        if (loading) return html`<div class="loading-state">Loading group infrastructure...</div>`;
        if (error) return html`<div class="empty-state"><h3>Error</h3><p>${error}</p></div>`;

        if (groupSources.length === 0) {
            return html`
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <h3>No Groups Found</h3>
                    <p>Groups allow you to manage permissions for multiple entities at once.</p>
                </div>
            `;
        }

        return html`
            <div class="sources-wrap">
                ${groupSources.map(source => html`
                    <div class="source-group-container">
                        ${this.renderSourceHeader(source)}
                        
                        <div class="card-grid">
                            ${source.items.map((g, i) => html`
                                <div class="item-card glass-panel" style="animation-delay: ${i * 0.05}s">
                                    <div class="card-header">
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <div class="user-avatar-sm" style="background: var(--accent-gradient)">👥</div>
                                            <div>
                                                <h3>${g.name}</h3>
                                                <div class="card-meta">${g.description || 'Global Framework Group'}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <small>ID: ${g.id}</small>
                                        <div class="card-actions">
                                            <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.manageMembers(g.id, g.name)}>Manage Members</button>
                                            <button type="button" class="btn danger-btn btn-sm" @click=${() => this.confirmDelete('group', g.id)}>Delete</button>
                                        </div>
                                    </div>
                                </div>
                            `)}
                        </div>
                    </div>
                `)}
            </div>
        `;
    }

    // =========================================================================
    //  GROUPS: MEMBER MANAGEMENT
    // =========================================================================

    async manageMembers(groupId, groupName) {
        this.state.currentGroupId = groupId;
        this.state.searchResults = [];
        this.state.searchQuery = '';
        this.state.currentMembers = [];

        this.openModal(`Manage Members: ${groupName}`, this.getManagementHtml(), [
            { label: 'Close', type: 'secondary', fn: () => this.closeModal() }
        ]);

        await this.loadMembers(groupId);
    }

    getManagementHtml() {
        const { searchResults, searchQuery, currentMembers, currentGroupId } = this.state;
        return html`
            <div class="group-mgmt-wrap">
                <div class="search-box mb-4">
                    <label>Add New Member</label>
                    <div class="member-search-container" style="position: relative;">
                        <input type="text" id="member-search-input" placeholder="Search Users, Staff, Students..." 
                            class="spp-element" value="${searchQuery}" @input=${(e) => this.handleMemberSearch(e, currentGroupId)}>
                        
                        <div id="member-suggestions" class="suggestions-list search-suggestions-dropdown ${searchResults.length > 0 ? 'active' : ''}">
                            ${searchResults.map(item => html`
                                <div class="suggestion-item" @click=${() => this.promptAddMember(currentGroupId, item.entity, item.id, item.name)}>
                                    <div class="suggestion-core">
                                        <span class="icon">${item.icon || '👤'}</span>
                                        <div class="suggestion-info">
                                            <strong>${item.name}</strong>
                                            <div class="type-label">${item.type || 'Entity'}</div>
                                        </div>
                                    </div>
                                    <span class="add-plus">＋ Add</span>
                                </div>
                            `)}
                        </div>
                    </div>
                </div>
                <div id="current-members-list">
                    ${currentMembers.length === 0 ? html`<div class="loader">Fetching members...</div>` : this.getMembersListHtml()}
                </div>
            </div>
        `;
    }

    getMembersListHtml() {
        const { currentMembers, currentGroupId } = this.state;
        if (currentMembers.length === 0) return html`<div class="empty-mini">No members yet.</div>`;

        return html`
            <div class="member-list-mini">
                <label>Current Members (${currentMembers.length})</label>
                ${currentMembers.map(m => html`
                    <div class="member-mini-item ${m.direct ? 'direct' : 'inherited'}">
                        <div class="member-core">
                            <span class="icon">${m.entity.includes('User') ? '👤' : (m.entity.includes('Group') ? '👥' : '🏷️')}</span>
                            <div class="member-meta">
                                <div class="name">${m.name} ${!m.direct ? html`<span class="inherited-label">Inherited</span>` : ''}</div>
                                <div class="role">${m.role || 'member'} ${!m.direct ? html`<small>via ${m.inherited_via}</small>` : ''}</div>
                            </div>
                        </div>
                        ${m.direct ? html`
                            <button type="button" class="remove-btn" @click=${() => this.removeMember(currentGroupId, m.entity, m.id, m.name)}>✕</button>
                        ` : html`<span class="lock">🔒</span>`}
                    </div>
                `)}
            </div>
        `;
    }

    refreshMemberModal() {
        const { groupSources, currentGroupId } = this.state;
        let group = null;
        for (const s of groupSources) {
            group = s.items.find(g => g.id === currentGroupId);
            if (group) break;
        }
        const title = group ? `Manage Members: ${group.name}` : 'Manage Members';

        this.updateModal(title, this.getManagementHtml());
    }

    async handleMemberSearch(e, groupId) {
        const q = e.target.value.trim();
        this.state.searchQuery = q;

        if (q.length < 1) {
            this.state.searchResults = [];
            this.refreshMemberModal();
            return;
        }

        try {
            const fd = new FormData();
            fd.append('action', 'search_entities');
            fd.append('q', q);
            const res = await this.apiPost(fd);

            this.state.searchResults = res.data?.results || res.results || [];
            this.refreshMemberModal();
        } catch (err) {
            console.error('Group search error:', err);
        }
    }

    async promptAddMember(groupId, entityClass, entityId, name) {
        try {
            console.log(`IdentityView: Fast-Add requested for ${name} into Group ${groupId}`);

            // Post-input UI clearing immediately
            const dropdown = document.getElementById('member-suggestions');
            if (dropdown) dropdown.classList.remove('active');
            const input = document.getElementById('member-search-input');
            if (input) input.value = '';

            const fd = new FormData();
            fd.append('action', 'add_group_member');
            fd.append('group_id', groupId);
            fd.append('member_entity', entityClass);
            fd.append('member_id', entityId);
            fd.append('role', 'member'); // Default role for immediate action

            this.notify(`Adding ${name}...`, 'info');
            const res = await this.apiPost(fd);
            console.log("IdentityView: add_group_member response:", res);

            if (res.success) {
                this.notify(`Successfully added ${name}.`, 'success');
                await this.loadMembers(groupId);
            } else {
                this.notify(res.message || "Failed to add member.", 'error');
            }
        } catch (err) {
            console.error("IdentityView: Error in promptAddMember:", err);
            this.notify("An unexpected error occurred during addition.", "error");
        }
    }

    async loadMembers(groupId) {
        try {
            const res = await this.api(`list_group_members&group_id=${groupId}`);
            if (res.success) {
                this.state.currentMembers = res.data.members || [];
                this.refreshMemberModal();
            }
        } catch (err) {
            this.notify(`Error loading members: ${err.message}`, 'error');
        }
    }

    async removeMember(groupId, entityClass, entityId, name) {
        try {
            console.log(`IdentityView: Removal requested for ${name} (${entityClass}:${entityId}) from Group ${groupId}`);

            if (!confirm(`Remove '${name}' from this group?`)) return;

            const fd = new FormData();
            fd.append('action', 'remove_group_member');
            fd.append('group_id', groupId);
            fd.append('member_entity', entityClass);
            fd.append('member_id', entityId);

            this.notify(`Removing ${name}...`, 'info');
            const res = await this.apiPost(fd);
            console.log("IdentityView: remove_group_member response:", res);

            if (res.success) {
                this.notify(`Successfully removed ${name}.`, 'success');
                await this.loadMembers(groupId);
            } else {
                this.notify(res.message || "Failed to remove member.", 'error');
            }
        } catch (err) {
            console.error("IdentityView: Error in removeMember:", err);
            this.notify("An unexpected error occurred during removal.", "error");
        }
    }

    // =========================================================================
    //  GROUPS: CREATE / SAVE
    // =========================================================================

    async openCreateModal() {
        const content = html`
            <div class="spp-form-modern">
                <div class="form-group">
                    <label>Group Name</label>
                    <input type="text" id="new-group-name" class="spp-element" placeholder="e.g. Administrators">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="new-group-desc" class="spp-element" placeholder="What is this group for?"></textarea>
                </div>
            </div>
        `;

        this.openModal('Create New Group', content, [
            { label: 'Cancel', type: 'secondary', fn: () => this.closeModal() },
            { label: 'Create Group', type: 'primary', fn: () => this.saveGroup() }
        ]);
    }

    async saveGroup() {
        const name = document.getElementById('new-group-name').value.trim();
        const description = document.getElementById('new-group-desc').value.trim();

        if (!name) {
            this.notify('Group name is required.', 'error');
            return;
        }

        try {
            const fd = new FormData();
            fd.append('action', 'save_group');
            fd.append('name', name);
            fd.append('description', description);

            const res = await this.apiPost(fd);
            if (res.success) {
                this.notify('Group created successfully.', 'success');
                this.closeModal();
                await this.fetchGroupData();
            } else {
                this.notify(res.message || 'Failed to create group.', 'error');
            }
        } catch (err) {
            this.notify(`Error: ${err.message}`, 'error');
        }
    }

    // =========================================================================
    //  IAM (ACCESS): RENDER
    // =========================================================================

    renderAccess() {
        const { loading, iamActiveTab, iamSources, error } = this.state;

        // Update Header with IAM-specific "+ New" button
        const headerActions = document.getElementById('header-actions');
        if (headerActions) {
            // The top-level tabs are already rendered by render(), but when on
            // 'access' we also need the "+ New ..." button.
            // We re-render the full header to include both top-level tabs and IAM action button.
            const headerHtml = html`
                <div class="spp-tabs" style="margin-right: 1rem; display: inline-flex;">
                    <div class="tab ${this.state.activeMainTab === 'groups' ? 'active' : ''}" @click=${() => this.switchMainTab('groups')}>Groups</div>
                    <div class="tab ${this.state.activeMainTab === 'access' ? 'active' : ''}" @click=${() => this.switchMainTab('access')}>Access Control (IAM)</div>
                </div>
            `;
            headerActions.innerHTML = headerHtml.toString();

            // Re-attach top-level tab listeners
            const tabs = headerActions.querySelectorAll('.tab');
            if (tabs[0]) tabs[0].onclick = () => this.switchMainTab('groups');
            if (tabs[1]) tabs[1].onclick = () => this.switchMainTab('access');

            // Add IAM-specific action button
            const btn = document.createElement('button');
            btn.className = 'btn primary-btn btn-sm';
            if (iamActiveTab === 'assignments') {
                btn.innerHTML = '+ New Assignment';
                btn.onclick = () => this.openAssignmentEditor();
            } else if (iamActiveTab === 'modern_rbac') {
                btn.innerHTML = '+ New Modern Role';
                btn.onclick = () => this.openModernRoleEditor();
            } else {
                btn.innerHTML = `+ New ${iamActiveTab.slice(0, -1).charAt(0).toUpperCase() + iamActiveTab.slice(1, -1)}`;
                btn.onclick = () => this.openEditor(iamActiveTab);
            }
            headerActions.appendChild(btn);
        }

        return html`
            <div class="iam-workspace">
                <div class="tab-bar-secondary mb-4">
                    <button class="sub-tab-btn ${iamActiveTab === 'users' ? 'active' : ''}" @click=${() => this.switchIamTab('users')}>👥 Users</button>
                    <button class="sub-tab-btn ${iamActiveTab === 'roles' ? 'active' : ''}" @click=${() => this.switchIamTab('roles')}>🛡️ Legacy Roles</button>
                    <button class="sub-tab-btn ${iamActiveTab === 'rights' ? 'active' : ''}" @click=${() => this.switchIamTab('rights')}>🔑 Legacy Rights</button>
                    <button class="sub-tab-btn ${iamActiveTab === 'modern_rbac' ? 'active' : ''}" @click=${() => this.switchIamTab('modern_rbac')}>⚡ Modern RBAC</button>
                    <button class="sub-tab-btn ${iamActiveTab === 'assignments' ? 'active' : ''}" @click=${() => this.switchIamTab('assignments')}>🔗 Assignments</button>
                    <button class="sub-tab-btn ${iamActiveTab === 'abac' ? 'active' : ''}" @click=${() => this.switchIamTab('abac')}>📜 ABAC Policies</button>
                    <button class="sub-tab-btn ${iamActiveTab === 'oauth' ? 'active' : ''}" @click=${() => this.switchIamTab('oauth')}>🔌 OAuth Clients</button>
                </div>

                <div id="iam-content">
                    ${loading ? html`<div class="loading-state">Syncing security manifests...</div>` : ''}
                    ${error ? html`<div class="alert error">${error}</div>` : ''}
                    
                    ${!loading && !error ? this.renderIamTabContent() : ''}
                </div>
            </div>
        `;
    }

    // =========================================================================
    //  IAM: TAB CONTENT RENDERING
    // =========================================================================

    renderIamTabContent() {
        const { iamActiveTab, iamSources, filters, page, pageSize } = this.state;

        if (iamSources.length === 0) {
            return html`
                <div class="empty-state">
                    <div class="empty-icon">🛡️</div>
                    <h3>No Records Found</h3>
                    <p>Start by creating your first security entity in this context.</p>
                </div>
            `;
        }

        return html`
            <div class="sources-wrap">
                ${iamSources.map(source => {
            // 1. Apply Filtering to source items
            const filteredItems = source.items.filter(item => {
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

            return html`
                        <div class="source-group-container">
                            ${this.renderSourceHeader(source)}
                            
                            ${iamActiveTab === 'assignments'
                    ? this.renderAssignmentsTable(pagedItems, totalItems, totalPages)
                    : (iamActiveTab === 'modern_rbac'
                        ? this.renderModernRbacTable(pagedItems, totalItems, totalPages)
                        : (iamActiveTab === 'abac'
                            ? this.renderAbacTable(pagedItems, totalItems, totalPages)
                            : (iamActiveTab === 'oauth'
                                ? this.renderOAuthTable(pagedItems, totalItems, totalPages)
                                : this.renderStandardTable(pagedItems, totalItems, totalPages)
                            )
                        )
                    )
                }
                        </div>
                    `;
        })}
            </div>
        `;
    }

    // =========================================================================
    //  IAM: TABLE RENDERERS
    // =========================================================================

    renderModernRbacTable(items, totalItems, totalPages) {
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
        const { iamActiveTab, filters, page } = this.state;
        const columns = this.getTableColumns(iamActiveTab);

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
                    if (iamActiveTab === 'users') {
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
                                            ${iamActiveTab === 'users' ? html`
                                                <button class="btn ghost-btn btn-sm" onclick="${() => this.openUserRolesEditor(item.id, title)}">Roles</button>
                                            ` : ''}
                                            ${iamActiveTab === 'roles' ? html`
                                                <button class="btn ghost-btn btn-sm" onclick="${() => this.openMassUserAssignor(item.id, title)}">Users</button>
                                                <button class="btn ghost-btn btn-sm" onclick="${() => this.openRoleRightsEditor(item.id, title)}">Rights</button>
                                            ` : ''}
                                            <button class="btn ghost-btn btn-sm" onclick="${() => this.openEditor(iamActiveTab, item.id, title)}">Edit</button>
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
    renderAbacTable(items, totalItems, totalPages) {
        const { filters } = this.state;
        return html`
            <div class="glass-panel">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Permission</th>
                            <th>Status</th>
                            <th>Condition Logic (JSON)</th>
                            <th class="text-right">Actions</th>
                        </tr>
                        <tr class="filter-row">
                            <th><input type="text" class="table-filter" placeholder="Filter..." value="${filters.permission || ''}" oninput="${(e) => this.updateFilter('permission', e.target.value)}"></th>
                            <th></th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${items.map(p => {
            return html`
                                <tr>
                                    <td><code>${p.permission}</code></td>
                                    <td><span class="badge ${p.status === 'active' ? 'success' : 'warning'}">${p.status}</span></td>
                                    <td><pre style="max-width: 300px; max-height: 100px; overflow: auto; margin:0; padding: 4px; font-size: 0.75rem;">${p.condition_logic}</pre></td>
                                    <td class="text-right">
                                        <button class="btn ghost-btn btn-sm" onclick="${() => this.openAbacEditor(p.id)}">Edit</button>
                                        <button class="btn danger-btn btn-sm" onclick="${() => this.deleteAbacPolicy(p.id)}">Delete</button>
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

    renderOAuthTable(items, totalItems, totalPages) {
        const { filters } = this.state;
        return html`
            < div class="glass-panel" >
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Client ID</th>
                            <th>Name</th>
                            <th>Redirect URI</th>
                            <th class="text-right">Actions</th>
                        </tr>
                        <tr class="filter-row">
                            <th><input type="text" class="table-filter" placeholder="Filter..." value="${filters.id || ''}" oninput="${(e) => this.updateFilter('id', e.target.value)}"></th>
                            <th><input type="text" class="table-filter" placeholder="Filter..." value="${filters.name || ''}" oninput="${(e) => this.updateFilter('name', e.target.value)}"></th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${items.map(client => {
                            return html`
                                <tr>
                                    <td><code>${client.id}</code></td>
                                    <td><strong>${client.name}</strong></td>
                                    <td><code>${client.redirect_uri}</code></td>
                                    <td class="text-right">
                                        <button class="btn ghost-btn btn-sm" onclick="${() => this.openOAuthEditor(client.id)}">Edit</button>
                                        <button class="btn danger-btn btn-sm" onclick="${() => this.deleteOAuthClient(client.id)}">Delete</button>
                                    </td>
                                </tr>
                            `;
                        })}
                    </tbody>
                </table>
            </div >
            ${ this.renderPagination(totalItems, totalPages) }
        `;
    }

    // =========================================================================
    //  IAM: HELPERS
    // =========================================================================

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
            < div class="pagination-bar" >
                <div class="pagination-info">
                    Showing <strong>${(page - 1) * pageSize + 1}</strong> to <strong>${Math.min(page * pageSize, total)}</strong> of <strong>${total}</strong> records
                </div>
                <div class="pagination-controls">
                    <button class="page-btn" ?disabled="${page === 1}" onclick="${() => this.setState({ page: page - 1 })}">«</button>
                    ${
            pages.map(p => html`
                        <button class="page-btn ${page === p ? 'active' : ''}" onclick="${() => this.setState({ page: p })}">${p}</button>
                    `)
        }
        <button class="page-btn" ?disabled="${page === totalPages}" onclick="${() => this.setState({ page: page + 1 })}">»</button>
                </div >
            </div >
            `;
    }

    updateFilter(key, val) {
        const newFilters = { ...this.state.filters };
        if (val) newFilters[key] = val;
        else delete newFilters[key];
        
        this.setState({ filters: newFilters, page: 1 });
    }

    // =========================================================================
    //  IAM: EDITOR / SAVE (Users, Roles, Rights)
    // =========================================================================

    async openEditor(type, id = null, name = '') {
        const title = id ? `Edit ${ type.slice(0, -1) }: ${ name } ` : `Create New ${ type.slice(0, -1) } `;
        this.openModal(title, html`< div class="loader" > Fetching framework form for ${ type }...</div > `.toString());

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
            < div class="spp-form-wrapper" >
                ${ res.data.html }
                    </div >
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
            this.switchIamTab(type, true);
        } else {
            this.handleApiErrors(res);
        }
    }

    // =========================================================================
    //  IAM: ASSIGNMENTS
    // =========================================================================

    async openAssignmentEditor(targetClass = null, targetId = null) {
        this.openModal(targetId ? 'Edit Role Assignment' : 'New Role Assignment', html`< div class="loader" > Preparing assignment form...</div > `.toString());
        
        try {
            const rolesRes = await this.api.listRoles();
            if (!rolesRes.success) throw new Error(rolesRes.message);

            // Fetch current roles if editing
            let assignedRoleIds = [];
            if (targetClass && targetId) {
                const detailsFd = new FormData();
                detailsFd.append('action', 'get_iam_details');
                detailsFd.append('type', targetClass.includes('SPPUser') ? 'users' : 'roles');
                if (targetClass.includes('SPPUser')) {
                    detailsFd.append('id', targetId);
                    const detRes = await SPPUX.apiPost(detailsFd);
                    if (detRes.success) assignedRoleIds = detRes.data.assigned_ids || [];
                }
            }

            document.getElementById('modal-body').innerHTML = html`
            < form id = "assignment-form" class="assignment-form" >
                    <div class="form-group">
                        <label>1. Select Entity Type</label>
                        <select name="target_class" id="asgn-class" class="spp-element" ${targetId ? 'disabled' : ''}>
                            <option value="SPPMod\\SPPAuth\\SPPUser" ${targetClass === 'SPPMod\\SPPAuth\\SPPUser' ? 'selected' : ''}>User</option>
                            <option value="SPPMod\\SPPAuth\\SPPGroup" ${targetClass === 'SPPMod\\SPPAuth\\SPPGroup' ? 'selected' : ''}>Group</option>
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
            < div class="suggestion-item" onclick = "document.getElementById('asgn-search').value='${item.label || item.name}'; document.getElementById('asgn-id').value='${item.id}'; document.getElementById('asgn-suggestions').innerHTML=''; document.getElementById('asgn-suggestions').classList.remove('active');" >
                ${ item.label || item.name } <small style="opacity:0.5">(ID: ${item.id})</small>
                        </div >
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
            document.getElementById('modal-body').innerHTML = html`< div class="alert error" > ${ err.message }</div > `.toString();
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
            this.switchIamTab('assignments', true);
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
            this.switchIamTab('assignments', true);
        } else {
            this.handleApiErrors(res);
        }
    }

    // =========================================================================
    //  IAM: USER ROLES EDITOR
    // =========================================================================

    async openUserRolesEditor(userId, userName) {
        this.openModal(`Manage Roles: ${ userName } `, html` < div class="loader" > Fetching role manifest...</div > `.toString());
        
        try {
            const fd = new FormData();
            fd.append('action', 'get_iam_details');
            fd.append('type', 'users');
            fd.append('id', userId);

            const res = await SPPUX.apiPost(fd);
            if (!res.success) throw new Error(res.message);

            const { assigned_ids, available } = res.data;
            document.getElementById('modal-body').innerHTML = html`
            < div class="iam-management-grid" >
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
                </div >
            `.toString();
            
            document.getElementById('modal-save').style.display = 'none';
            document.getElementById('modal-close').textContent = 'Close';

        } catch (err) {
            this.notify(err.message, 'error');
        }
    }

    // =========================================================================
    //  IAM: ROLE RIGHTS EDITOR
    // =========================================================================

    async openRoleRightsEditor(roleId, roleName) {
        this.openModal(`Manage Rights: ${ roleName } `, html` < div class="loader" > Fetching permission table...</div > `.toString());
        
        try {
            const fd = new FormData();
            fd.append('action', 'get_iam_details');
            fd.append('type', 'roles');
            fd.append('id', roleId);

            const res = await SPPUX.apiPost(fd);
            if (!res.success) throw new Error(res.message);

            const { assigned_ids, available } = res.data;
            document.getElementById('modal-body').innerHTML = html`
            < div class="iam-management-grid" >
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
                </div >
            `.toString();
            
            document.getElementById('modal-save').style.display = 'none';
        } catch (err) {
            this.notify(err.message, 'error');
        }
    }

    // =========================================================================
    //  IAM: TOGGLE RELATION (Role ↔ User, Right ↔ Role)
    // =========================================================================

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

    // =========================================================================
    //  IAM: MASS USER ASSIGNOR
    // =========================================================================

    async openMassUserAssignor(roleId, roleName) {
        this.openModal(`Assign Users to Role: ${ roleName } `, html`
            < div class="mass-assignor" >
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
            </div >
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
            < div class="suggestion-item" data - id="${item.id}" data - name="${item.label || item.name}" >
                ${ item.label || item.name } <small style="opacity:0.5">ID: ${item.id}</small>
                    </div >
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
            this.notify(`Role ${ roleName } assigned to ${ selectedIds.size } users.`, 'success');
            this.closeModal();
            this.switchIamTab(this.state.iamActiveTab, true);
        };
    }

    updateSelectedUsersUI(container, idSet, name, id) {
        if (idSet.size === 1 && container.querySelector('.text-dim')) {
            container.innerHTML = '';
        }

        const tag = document.createElement('div');
        tag.className = 'role-tag';
        tag.style.cssText = 'background: var(--primary-subtle); padding: 4px 10px; border-radius: 4px; font-size: 0.85rem; display: flex; align-items: center; gap: 8px;';
        tag.innerHTML = `< span > ${ name }</span > <span style="cursor:pointer; opacity:0.6;">✕</span>`;
        tag.querySelector('span:last-child').onclick = () => {
            idSet.delete(id);
            tag.remove();
            if (idSet.size === 0) container.innerHTML = '<span class="text-dim" style="font-size: 0.85rem;">No users selected yet.</span>';
        };
        container.appendChild(tag);
    }

    // =========================================================================
    //  IAM: USER STATUS TOGGLE
    // =========================================================================

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
                this.notify(`User status updated to ${ newStatus }.`, 'success');
            } else {
                throw new Error(res.message || 'Update failed');
            }
        } catch (err) {
            // 2. Rollback on failure
            this.notify(`Failed to update status: ${ err.message } `, 'error');
            const rolledBackItems = this.state.items.map(user => {
                if (user.id === userId) return { ...user, status: currentStatus };
                return user;
            });
            this.setState({ items: rolledBackItems });
        }
    }

    // =========================================================================
    //  IAM: MODERN RBAC ROLE EDITOR
    // =========================================================================

    async openModernRoleEditor(slug = '', permissions = []) {
        const title = slug ? `Edit Modern Role: ${ slug } ` : 'Create Modern Role';
        this.openModal(title, html`
            < form id = "modern-role-form" >
                <div class="form-group">
                    <label>Role Slug (Registry Key)</label>
                    <input type="text" name="slug" class="spp-element" value="${slug}" ${slug ? 'readonly' : ''} placeholder="e.g. editor, manager">
                </div>
                <div class="form-group mt-4">
                    <label>Permissions (Atomic Identifiers)</label>
                    <textarea name="permissions" class="spp-element" style="height: 150px;" placeholder="One permission per line, e.g.\nposts.create\nposts.edit\n*">${permissions.join('\n')}</textarea>
                    <small class="text-dim">Use '*' for super-admin access.</small>
                </div>
            </form >
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
            this.switchIamTab('modern_rbac', true);
        } else {
            this.handleApiErrors(res);
        }
    }

    // =========================================================================
    //  IAM: ABAC POLICY EDITOR
    // =========================================================================

    async openAbacEditor(id = null) {
        const title = id ? 'Edit ABAC Policy' : 'Create ABAC Policy';
        this.openModal(title, html`< div class="loader" > Fetching...</div > `.toString());

        let policy = { permission: '', condition_logic: '{\n  "field": "user.id",\n  "operator": "equals",\n  "value": "context.owner_id"\n}', status: 'active' };

        if (id) {
            const item = this.state.items.find(i => i.id == id);
            if (item) policy = item;
        }

        document.getElementById('modal-body').innerHTML = html`
            < form id = "abac-policy-form" >
                <input type="hidden" name="id" value="${id || ''}">
                    <div class="form-group">
                        <label>Permission</label>
                        <input type="text" name="permission" class="spp-element" value="${policy.permission}" placeholder="e.g. content.edit">
                    </div>
                    <div class="form-group mt-4">
                        <label>Condition Logic (JSON)</label>
                        <textarea name="condition_logic" class="spp-element" style="height: 150px; font-family: monospace;">${policy.condition_logic}</textarea>
                    </div>
                    <div class="form-group mt-4">
                        <label>Status</label>
                        <select name="status" class="spp-element">
                            <option value="active" ${policy.status === 'active' ? 'selected' : ''}>Active</option>
                            <option value="inactive" ${policy.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                        </select>
                    </div>
                </form>
        `.toString();

        const saveBtn = document.getElementById('modal-save');
        saveBtn.textContent = 'Save Policy';
        saveBtn.onclick = () => this.saveAbacPolicy();
    }

    async saveAbacPolicy() {
        const form = document.getElementById('abac-policy-form');
        const fd = new FormData(form);
        
        try {
            JSON.parse(fd.get('condition_logic'));
        } catch(e) {
            this.notify('Invalid JSON in Condition Logic.', 'error');
            return;
        }
        
        fd.append('action', 'save_abac_policy');

        const res = await this.apiPost(fd);
        if (res.success) {
            this.notify('ABAC Policy saved.', 'success');
            this.closeModal();
            this.switchIamTab('abac', true);
        } else {
            this.handleApiErrors(res);
        }
    }

    async deleteAbacPolicy(id) {
        if (!confirm('Delete this ABAC policy?')) return;

        const fd = new FormData();
        fd.append('action', 'delete_abac_policy');
        fd.append('id', id);

        const res = await this.apiPost(fd);
        if (res.success) {
            this.notify('ABAC Policy deleted.', 'success');
            this.switchIamTab('abac', true);
        } else {
            this.handleApiErrors(res);
        }
    }

    // =========================================================================
    //  IAM: OAUTH CLIENT EDITOR
    // =========================================================================

    async openOAuthEditor(id = null) {
        const title = id ? 'Edit OAuth Client' : 'Create OAuth Client';
        this.openModal(title, html`< div class="loader" > Fetching...</div > `.toString());

        let client = { id: '', name: '', redirect_uri: '' };

        if (id) {
            const item = this.state.items.find(i => i.id == id);
            if (item) client = item;
        } else {
            // Generate random client ID for new clients
            client.id = 'client_' + Math.random().toString(36).substr(2, 9);
        }

        document.getElementById('modal-body').innerHTML = html`
            < form id = "oauth-client-form" >
                <div class="form-group">
                    <label>Client ID</label>
                    <input type="text" name="id" class="spp-element" value="${client.id}" ${id ? 'readonly' : ''}>
                </div>
                <div class="form-group mt-4">
                    <label>Application Name</label>
                    <input type="text" name="name" class="spp-element" value="${client.name}" placeholder="e.g. Acme App">
                </div>
                <div class="form-group mt-4">
                    <label>Redirect URI</label>
                    <input type="text" name="redirect_uri" class="spp-element" value="${client.redirect_uri}" placeholder="e.g. https://app.example.com/callback">
                </div>
                ${
            !id ? html`
                <div class="alert info mt-4">
                    <p>A secure <code>client_secret</code> will be generated automatically and displayed after creation.</p>
                </div>
                ` : ''
        }
            </form >
            `.toString();

        const saveBtn = document.getElementById('modal-save');
        saveBtn.textContent = 'Save Client';
        saveBtn.onclick = () => this.saveOAuthClient();
    }

    async saveOAuthClient() {
        const form = document.getElementById('oauth-client-form');
        const fd = new FormData(form);
        
        fd.append('action', 'save_oauth_client');

        const res = await this.apiPost(fd);
        if (res.success) {
            if (res.data && res.data.client_secret) {
                this.notify('OAuth Client saved. Secret: ' + res.data.client_secret, 'success');
                alert('IMPORTANT: Copy this Client Secret now, it will not be shown again.\n\n' + res.data.client_secret);
            } else {
                this.notify('OAuth Client saved.', 'success');
            }
            this.closeModal();
            this.switchIamTab('oauth', true);
        } else {
            this.handleApiErrors(res);
        }
    }

    async deleteOAuthClient(id) {
        if (!confirm('Delete this OAuth Client? This will break authentication for apps using it.')) return;

        const fd = new FormData();
        fd.append('action', 'delete_oauth_client');
        fd.append('id', id);

        const res = await this.apiPost(fd);
        if (res.success) {
            this.notify('OAuth Client deleted.', 'success');
            this.switchIamTab('oauth', true);
        } else {
            this.handleApiErrors(res);
        }
    }
}
