import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js?v=2026_05_13_v1';

/**
 * Lekhak Structure View - Content Type Manager
 * Manages content type bundles and their field definitions.
 */
export default class StructureView extends BaseComponent {
    async onInit() {
        this.state = {
            types: [
                { id: 'article', name: 'Article', description: 'Standard content articles and blog posts', icon: '📄', fields: ['Title', 'Body', 'Category', 'Tags', 'Featured Image'] },
                { id: 'page', name: 'Page', description: 'Static pages like About, Contact, Terms', icon: '📃', fields: ['Title', 'Body', 'Template'] },
                { id: 'product', name: 'Product', description: 'Commerce product listings', icon: '🛍️', fields: ['Title', 'Description', 'Price', 'SKU', 'Stock', 'Image'] },
                { id: 'landing', name: 'Landing Page', description: 'Marketing and promotional landing pages', icon: '🎨', fields: ['Title', 'Hero Image', 'Sections', 'CTA'] }
            ],
            loading: false
        };

        window.__spp_handlers = window.__spp_handlers || {};
        window.__spp_handlers['nav-lekhak'] = () => location.hash = 'lekhak';
        window.__spp_handlers['nav-content'] = () => location.hash = 'content';
        window.__spp_handlers['nav-canvas'] = () => location.hash = 'canvas';
        window.__spp_handlers['nav-media'] = () => location.hash = 'media';
        window.__spp_handlers['nav-structure'] = () => location.hash = 'structure';
        window.__spp_handlers['nav-commerce'] = () => location.hash = 'commerce';
        window.__spp_handlers['nav-translations'] = () => location.hash = 'translations';
        window.__spp_handlers['nav-settings'] = () => location.hash = 'settings';
    }

    addType() {
        const name = prompt('Content type name:');
        if (!name) return;
        const desc = prompt('Description:', 'Custom content type') || '';
        const id = name.toLowerCase().replace(/[^a-z0-9]+/g, '_');
        if (this.state.types.some(t => t.id === id)) {
            this.admin?.notify?.('Type already exists.', 'error'); return;
        }
        this.setState({
            types: [...this.state.types, { id, name, description: desc, icon: '📋', fields: ['Title', 'Body'] }]
        });
        this.admin?.notify?.(`Content type "${name}" created.`, 'success');
    }

    addField(typeId) {
        const type = this.state.types.find(t => t.id === typeId);
        if (!type) return;
        const fieldName = prompt(`Add field to "${type.name}":`);
        if (!fieldName) return;
        if (type.fields.includes(fieldName)) {
            this.admin?.notify?.('Field already exists.', 'error'); return;
        }
        this.setState({
            types: this.state.types.map(t => t.id === typeId ? { ...t, fields: [...t.fields, fieldName] } : t)
        });
        this.admin?.notify?.(`Field "${fieldName}" added to ${type.name}.`, 'success');
    }

    deleteType(typeId) {
        if (!confirm('Delete this content type?')) return;
        this.setState({ types: this.state.types.filter(t => t.id !== typeId) });
        this.admin?.notify?.('Content type deleted.', 'info');
    }

