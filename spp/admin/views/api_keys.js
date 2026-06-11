const ApiKeysView = `
<div class="view-container slide-in">
    <div class="view-header">
        <h2>🔑 API Keys Management</h2>
        <p>Generate and manage permanent API tokens for your application.</p>
        <button class="spp-btn spp-btn-primary" onclick="app.apiKeys.showGenerateModal()">+ Generate New Key</button>
    </div>

    <div class="spp-card">
        <table class="spp-table" id="apiKeysTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Token</th>
                    <th>Created At</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Populated via JS -->
            </tbody>
        </table>
    </div>
</div>

<div id="generateKeyModal" class="spp-modal" style="display:none;">
    <div class="spp-modal-content">
        <h3>Generate API Key</h3>
        <div class="spp-form-group">
            <label>Key Name (e.g. Frontend App)</label>
            <input type="text" id="newKeyName" class="spp-input" placeholder="Name">
        </div>
        <div class="spp-modal-actions">
            <button class="spp-btn" onclick="document.getElementById('generateKeyModal').style.display='none'">Cancel</button>
            <button class="spp-btn spp-btn-primary" onclick="app.apiKeys.generateKey()">Generate</button>
        </div>
    </div>
</div>
`;

class ApiKeysController {
    constructor() {
        this.keys = [];
    }

    async init() {
        app.setMainContent(ApiKeysView);
        await this.loadKeys();
    }

    async loadKeys() {
        try {
            const data = await SPPAPI.call('Auth.ListApiKeys');
            this.keys = data || [];
            this.renderTable();
        } catch (e) {
            console.error('Failed to load API keys:', e);
        }
    }

    renderTable() {
        const tbody = document.querySelector('#apiKeysTable tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (this.keys.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No API keys found.</td></tr>';
            return;
        }

        this.keys.forEach(key => {
            const tr = document.createElement('tr');
            
            const isActive = parseInt(key.status) === 1;
            const statusBadge = isActive ? 
                '<span style="color: green; font-weight: bold;">Active</span>' : 
                '<span style="color: red;">Revoked</span>';

            const actionBtn = isActive ? 
                `<button class="spp-btn spp-btn-danger spp-btn-sm" onclick="app.apiKeys.revokeKey('${key.id}')">Revoke</button>` : 
                '';

            // Show token only if active, or masked if revoked
            const tokenDisplay = isActive ? key.token : '********************************';

            tr.innerHTML = \`
                <td><strong>\${key.name}</strong></td>
                <td style="font-family: monospace; font-size: 0.9em; word-break: break-all;">\${tokenDisplay}</td>
                <td>\${key.created_at}</td>
                <td>\${statusBadge}</td>
                <td>\${actionBtn}</td>
            \`;
            tbody.appendChild(tr);
        });
    }

    showGenerateModal() {
        document.getElementById('newKeyName').value = '';
        document.getElementById('generateKeyModal').style.display = 'flex';
    }

    async generateKey() {
        const name = document.getElementById('newKeyName').value.trim();
        if (!name) {
            alert('Please enter a name for the API key.');
            return;
        }

        document.getElementById('generateKeyModal').style.display = 'none';
        await SPPAPI.call('Auth.GenerateApiKey', { name });
        // The server will execute app.apiKeys.loadKeys() directly via instruction
    }

    async revokeKey(id) {
        if (!confirm('Are you sure you want to revoke this API key? This action cannot be undone.')) {
            return;
        }
        await SPPAPI.call('Auth.RevokeApiKey', { id });
    }
}

// Register with the global app
app.apiKeys = new ApiKeysController();
export default app.apiKeys;
