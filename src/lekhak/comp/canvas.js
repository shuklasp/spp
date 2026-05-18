import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js?v=2026_05_13_v1';

/**
 * CanvasView - The Visual Editing Hub and Landing Page Designer for Lekhak
 */
export default class CanvasView extends BaseComponent {
    async onInit() {
        this.state = {
            pages: [],
            loading: true,
            designerMode: false,
            currentPage: null,
            layout: []
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
        window.__spp_handlers['spp-canvas-add-page'] = () => this.createPage();
        window.__spp_handlers['spp-canvas-save-layout'] = () => this.saveLayout();
        window.__spp_handlers['spp-canvas-back'] = () => this.setState({ designerMode: false, currentPage: null, layout: [] });
    }

    async onMount() {
        await this.fetchPages();
    }

    async fetchPages() {
        try {
            const res = await this.api.listLandingPages();
            if (res.success) {
                this.setState({ pages: res.pages || [], loading: false });
            }
        } catch (e) {
            console.error('Failed to list landing pages:', e);
            this.setState({ loading: false });
        }
    }

    async createPage() {
        const title = await this.prompt("Enter landing page title:");
        if (!title) return;
        const alias = await this.prompt("Enter route URL slug (e.g. black-friday-sale):", title.toLowerCase().replace(/[^a-z0-9]+/g, '-'));
        if (!alias) return;

        try {
            const res = await this.api.saveLandingPage({ title, alias, layout: '[]' });
            if (res.success) {
                this.admin?.notify?.("Landing page registered.", "success");
                await this.openDesigner(res.id);
            }
        } catch (e) {
            this.admin?.notify?.("Failed to create page.", "error");
        }
    }

    async openDesigner(id) {
        try {
            const res = await this.api.getLandingPage({ id });
            if (res.success && res.page) {
                let layout = [];
                try {
                    layout = JSON.parse(res.page.layout || '[]');
                } catch (err) {
                    layout = [];
                }
                this.setState({
                    designerMode: true,
                    currentPage: res.page,
                    layout: layout
                });
            }
        } catch (e) {
            this.admin?.notify?.("Failed to load layout designer.", "error");
        }
    }

    async saveLayout() {
        if (!this.state.currentPage) return;
        const id = this.state.currentPage.id;
        const titleInput = document.getElementById('spp-designer-title-input');
        const title = titleInput ? titleInput.value : this.state.currentPage.title;
        const layoutJson = JSON.stringify(this.state.layout);

        try {
            const res = await this.api.saveLandingPage({ id, title, alias: this.state.currentPage.alias, layout: layoutJson });
            if (res.success) {
                this.admin?.notify?.("Visual Layout saved successfully.", "success");
                this.setState({ designerMode: false, currentPage: null, layout: [] });
                await this.fetchPages();
            }
        } catch (e) {
            this.admin?.notify?.("Failed to save layout.", "error");
        }
    }

    async deletePage(id) {
        if (!await this.confirm("Are you sure you want to permanently delete this landing page?")) return;
        try {
            const res = await this.api.deleteLandingPage({ id });
            if (res.success) {
                this.admin?.notify?.("Landing page deleted.", "info");
                await this.fetchPages();
            }
        } catch (e) {
            this.admin?.notify?.("Delete failed.", "error");
        }
    }

    async addBlock(type) {
        const title = await this.prompt("Enter block heading:", "Dynamic Headline");
        if (title === null) return;
        const text = await this.prompt("Enter body paragraph details:", "Lorem ipsum dolor sit amet, consectetur adipiscing elit.");
        if (text === null) return;

        const newBlock = {
            id: Date.now(),
            type: type,
            title: title,
            text: text,
            bg: '#1e293b',
            color: '#f8fafc'
        };

        this.setState({ layout: [...this.state.layout, newBlock] });
    }

    async editBlock(id) {
        const block = this.state.layout.find(b => b.id === id);
        if (!block) return;
        const title = await this.prompt("Edit block heading:", block.title);
        if (title === null) return;
        const text = await this.prompt("Edit body paragraph details:", block.text);
        if (text === null) return;

        this.setState({
            layout: this.state.layout.map(b => b.id === id ? { ...b, title, text } : b)
        });
    }

    moveBlock(id, direction) {
        const idx = this.state.layout.findIndex(b => b.id === id);
        if (idx === -1) return;
        const targetIdx = idx + direction;
        if (targetIdx < 0 || targetIdx >= this.state.layout.length) return;

        const copy = [...this.state.layout];
        const temp = copy[idx];
        copy[idx] = copy[targetIdx];
        copy[targetIdx] = temp;
        this.setState({ layout: copy });
    }

    deleteBlock(id) {
        this.setState({
            layout: this.state.layout.filter(b => b.id !== id)
        });
    }

    render() {
        const state = this.state || {};
        
        const html = `<div class="lekhak-canvas-shell">
    <div class="lekhak-admin-toolbar">
        <div class="toolbar-brand">
            <span class="logo-icon" style="background: linear-gradient(135deg, var(--primary), #a855f7); color: white; width: 24px; height: 24px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem; vertical-align: middle; box-shadow: 0 2px 6px rgba(0,0,0,0.2);">🎨</span>
            <span class="brand-label">Lekhak CMS</span>
        </div>
        <div class="toolbar-links">
            <a class="toolbar-tab" data-spp-evt="nav-lekhak" data-spp-type="click">Dashboard</a>
            <a class="toolbar-tab" data-spp-evt="nav-content" data-spp-type="click">Content</a>
            <a class="toolbar-tab active" data-spp-evt="nav-canvas" data-spp-type="click">Pages</a>
            <a class="toolbar-tab" data-spp-evt="nav-media" data-spp-type="click">Media</a>
            <a class="toolbar-tab" data-spp-evt="nav-structure" data-spp-type="click">Structure</a>
            <a class="toolbar-tab" data-spp-evt="nav-commerce" data-spp-type="click">Commerce</a>
            <a class="toolbar-tab" data-spp-evt="nav-translations" data-spp-type="click">Translations</a>
            <a class="toolbar-tab" data-spp-evt="nav-settings" data-spp-type="click">Appearance</a>
        </div>
        <div class="toolbar-actions">
            ${state.designerMode ? 
                `<button class="btn-toolbar-primary" style="background: var(--primary, #f97316) !important; color: white !important; font-weight: 800; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer;" data-spp-evt="spp-canvas-save-layout" data-spp-type="click" id="spp-canvas-save-btn">💾 Save Layout</button>` : 
                `<button class="btn-toolbar-primary" style="background: var(--primary, #f97316) !important; color: white !important; font-weight: 800; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer;" data-spp-evt="spp-canvas-add-page" data-spp-type="click" id="spp-canvas-add-btn">＋ New Page</button>`
            }
        </div>
    </div>

    <div class="lekhak-main-container">
        ${!state.designerMode ? `
            <!-- LIST MODE -->
            <header class="repository-header">
                <div class="header-main">
                    <h1>Visual Canvas</h1>
                    <p class="desc">Design premium layouts and responsive landing pages with modular sections.</p>
                </div>
            </header>

            <div class="lekhak-table-card">
                <div class="table-responsive-wrapper">
                    <table class="lekhak-data-table">
                        <thead>
                            <tr>
                                <th class="col-indicator"></th>
                                <th class="col-title">Page Name</th>
                                <th class="col-status">Route URL</th>
                                <th class="col-date">Last Saved</th>
                                <th class="col-actions" style="text-align: right;">Operations</th>
                            </tr>
                        </thead>
                        <tbody id="spp-canvas-table-rows">
                            <tr><td colspan="5" class="empty-table-cell">⏳ Loading pages...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        ` : `
            <header class="repository-header" style="display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:16px;">
                    <button id="spp-designer-back-btn" class="btn-operation" style="padding: 8px 16px; font-weight:600; background: var(--sidebar-bg, #ffedd5); color: var(--text, #431407); border: 1px solid var(--border, #fed7aa); border-radius: 6px; cursor: pointer;" data-spp-evt="spp-canvas-back" data-spp-type="click">← Back</button>
                    <input type="text" id="spp-designer-title-input" value="${state.currentPage?.title || ''}" style="background:transparent; border:none; border-bottom:2px dashed var(--border); font-size:1.8rem; font-weight:800; font-family:'Outfit',sans-serif; color:var(--text); outline:none; max-width:400px;" placeholder="Landing Page Title">
                </div>
                <div style="font-size:0.8rem; color:var(--text-dim); font-family:monospace;">Alias: /page/${state.currentPage?.alias || ''}</div>
            </header>

            <div class="designer-workspace" style="display:grid; grid-template-columns: 260px 1fr; gap:1.5rem;">
                <!-- Palette Column -->
                <aside class="palette-column" style="background:var(--sidebar-bg); border:1px solid var(--border); border-radius:8px; padding:1.25rem;">
                    <h3 style="font-size:0.9rem; font-weight:700; margin-bottom:1rem; text-transform:uppercase; color:var(--text-dim); letter-spacing:0.05em;">Section Blocks</h3>
                    <div class="block-palette" style="display:flex; flex-direction:column; gap:8px;">
                        <div class="palette-item" data-block-type="hero" style="padding:10px; background:var(--header-bg); border:1px dashed var(--border); border-radius:6px; cursor:pointer; font-size:0.82rem; font-weight:600;">
                            ✨ Hero Banner
                        </div>
                        <div class="palette-item" data-block-type="features" style="padding:10px; background:var(--header-bg); border:1px dashed var(--border); border-radius:6px; cursor:pointer; font-size:0.82rem; font-weight:600;">
                            📊 Features Grid
                        </div>
                        <div class="palette-item" data-block-type="cta" style="padding:10px; background:var(--header-bg); border:1px dashed var(--border); border-radius:6px; cursor:pointer; font-size:0.82rem; font-weight:600;">
                            📢 Call to Action
                        </div>
                        <div class="palette-item" data-block-type="gallery" style="padding:10px; background:var(--header-bg); border:1px dashed var(--border); border-radius:6px; cursor:pointer; font-size:0.82rem; font-weight:600;">
                            🖼️ Image Showcase
                        </div>
                        <div class="palette-item" data-block-type="pricing" style="padding:10px; background:var(--header-bg); border:1px dashed var(--border); border-radius:6px; cursor:pointer; font-size:0.82rem; font-weight:600;">
                            💳 Pricing Tables
                        </div>
                    </div>
                </aside>

                <!-- Stage Column -->
                <section class="stage-column" style="background:var(--sidebar-bg); border:1px solid var(--border); border-radius:8px; padding:1.5rem; min-height:500px; display:flex; flex-direction:column;">
                    <h3 style="font-size:0.9rem; font-weight:700; margin-bottom:1rem; text-transform:uppercase; color:var(--text-dim); letter-spacing:0.05em; border-bottom:1px solid var(--border); padding-bottom:8px;">Canvas Stage</h3>
                    
                    <div id="spp-designer-stage-body" style="flex-grow:1; display:flex; flex-direction:column; gap:12px;">
                        <!-- Decoupled Stage Renderer -->
                    </div>
                </section>
            </div>
        `}
    </div>
</div>

<style>
    .lekhak-canvas-shell { font-family: 'Inter', sans-serif; color: var(--text); min-height: 100vh; background: transparent; }
    .lekhak-admin-toolbar {
        position: sticky; top: 0; z-index: 1000;
        background: var(--header-bg); border-bottom: 2px solid var(--border);
        padding: 0 1.5rem; height: 50px;
        display: flex; justify-content: space-between; align-items: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .toolbar-brand { display: flex; align-items: center; gap: 8px; font-weight: bold; font-family: 'Outfit', sans-serif; font-size: 1rem; color: var(--text); }
    .toolbar-links { display: flex; height: 100%; }
    .toolbar-tab {
        padding: 0 0.75rem; display: flex; align-items: center;
        color: var(--text-dim); font-size: 0.78rem; font-weight: 600;
        text-decoration: none; cursor: pointer; transition: all 0.2s;
        border-bottom: 2px solid transparent; height: 100%;
    }
    .toolbar-tab:hover, .toolbar-tab.active { color: var(--primary); border-bottom-color: var(--primary); background: rgba(128,128,128,0.05); }
    .btn-toolbar-primary {
        background: var(--primary); color: white; border: none;
        padding: 6px 14px; border-radius: 6px; font-size: 0.8rem;
        font-weight: 800; cursor: pointer; transition: opacity 0.15s;
    }
    .btn-toolbar-primary:hover { opacity: 0.9; }
    .lekhak-main-container { padding: 2rem; max-width: 1400px; margin: 0 auto; }
    .repository-header { margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 1.25rem; }
    .header-main h1 { font-family: 'Outfit', sans-serif; font-size: 2rem; font-weight: 800; margin: 0; color: var(--text); }
    .header-main .desc { color: var(--text-dim); font-size: 0.9rem; margin-top: 4px; }
    
    .lekhak-table-card { background: var(--sidebar-bg); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
    .table-responsive-wrapper { width: 100%; overflow-x: auto; }
    .lekhak-data-table { width: 100%; border-collapse: collapse; text-align: left; }
    .lekhak-data-table th {
        background: var(--header-bg); color: var(--text-dim); font-size: 0.72rem;
        text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;
        padding: 12px 16px; border-bottom: 1px solid var(--border); white-space: nowrap;
    }
    .lekhak-data-table td { padding: 12px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; }
    .data-row { cursor: pointer; transition: background 0.15s; }
    .data-row:hover { background: rgba(128,128,128,0.08); }
    .col-indicator { width: 24px; padding-right: 0 !important; }
    .row-marker { width: 8px; height: 8px; border-radius: 50%; background: #38bdf8; }
    
    .title-text { font-weight: 700; font-size: 0.92rem; color: var(--text); font-family: 'Outfit', sans-serif; }
    .url-text { font-size: 0.78rem; color: var(--text-dim); font-family: monospace; }
    .date-string { font-size: 0.8rem; color: var(--text-dim); }
    
    .lekhak-operations-group { display: flex; gap: 4px; justify-content: flex-end; }
    .btn-operation {
        background: transparent; border: 1px solid var(--border);
        color: var(--text); padding: 4px 10px; border-radius: 4px;
        font-size: 0.73rem; text-decoration: none; cursor: pointer; transition: all 0.15s;
    }
    .btn-operation:hover { background: var(--header-bg); }
    .btn-operation.highlight { border-color: var(--primary); color: var(--primary); font-weight: 600; }
    .btn-operation.highlight:hover { background: var(--primary); color: white; }
    .empty-table-cell { text-align: center; padding: 3rem; color: var(--text-dim); }

    .palette-item:hover {
        border-color: var(--primary) !important;
        color: var(--primary);
        background: rgba(99, 102, 241, 0.05) !important;
    }
</style>`;

        const Trusted = Object.getPrototypeOf(this.renderLoading('')).constructor;
        return new Trusted(html);
    }

    afterUpdate() {
        if (!this.state.designerMode) {
            // Render list mode table rows
            const rows = document.getElementById('spp-canvas-table-rows');
            if (rows) {
                if (this.state.pages.length === 0) {
                    rows.innerHTML = `
                        <tr><td colspan="5" class="empty-table-cell">
                            <span style="font-size: 1.5rem; display: block; margin-bottom: 8px;">📭</span>
                            <span>No landing pages created. Click "＋ New Page" above to start designing.</span>
                        </td></tr>`;
                } else {
                    rows.innerHTML = '';
                    this.state.pages.forEach(p => {
                        const tr = document.createElement('tr');
                        tr.className = 'data-row';
                        tr.innerHTML = `
                            <td class="col-indicator"><div class="row-marker"></div></td>
                            <td class="col-title"><div class="title-text">${p.title}</div></td>
                            <td class="col-status"><span class="url-text">/page/${p.alias}</span></td>
                            <td class="col-date"><span class="date-string">${p.changed}</span></td>
                            <td class="col-actions" style="text-align: right;">
                                <div class="lekhak-operations-group">
                                    <button class="btn-operation highlight design-btn">Design Layout</button>
                                    <button class="btn-operation del-btn" style="color: #ef4444;">Delete</button>
                                </div>
                            </td>`;
                        tr.querySelector('.design-btn').onclick = () => this.openDesigner(p.id);
                        tr.querySelector('.del-btn').onclick = () => this.deletePage(p.id);
                        rows.appendChild(tr);
                    });
                }
            }
        } else {
            // Render designer stage body
            const stage = document.getElementById('spp-designer-stage-body');
            if (stage) {
                if (this.state.layout.length === 0) {
                    stage.innerHTML = `
                        <div style="flex-grow:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--text-dim); text-align:center; padding:3rem;">
                            <span style="font-size:2.5rem; display:block; margin-bottom:12px; animation:pulse 2s infinite;">✨</span>
                            <h4>Ready to build</h4>
                            <p style="font-size:0.8rem; max-width:280px; margin-top:4px;">Click any section block on the left palette to insert it here.</p>
                        </div>`;
                } else {
                    stage.innerHTML = '';
                    this.state.layout.forEach((b, idx) => {
                        const div = document.createElement('div');
                        div.style.cssText = `background:var(--header-bg); border:1px solid var(--border); border-radius:8px; padding:1.25rem; position:relative; box-shadow:0 4px 6px rgba(0,0,0,0.1);`;
                        
                        let badgeIcon = '🧩';
                        if (b.type === 'hero') badgeIcon = '✨ Hero';
                        if (b.type === 'features') badgeIcon = '📊 Features';
                        if (b.type === 'cta') badgeIcon = '📢 CTA';
                        if (b.type === 'gallery') badgeIcon = '🖼️ Gallery';
                        if (b.type === 'pricing') badgeIcon = '💳 Pricing';
 
                        div.innerHTML = `
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; border-bottom:1px solid var(--border); padding-bottom:6px;">
                                <span style="font-size:0.75rem; font-weight:700; color:var(--primary); text-transform:uppercase; letter-spacing:0.05em;">${badgeIcon} Section</span>
                                <div style="display:flex; gap:4px;">
                                    <button class="btn-up btn-operation" style="padding:2px 6px; font-size:0.65rem;" ${idx === 0 ? 'disabled' : ''}>▲</button>
                                    <button class="btn-down btn-operation" style="padding:2px 6px; font-size:0.65rem;" ${idx === this.state.layout.length - 1 ? 'disabled' : ''}>▼</button>
                                    <button class="btn-edit btn-operation highlight" style="padding:2px 8px; font-size:0.65rem;">Edit</button>
                                    <button class="btn-del btn-operation" style="padding:2px 8px; font-size:0.65rem; color:#ef4444;">✕</button>
                                </div>
                            </div>
                            <div>
                                <h4 style="font-size:1.05rem; font-weight:800; font-family:'Outfit',sans-serif; color:white; margin:0 0 4px 0;">${b.title}</h4>
                                <p style="font-size:0.82rem; color:var(--text-dim); line-height:1.4; margin:0;">${b.text}</p>
                            </div>`;
 
                        div.querySelector('.btn-up').onclick = () => this.moveBlock(b.id, -1);
                        div.querySelector('.btn-down').onclick = () => this.moveBlock(b.id, 1);
                        div.querySelector('.btn-edit').onclick = () => this.editBlock(b.id);
                        div.querySelector('.btn-del').onclick = () => this.deleteBlock(b.id);
 
                        stage.appendChild(div);
                    });
                }
            }

            // Palette items binding
            document.querySelectorAll('.palette-item').forEach(el => {
                if (!el._bound) {
                    el.onclick = () => this.addBlock(el.dataset.blockType);
                    el._bound = true;
                }
            });
        }
    }
}