    render() {
        const { types } = this.state;

        const typeCards = types.map(t => `
            <div class="structure-card" data-type-id="${t.id}" style="background:var(--sidebar-bg);border:1px solid var(--border);border-radius:8px;padding:20px;position:relative;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="font-size:1.5rem;">${t.icon}</span>
                        <div>
                            <div style="font-weight:700;font-size:1rem;font-family:'Outfit',sans-serif;color:var(--text);">${t.name}</div>
                            <div style="font-size:0.75rem;color:var(--text-dim);margin-top:2px;">${t.description}</div>
                        </div>
                    </div>
                    <span style="font-size:0.68rem;background:var(--header-bg);padding:2px 8px;border-radius:4px;color:var(--text-dim);font-weight:600;">${t.id}</span>
                </div>
                <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;color:var(--text-dim);margin-bottom:8px;letter-spacing:0.05em;">Fields (${t.fields.length})</div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">
                    ${t.fields.map(f => `<span style="background:var(--header-bg);color:var(--text);padding:4px 10px;border-radius:6px;font-size:0.75rem;font-weight:500;border:1px solid var(--border);">${f}</span>`).join('')}
                </div>
                <div style="display:flex;gap:6px;justify-content:flex-end;border-top:1px solid var(--border);padding-top:12px;">
                    <button class="btn-add-field" style="font-size:0.75rem;color:var(--primary);background:transparent;border:1px solid var(--primary);border-radius:4px;padding:4px 12px;cursor:pointer;font-weight:600;">＋ Add Field</button>
                    <button class="btn-delete-type" style="font-size:0.75rem;color:#ef4444;background:transparent;border:1px solid var(--border);border-radius:4px;padding:4px 12px;cursor:pointer;">Delete</button>
                </div>
            </div>`).join('');

        const html = `<div class="lekhak-structure-shell" style="font-family:'Inter',sans-serif;color:var(--text);min-height:100vh;">
            <div class="lekhak-admin-toolbar" style="position:sticky;top:0;z-index:1000;background:var(--header-bg);border-bottom:2px solid var(--border);padding:0 1.5rem;height:50px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                <div style="display:flex;align-items:center;gap:8px;font-weight:bold;font-family:'Outfit',sans-serif;">
                    <span style="background:linear-gradient(135deg,var(--primary),#a855f7);color:white;width:24px;height:24px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;font-size:0.8rem;box-shadow:0 2px 6px rgba(0,0,0,0.2);">🏗️</span>
                    <span>Lekhak CMS</span>
                </div>
                <div style="display:flex;height:100%;">
                    <a class="toolbar-tab" data-spp-evt="nav-lekhak" data-spp-type="click" style="padding:0 0.75rem;display:flex;align-items:center;color:var(--text-dim);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid transparent;height:100%;">Dashboard</a>
                    <a class="toolbar-tab" data-spp-evt="nav-content" data-spp-type="click" style="padding:0 0.75rem;display:flex;align-items:center;color:var(--text-dim);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid transparent;height:100%;">Content</a>
                    <a class="toolbar-tab" data-spp-evt="nav-canvas" data-spp-type="click" style="padding:0 0.75rem;display:flex;align-items:center;color:var(--text-dim);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid transparent;height:100%;">Pages</a>
                    <a class="toolbar-tab" data-spp-evt="nav-media" data-spp-type="click" style="padding:0 0.75rem;display:flex;align-items:center;color:var(--text-dim);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid transparent;height:100%;">Media</a>
                    <a class="toolbar-tab active" data-spp-evt="nav-structure" data-spp-type="click" style="padding:0 0.75rem;display:flex;align-items:center;color:var(--primary);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid var(--primary);height:100%;">Structure</a>
                    <a class="toolbar-tab" data-spp-evt="nav-commerce" data-spp-type="click" style="padding:0 0.75rem;display:flex;align-items:center;color:var(--text-dim);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid transparent;height:100%;">Commerce</a>
                    <a class="toolbar-tab" data-spp-evt="nav-translations" data-spp-type="click" style="padding:0 0.75rem;display:flex;align-items:center;color:var(--text-dim);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid transparent;height:100%;">Translations</a>
                    <a class="toolbar-tab" data-spp-evt="nav-settings" data-spp-type="click" style="padding:0 0.75rem;display:flex;align-items:center;color:var(--text-dim);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid transparent;height:100%;">Appearance</a>
                </div>
                <div><button class="btn-toolbar-primary" id="structure-add-btn" style="background:var(--primary);color:white;border:none;padding:6px 14px;border-radius:6px;font-size:0.8rem;font-weight:800;cursor:pointer;">＋ New Type</button></div>
            </div>
            <div style="padding:2rem;max-width:1400px;margin:0 auto;">
                <h1 style="font-family:'Outfit',sans-serif;font-size:2rem;font-weight:800;margin:0 0 4px 0;color:var(--text);">Structure</h1>
                <p style="color:var(--text-dim);font-size:0.9rem;margin:0 0 1.5rem 0;">Manage content types and their field definitions. ${types.length} types configured.</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px;">${typeCards}</div>
            </div>
        </div>`;
        return new (window.TrustedHTML || Object.getPrototypeOf(this.renderLoading('')).constructor)(html);
    }

    afterUpdate() {
        const addBtn = document.getElementById('structure-add-btn');
        if (addBtn && !addBtn._bound) { addBtn.onclick = () => this.addType(); addBtn._bound = true; }

        document.querySelectorAll('.structure-card').forEach(card => {
            const typeId = card.dataset.typeId;
            const addFieldBtn = card.querySelector('.btn-add-field');
            const delBtn = card.querySelector('.btn-delete-type');
            if (addFieldBtn && !addFieldBtn._bound) { addFieldBtn.onclick = () => this.addField(typeId); addFieldBtn._bound = true; }
            if (delBtn && !delBtn._bound) { delBtn.onclick = () => this.deleteType(typeId); delBtn._bound = true; }
        });
    }
}
