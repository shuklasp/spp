/**
 * ApiKeysView Component
 *
 * API Key Management for the IAM service.
 * Supports listing, generating (with one-time full key reveal),
 * and revoking API keys via Auth_* backend actions.
 */

export default class ApiKeysView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: true,
            keys: [],
            error: null,

            // Generate modal
            showGenerateModal: false,
            generateStep: 'form', // 'form' | 'reveal'
            newKeyName: '',
            newKeyScopes: '',
            generatedKey: null,
            generating: false,

            // Revoke
            revoking: null
        };

        await this.loadKeys();
    }

    // =========================================================================
    //  DATA LOADING
    // =========================================================================

    async loadKeys() {
        this.setState({ loading: true, error: null });
        try {
            const res = await this.api('Auth_ListApiKeys');
            if (res.success) {
                this.setState({
                    keys: res.data?.keys || [],
                    loading: false
                });
            } else {
                throw new Error(res.message || 'Failed to load API keys.');
            }
        } catch (err) {
            this.setState({ loading: false, error: err.message });
        }
    }

    // =========================================================================
    //  GENERATE KEY
    // =========================================================================

    openGenerateModal() {
        this.setState({
            showGenerateModal: true,
            generateStep: 'form',
            newKeyName: '',
            newKeyScopes: '',
            generatedKey: null,
            generating: false
        });
    }

    closeGenerateModal() {
        this.setState({
            showGenerateModal: false,
            generatedKey: null,
            generateStep: 'form'
        });
    }

    async submitGenerateKey() {
        const name = this.state.newKeyName.trim();
        const scopesRaw = this.state.newKeyScopes.trim();

        if (!name) {
            this.notify('Key name is required.', 'error');
            return;
        }

        const scopes = scopesRaw
            ? scopesRaw.split(',').map(s => s.trim()).filter(Boolean)
            : [];

        this.setState({ generating: true });

        try {
            const res = await this.apiPost('Auth_GenerateApiKey', { name, scopes });
            if (res.success) {
                this.setState({
                    generatedKey: res.data?.key || res.key,
                    generateStep: 'reveal',
                    generating: false
                });
                this.notify('API key generated successfully.', 'success');
                // Refresh the key list in the background
                this.loadKeys();
            } else {
                this.setState({ generating: false });
                this.notify(res.message || 'Failed to generate key.', 'error');
            }
        } catch (err) {
            this.setState({ generating: false });
            this.notify(`Error: ${err.message}`, 'error');
        }
    }

    async copyKeyToClipboard() {
        const key = this.state.generatedKey;
        if (!key) return;

        try {
            await navigator.clipboard.writeText(key);
            this.notify('API key copied to clipboard!', 'success');
        } catch (err) {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = key;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            this.notify('API key copied to clipboard!', 'success');
        }
    }

    // =========================================================================
    //  REVOKE KEY
    // =========================================================================

    async revokeKey(keyId, keyName) {
        if (!confirm(`Are you sure you want to revoke the API key "${keyName}"?\n\nThis action cannot be undone. Any integrations using this key will immediately lose access.`)) {
            return;
        }

        this.setState({ revoking: keyId });

        try {
            const res = await this.apiPost('Auth_RevokeApiKey', { key_id: keyId });
            if (res.success) {
                this.notify(`API key "${keyName}" has been revoked.`, 'success');
                await this.loadKeys();
            } else {
                this.notify(res.message || 'Failed to revoke key.', 'error');
            }
        } catch (err) {
            this.notify(`Error: ${err.message}`, 'error');
        } finally {
            this.setState({ revoking: null });
        }
    }

    // =========================================================================
    //  HELPERS
    // =========================================================================

    formatDate(dateStr) {
        if (!dateStr) return '—';
        try {
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-US', {
                year: 'numeric', month: 'short', day: 'numeric'
            });
        } catch {
            return dateStr;
        }
    }

    formatKeyPrefix(prefix) {
        if (!prefix) return '••••••••';
        const display = prefix.length > 8 ? prefix.substring(0, 8) : prefix;
        return display + '...';
    }

    // =========================================================================
    //  MAIN RENDER
    // =========================================================================

    render() {
        const { loading, error, keys, showGenerateModal } = this.state;

        // Update Header
        const headerActions = document.getElementById('header-actions');
        if (headerActions) {
            headerActions.innerHTML = '';
            const btn = document.createElement('button');
            btn.className = 'btn primary-btn btn-sm';
            btn.innerHTML = '🔑 + Generate New Key';
            btn.onclick = () => this.openGenerateModal();
            headerActions.appendChild(btn);
        }

        if (loading) {
            return html`<div class="loading-state">Loading API keys...</div>`;
        }

        if (error) {
            return html`
                <div class="empty-state">
                    <div class="empty-icon">⚠️</div>
                    <h3>Error Loading Keys</h3>
                    <p style="color: var(--text-dim);">${error}</p>
                    <button class="btn primary-btn" style="margin-top: 1rem;" @click=${() => this.loadKeys()}>🔄 Retry</button>
                </div>
            `;
        }

        return html`
            <div class="view-content-wrapper fade-in">
                <!-- Page Header -->
                <div class="glass-panel" style="padding: 1.5rem 2rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="margin: 0; font-size: 1.5rem;" class="gradient-text">🔑 API Key Management</h2>
                        <p style="margin: 0.25rem 0 0 0; color: var(--text-dim); font-size: 0.9rem;">
                            Create and manage API keys for programmatic access to your services.
                        </p>
                    </div>
                    <button class="btn primary-btn" @click=${() => this.openGenerateModal()}>
                        + Generate New Key
                    </button>
                </div>

                <!-- Key List or Empty State -->
                ${keys.length === 0
                    ? this.renderEmptyState()
                    : this.renderKeyTable()
                }

                <!-- Generate Modal -->
                ${showGenerateModal ? this.renderGenerateModal() : ''}
            </div>
        `;
    }

    // =========================================================================
    //  EMPTY STATE
    // =========================================================================

    renderEmptyState() {
        return html`
            <div class="glass-panel" style="text-align: center; padding: 4rem 2rem;">
                <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.8;">🔐</div>
                <h3 style="margin: 0 0 0.5rem 0; font-size: 1.4rem;">No API Keys Yet</h3>
                <p style="color: var(--text-dim); max-width: 450px; margin: 0 auto 1.5rem auto; line-height: 1.6;">
                    API keys allow external applications and scripts to authenticate with your platform securely.
                    Generate your first key to get started.
                </p>
                <button class="btn primary-btn" style="padding: 12px 28px; font-size: 1rem;" @click=${() => this.openGenerateModal()}>
                    🔑 Generate Your First Key
                </button>
            </div>
        `;
    }

    // =========================================================================
    //  KEY TABLE
    // =========================================================================

    renderKeyTable() {
        const { keys, revoking } = this.state;

        return html`
            <div class="glass-panel" style="padding: 0; overflow: hidden;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Key Prefix</th>
                            <th>Scopes</th>
                            <th>Created</th>
                            <th>Last Used</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${keys.map((key, i) => html`
                            <tr style="animation: fadeIn 0.3s ease ${i * 0.04}s both;">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span style="font-size: 1.1rem;">🔑</span>
                                        <div>
                                            <div style="font-weight: 600;">${key.name}</div>
                                            <div style="font-size: 0.75rem; color: var(--text-dim);">ID: ${key.id}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <code style="background: rgba(255,255,255,0.05); padding: 3px 10px; border-radius: 4px; font-size: 0.85rem; letter-spacing: 0.5px;">
                                        ${this.formatKeyPrefix(key.key_prefix)}
                                    </code>
                                </td>
                                <td>
                                    <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                        ${(key.scopes || []).length > 0
                                            ? key.scopes.map(scope => html`
                                                <span class="tag info-tag" style="font-size: 0.7rem; padding: 2px 8px;">${scope}</span>
                                            `)
                                            : html`<span style="color: var(--text-dim); font-size: 0.8rem;">No scopes</span>`
                                        }
                                    </div>
                                </td>
                                <td style="font-size: 0.85rem; color: var(--text-dim);">
                                    ${this.formatDate(key.created_at)}
                                </td>
                                <td style="font-size: 0.85rem; color: var(--text-dim);">
                                    ${key.last_used ? this.formatDate(key.last_used) : html`<span style="opacity: 0.4;">Never</span>`}
                                </td>
                                <td>
                                    ${key.status === 'active'
                                        ? html`<span class="tag success-tag" style="font-size: 0.7rem; padding: 2px 10px;">● Active</span>`
                                        : html`<span class="tag" style="font-size: 0.7rem; padding: 2px 10px; background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3);">● Revoked</span>`
                                    }
                                </td>
                                <td class="text-right">
                                    ${key.status === 'active'
                                        ? html`
                                            <button class="btn danger-btn btn-sm"
                                                ?disabled=${revoking === key.id}
                                                @click=${() => this.revokeKey(key.id, key.name)}>
                                                ${revoking === key.id ? '⏳ Revoking...' : '🗑️ Revoke'}
                                            </button>
                                        `
                                        : html`<span style="color: var(--text-dim); font-size: 0.8rem;">—</span>`
                                    }
                                </td>
                            </tr>
                        `)}
                    </tbody>
                </table>
            </div>

            <!-- Summary bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding: 0 0.5rem;">
                <span style="color: var(--text-dim); font-size: 0.85rem;">
                    ${keys.length} key${keys.length !== 1 ? 's' : ''} total ·
                    ${keys.filter(k => k.status === 'active').length} active ·
                    ${keys.filter(k => k.status === 'revoked').length} revoked
                </span>
                <button class="btn ghost-btn btn-sm" @click=${() => this.loadKeys()}>🔄 Refresh</button>
            </div>
        `;
    }

    // =========================================================================
    //  GENERATE KEY MODAL
    // =========================================================================

    renderGenerateModal() {
        const { generateStep } = this.state;

        return html`
            <div class="glass-overlay" style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000;"
                @click=${(e) => { if (e.target === e.currentTarget) this.closeGenerateModal(); }}>
                <div class="glass-panel" style="width: 560px; max-width: 95vw; padding: 0; overflow: hidden; animation: fadeIn 0.2s ease;">
                    <!-- Modal Header -->
                    <div style="padding: 1.5rem 2rem; border-bottom: 1px solid var(--glass-border); display: flex; align-items: center; justify-content: space-between;">
                        <h3 style="margin: 0; font-size: 1.2rem;">
                            ${generateStep === 'form' ? '🔑 Generate New API Key' : '✅ Key Generated Successfully'}
                        </h3>
                        <button class="btn ghost-btn btn-sm" style="font-size: 1.2rem; padding: 4px 8px;" @click=${() => this.closeGenerateModal()}>✕</button>
                    </div>

                    <!-- Modal Body -->
                    <div style="padding: 2rem;">
                        ${generateStep === 'form'
                            ? this.renderGenerateForm()
                            : this.renderKeyReveal()
                        }
                    </div>
                </div>
            </div>
        `;
    }

    renderGenerateForm() {
        const { newKeyName, newKeyScopes, generating } = this.state;

        return html`
            <div class="input-group" style="margin-bottom: 1.25rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem;">Key Name</label>
                <input type="text" class="spp-element" placeholder="e.g. CI/CD Pipeline, Mobile App, Partner Integration"
                    .value=${newKeyName}
                    @input=${(e) => this.setState({ newKeyName: e.target.value })}
                    style="width: 100%;">
                <small style="color: var(--text-dim); margin-top: 0.3rem; display: block;">A descriptive name to identify this key.</small>
            </div>

            <div class="input-group" style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem;">Scopes</label>
                <input type="text" class="spp-element" placeholder="read, write, admin"
                    .value=${newKeyScopes}
                    @input=${(e) => this.setState({ newKeyScopes: e.target.value })}
                    style="width: 100%;">
                <small style="color: var(--text-dim); margin-top: 0.3rem; display: block;">Comma-separated list of access scopes. Leave empty for default permissions.</small>
            </div>

            <!-- Scopes quick-select -->
            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 1.5rem;">
                <span style="color: var(--text-dim); font-size: 0.8rem; margin-right: 4px;">Quick add:</span>
                ${['read', 'write', 'admin', 'webhooks', 'reports'].map(scope => html`
                    <button class="btn ghost-btn" style="padding: 2px 10px; font-size: 0.75rem; border-radius: 20px;"
                        @click=${() => {
                            const current = this.state.newKeyScopes.split(',').map(s => s.trim()).filter(Boolean);
                            if (!current.includes(scope)) {
                                current.push(scope);
                                this.setState({ newKeyScopes: current.join(', ') });
                            }
                        }}>+ ${scope}</button>
                `)}
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; padding-top: 1rem; border-top: 1px solid var(--glass-border);">
                <button class="btn ghost-btn" @click=${() => this.closeGenerateModal()}>Cancel</button>
                <button class="btn primary-btn" ?disabled=${generating} @click=${() => this.submitGenerateKey()}>
                    ${generating ? html`<span class="sppux-spinner" style="width:16px;height:16px;margin-right:8px;"></span> Generating...` : '🔑 Generate Key'}
                </button>
            </div>
        `;
    }

    renderKeyReveal() {
        const { generatedKey } = this.state;

        return html`
            <!-- Warning Banner -->
            <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: flex-start; gap: 10px;">
                <span style="font-size: 1.3rem;">⚠️</span>
                <div>
                    <strong style="color: #fbbf24; font-size: 0.9rem;">This key will only be shown once!</strong>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: var(--text-dim); line-height: 1.5;">
                        Copy it now and store it securely. You will not be able to view the full key again after closing this dialog.
                    </p>
                </div>
            </div>

            <!-- Key Display -->
            <div style="position: relative; margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.8rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px;">Your API Key</label>
                <div style="background: rgba(0,0,0,0.4); border: 1px solid var(--glass-border); border-radius: 8px; padding: 1rem 1.25rem; font-family: 'JetBrains Mono', 'Fira Code', monospace; font-size: 0.85rem; word-break: break-all; line-height: 1.6; color: var(--success); user-select: all;">
                    ${generatedKey}
                </div>
            </div>

            <!-- Actions -->
            <div style="display: flex; justify-content: flex-end; gap: 1rem; padding-top: 1rem; border-top: 1px solid var(--glass-border);">
                <button class="btn ghost-btn" @click=${() => this.closeGenerateModal()}>Close</button>
                <button class="btn primary-btn" @click=${() => this.copyKeyToClipboard()}>
                    📋 Copy to Clipboard
                </button>
            </div>
        `;
    }
}
