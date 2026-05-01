/**
 * GroupsView Component
 */

/**
 * GroupsView Component
 * 
 * Manages framework groups and polymorphic membership assignments.
 */
export default class GroupsView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: true,
            groups: [],
            searchQuery: '',
            searchResults: [],
            currentGroupId: null,
            currentMembers: []
        };
        await this.fetchData();
    }

    async fetchData() {
        try {
            const res = await this.admin.api('list_groups');
            if (res.success) {
                this.setState({
                    groups: res.data.groups || [],
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
        const { loading, groups, error } = this.state;

        if (loading) return html`<div class="loading-state">Loading group infrastructure...</div>`;
        if (error) return html`<div class="empty-state"><h3>Error</h3><p>${error}</p></div>`;

        // Update Header
        const headerActions = document.getElementById('header-actions');
        if (headerActions) {
            const headerHtml = html`
                <button type="button" class="btn primary-btn btn-sm" @click=${() => this.openCreateModal()}>+ Create Group</button>
            `;
            headerActions.innerHTML = headerHtml.toString();
        }

        if (groups.length === 0) {
            return html`
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <h3>No Groups Found</h3>
                    <p>Groups allow you to manage permissions for multiple entities at once.</p>
                </div>
            `;
        }

        return html`
            <div class="card-grid">
                ${groups.map((g, i) => html`
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
                                <button type="button" class="btn danger-btn btn-sm" @click=${() => this.admin.confirmDelete('group', g.id)}>Delete</button>
                            </div>
                        </div>
                    </div>
                `)}
            </div>
        `;
    }

    async manageMembers(groupId, groupName) {
        this.state.currentGroupId = groupId;
        this.state.searchResults = [];
        this.state.searchQuery = '';
        this.state.currentMembers = [];
        
        this.admin.openModal(`Manage Members: ${groupName}`, this.getManagementHtml(), [
            { label: 'Close', type: 'secondary', fn: () => this.admin.closeModal() }
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
                            class="spp-element" value="${searchQuery}" @input=${(e) => this.handleSearch(e, currentGroupId)}>
                        
                        <div id="member-suggestions" class="suggestions-list search-suggestions-dropdown ${searchResults.length > 0 ? 'active' : ''}">
                            ${searchResults.map(item => html`
                                <div class="suggestion-item" @click=${() => this.promptAddMember(currentGroupId, item.class, item.id, item.name)}>
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

    refreshModal() {
        const { groups, currentGroupId } = this.state;
        const group = groups.find(g => g.id === currentGroupId);
        const title = group ? `Manage Members: ${group.name}` : 'Manage Members';
        
        this.admin.updateModal(title, this.getManagementHtml());
        this._registerGlobalHandlers();
    }

    async handleSearch(e, groupId) {
        const q = e.target.value.trim();
        this.state.searchQuery = q;
        
        if (q.length < 1) {
            this.state.searchResults = [];
            this.refreshModal();
            return;
        }

        try {
            const fd = new FormData();
            fd.append('action', 'search_entities');
            fd.append('q', q);
            const res = await this.admin.apiPost(fd);
            
            this.state.searchResults = res.data?.results || res.results || [];
            this.refreshModal();
        } catch (err) {
            console.error('Group search error:', err);
        }
    }

    async promptAddMember(groupId, entityClass, entityId, name) {
        try {
            console.log(`GroupsView: Fast-Add requested for ${name} into Group ${groupId}`);
            
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

            this.admin.notify(`Adding ${name}...`, 'info');
            const res = await this.admin.apiPost(fd);
            console.log("GroupsView: add_group_member response:", res);

            if (res.success) {
                this.admin.notify(`Successfully added ${name}.`, 'success');
                await this.loadMembers(groupId);
            } else {
                this.admin.notify(res.message || "Failed to add member.", 'error');
            }
        } catch (err) {
            console.error("GroupsView: Error in promptAddMember:", err);
            this.admin.notify("An unexpected error occurred during addition.", "error");
        }
    }

    async loadMembers(groupId) {
        try {
            const res = await this.admin.api(`list_group_members&group_id=${groupId}`);
            if (res.success) {
                this.state.currentMembers = res.data.members || [];
                this.refreshModal();
            }
        } catch (err) {
            this.admin.notify(`Error loading members: ${err.message}`, 'error');
        }
    }

    async removeMember(groupId, entityClass, entityId, name) {
        try {
            console.log(`GroupsView: Removal requested for ${name} (${entityClass}:${entityId}) from Group ${groupId}`);
            
            if (!confirm(`Remove '${name}' from this group?`)) return;

            const fd = new FormData();
            fd.append('action', 'remove_group_member');
            fd.append('group_id', groupId);
            fd.append('member_entity', entityClass);
            fd.append('member_id', entityId);

            this.admin.notify(`Removing ${name}...`, 'info');
            const res = await this.admin.apiPost(fd);
            console.log("GroupsView: remove_group_member response:", res);

            if (res.success) {
                this.admin.notify(`Successfully removed ${name}.`, 'success');
                await this.loadMembers(groupId);
            } else {
                this.admin.notify(res.message || "Failed to remove member.", 'error');
            }
        } catch (err) {
            console.error("GroupsView: Error in removeMember:", err);
            this.admin.notify("An unexpected error occurred during removal.", "error");
        }
    }
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

        this.admin.openModal('Create New Group', content, [
            { label: 'Cancel', type: 'secondary', fn: () => this.admin.closeModal() },
            { label: 'Create Group', type: 'primary', fn: () => this.saveGroup() }
        ]);
    }

    async saveGroup() {
        const name = document.getElementById('new-group-name').value.trim();
        const description = document.getElementById('new-group-desc').value.trim();

        if (!name) {
            this.admin.notify('Group name is required.', 'error');
            return;
        }

        try {
            const fd = new FormData();
            fd.append('action', 'save_group');
            fd.append('name', name);
            fd.append('description', description);

            const res = await this.admin.apiPost(fd);
            if (res.success) {
                this.admin.notify('Group created successfully.', 'success');
                this.admin.closeModal();
                await this.fetchData();
            } else {
                this.admin.notify(res.message || 'Failed to create group.', 'error');
            }
        } catch (err) {
            this.admin.notify(`Error: ${err.message}`, 'error');
        }
    }
}
