import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js?v=2026_05_13_v1';
import { html } from '../../../spp/modules/spp/sppux/js/sppux.js?v=2026_05_13_v1';

/**
 * Lekhak Blocks & Views Manager View Controller
 * Manages Drupal-style template regions, custom HTML blocks, and dynamic views query designer.
 */
export default class BlocksView extends BaseComponent {
    async onInit(params = {}) {
        this.state = {
            blocks: [],
            blockTypes: [],
            regions: [
                { id: 'header', name: 'Header Region' },
                { id: 'primary_menu', name: 'Primary Navigation Menu' },
                { id: 'slider', name: 'Hero / Slider Area' },
                { id: 'sidebar_first', name: 'Sidebar First' },
                { id: 'content', name: 'Main Content Region' },
                { id: 'footer', name: 'Footer Region' }
            ],
            loading: true,
            activeTab: 'library', // library, designer
            editingBlock: null, // block structure for create/edit
            designer: {
                title: 'Recent Articles View',
                region: 'sidebar_first',
                weight: 0,
                entity_type: 'node',
                limit: 5,
                sort: 'created DESC',
                display_style: 'grid',
                conditions: { status: 'published' }
            },
            previewLoading: false,
            previewNodes: []
        };

        window.__spp_handlers = window.__spp_handlers || {};
        window.__spp_handlers['nav-lekhak'] = () => location.hash = 'lekhak';
        window.__spp_handlers['nav-content'] = () => location.hash = 'content';
        window.__spp_handlers['nav-canvas'] = () => location.hash = 'canvas';
        window.__spp_handlers['nav-media'] = () => location.hash = 'media';
        window.__spp_handlers['nav-structure'] = () => location.hash = 'structure';
        window.__spp_handlers['nav-blocks'] = () => location.hash = 'blocks';
        window.__spp_handlers['nav-commerce'] = () => location.hash = 'commerce';
        window.__spp_handlers['nav-translations'] = () => location.hash = 'translations';
        window.__spp_handlers['nav-settings'] = () => location.hash = 'settings';
    }

    async onMount() {
        await this.fetchData();
        await this.previewQuery();
    }

    async fetchData() {
        this.setState({ loading: true });
        try {
            const blockTypesRes = await this.api.listTypes();
            const blocksRes = await this.api.listBlocks();
            
            this.setState({
                blockTypes: blockTypesRes.success ? blockTypesRes.types : [],
                blocks: blocksRes.success ? blocksRes.blocks : [],
                loading: false
            });
        } catch (e) {
            console.error('Blocks data fetch error:', e);
            this.admin?.notify?.("Failed to load blocks data.", "error");
            this.setState({ loading: false });
        }
    }

    setTab(tab) {
        this.setState({ activeTab: tab, editingBlock: null });
        if (tab === 'designer') {
            this.previewQuery();
        }
    }

    async saveBlock(e) {
        if (e) e.preventDefault();
        const block = this.state.editingBlock;
        if (!block) return;

        try {
            const res = await this.api.saveBlock({
                id: block.id || null,
                block_type: block.block_type || 'custom_html',
                region: block.region || 'global',
                weight: parseInt(block.weight || 0, 10),
                page_id: 0,
                data: block.data || {}
            });

            if (res.success) {
                this.admin?.notify?.(block.id ? "Block updated successfully." : "Block created successfully.", "success");
                this.setState({ editingBlock: null });
                await this.fetchData();
            } else {
                this.admin?.notify?.(res.message || "Save failed.", "error");
            }
        } catch (e) {
            console.error(e);
            this.admin?.notify?.("An error occurred during save.", "error");
        }
    }

    async deleteBlock(id) {
        if (!confirm("Are you sure you want to permanently delete this block?")) return;
        try {
            const res = await this.api.deleteBlock({ id });
            if (res.success) {
                this.admin?.notify?.("Block deleted successfully.", "success");
                await this.fetchData();
            } else {
                this.admin?.notify?.(res.message || "Delete failed.", "error");
            }
        } catch (e) {
            console.error(e);
            this.admin?.notify?.("Failed to delete block.", "error");
        }
    }

    async adjustWeight(block, dir) {
        const newWeight = parseInt(block.weight || 0, 10) + (dir === 'up' ? -1 : 1);
        try {
            const res = await this.api.saveBlock({
                id: block.id,
                block_type: block.block_type,
                region: block.region,
                weight: newWeight,
                page_id: 0,
                data: block.data
            });
            if (res.success) {
                await this.fetchData();
            }
        } catch (e) {
            console.error(e);
        }
    }

    startCreateBlock(type) {
        this.setState({
            editingBlock: {
                block_type: type,
                region: 'global',
                weight: 0,
                data: {
                    title: type === 'custom_html' ? 'New HTML Block' : 'New Text Block',
                    text: '',
                    html: '<!-- Custom HTML -->'
                }
            }
        });
    }

    startEditBlock(block) {
        this.setState({
            editingBlock: JSON.parse(JSON.stringify(block))
        });
    }

    cancelEdit() {
        this.setState({ editingBlock: null });
    }

    updateEditingField(field, value, isData = false) {
        const editingBlock = { ...this.state.editingBlock };
        if (isData) {
            editingBlock.data = { ...editingBlock.data, [field]: value };
        } else {
            editingBlock[field] = value;
        }
        this.setState({ editingBlock });
    }

    updateDesignerField(field, value, isCond = false) {
        const designer = { ...this.state.designer };
        if (isCond) {
            designer.conditions = { ...designer.conditions, [field]: value };
        } else {
            designer[field] = value;
        }
        this.setState({ designer });
        this.previewQuery();
    }

    async previewQuery() {
        this.setState({ previewLoading: true });
        try {
            const res = await this.api.listNodes({
                limit: this.state.designer.limit || 5
            });
            if (res.success) {
                this.setState({ previewNodes: res.nodes || [], previewLoading: false });
            } else {
                this.setState({ previewLoading: false });
            }
        } catch (e) {
            console.error(e);
            this.setState({ previewLoading: false });
        }
    }

    async saveDesignerBlock() {
        const d = this.state.designer;
        try {
            const res = await this.api.saveBlock({
                block_type: 'dynamic_view',
                region: d.region,
                weight: parseInt(d.weight || 0, 10),
                page_id: 0,
                data: {
                    title: d.title,
                    entity_type: d.entity_type,
                    limit: parseInt(d.limit || 5, 10),
                    sort: d.sort,
                    display_style: d.display_style,
                    conditions: d.conditions
                }
            });

            if (res.success) {
                this.admin?.notify?.("Dynamic view query block saved successfully!", "success");
                this.setTab('library');
                await this.fetchData();
            } else {
                this.admin?.notify?.(res.message || "Failed to save designer block.", "error");
            }
        } catch (e) {
            console.error(e);
            this.admin?.notify?.("Failed to save designer block.", "error");
        }
    }

    render() {
        if (this.state.loading) {
            return this.renderLoading("Loading Blocks Layout & Views System...");
        }

        const { activeTab, blocks, regions, blockTypes, editingBlock, designer, previewNodes, previewLoading } = this.state;

        // Group blocks by region
        const regionBlocksMap = {};
        regions.forEach(r => {
            regionBlocksMap[r.id] = blocks.filter(b => b.region === r.id).sort((a,b) => a.weight - b.weight);
        });

        // Tab selection styles
        const tabStyle = (tab) => `
            padding: 0.75rem 1.5rem;
            color: ${activeTab === tab ? '#ffffff' : 'var(--text-dim)'};
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border-bottom: 3px solid ${activeTab === tab ? '#f97316' : 'transparent'};
            transition: all 0.2s ease;
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem;
        `;

        return html`
            <div class="lekhak-blocks-shell" style="font-family:'Inter',sans-serif;color:var(--text);min-height:100vh;">
                <!-- Header Tabs -->
                <div class="lekhak-admin-toolbar" style="position:sticky;top:0;z-index:1000;background:var(--header-bg);border-bottom:2px solid var(--border);padding:0 1.5rem;height:50px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                    <div style="display:flex;align-items:center;gap:8px;font-weight:bold;font-family:'Outfit',sans-serif;">
                        <span style="background:linear-gradient(135deg,#f97316,#ea580c);color:white;width:24px;height:24px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;font-size:0.8rem;box-shadow:0 2px 6px rgba(0,0,0,0.2);">🧱</span>
                        <span>Blocks & Views</span>
                    </div>
                    <div style="display:flex;height:100%;">
                        <a class="toolbar-tab" data-spp-evt="nav-lekhak" data-spp-type="click" style="padding:0 0.75rem;display:flex;align-items:center;color:var(--text-dim);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid transparent;height:100%;">Dashboard</a>
                        <a class="toolbar-tab" data-spp-evt="nav-content" data-spp-type="click" style="padding:0 0.75rem;display:flex;align-items:center;color:var(--text-dim);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid transparent;height:100%;">Content</a>
                        <a class="toolbar-tab" data-spp-evt="nav-canvas" data-spp-type="click" style="padding:0 0.75rem;display:flex;align-items:center;color:var(--text-dim);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid transparent;height:100%;">Pages</a>
                        <a class="toolbar-tab" data-spp-evt="nav-media" data-spp-type="click" style="padding:0 0.75rem;display:flex;align-items:center;color:var(--text-dim);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid transparent;height:100%;">Media</a>
                        <a class="toolbar-tab" data-spp-evt="nav-structure" data-spp-type="click" style="padding:0 0.75rem;display:flex;align-items:center;color:var(--text-dim);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid transparent;height:100%;">Structure</a>
                        <a class="toolbar-tab active" data-spp-evt="nav-blocks" data-spp-type="click" style="padding:0 0.75rem;display:flex;align-items:center;color:var(--primary);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid var(--primary);height:100%;">Blocks</a>
                        <a class="toolbar-tab" data-spp-evt="nav-commerce" data-spp-type="click" style="padding:0 0.75rem;display:flex;align-items:center;color:var(--text-dim);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid transparent;height:100%;">Commerce</a>
                        <a class="toolbar-tab" data-spp-evt="nav-translations" data-spp-type="click" style="padding:0 0.75rem;display:flex;align-items:center;color:var(--text-dim);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid transparent;height:100%;">Translations</a>
                        <a class="toolbar-tab" data-spp-evt="nav-settings" data-spp-type="click" style="padding:0 0.75rem;display:flex;align-items:center;color:var(--text-dim);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid transparent;height:100%;">Appearance</a>
                    </div>
                    <div>
                        ${activeTab === 'library' ? html`<button class="btn-toolbar-primary" id="btn-create-html" style="background:#f97316;color:white;border:none;padding:6px 14px;border-radius:6px;font-size:0.8rem;font-weight:800;cursor:pointer;">＋ HTML Block</button>` : ''}
                    </div>
                </div>

                <div style="padding:2rem;max-width:1400px;margin:0 auto;">
                    <!-- Inner Page Tabs -->
                    <div style="display:flex;border-bottom:1px solid var(--border);margin-bottom:2rem;gap:1rem;">
                        <span style="${tabStyle('library')}" id="tab-library">Custom Blocks Library</span>
                        <span style="${tabStyle('designer')}" id="tab-designer">Drupal Views Designer</span>
                    </div>

                    <!-- Blocks Library Tab Content -->
                    ${activeTab === 'library' ? html`
                        <div>
                            <!-- Edit/Create form -->
                            ${editingBlock ? html`
                                <div style="background:rgba(30,41,59,0.6);border:1px solid var(--border);border-radius:12px;padding:2rem;margin-bottom:2rem;max-width:800px;backdrop-filter:blur(8px);">
                                    <h3 style="font-family:'Outfit',sans-serif;font-weight:800;color:var(--text);margin-bottom:1.5rem;font-size:1.3rem;">
                                        ${editingBlock.id ? "Edit Custom Block" : "Create New Custom Block"}
                                    </h3>

                                    <form id="block-editor-form" style="display:flex;flex-direction:column;gap:1.25rem;">
                                        <div style="display:grid;grid-template-columns:1fr;gap:1rem;">
                                            <div style="display:flex;flex-direction:column;gap:6px;">
                                                <label style="font-size:0.85rem;font-weight:700;color:var(--text-dim);">Block Title</label>
                                                <input type="text" id="edit-title" value="${editingBlock.data?.title || ''}" style="background:rgba(0,0,0,0.3);border:1px solid var(--border);padding:8px 12px;border-radius:8px;color:var(--text);outline:none;font-size:0.9rem;" required />
                                            </div>
                                        </div>

                                        <div style="display:grid;grid-template-columns:1fr;gap:1rem;">
                                            <div style="display:flex;flex-direction:column;gap:6px;">
                                                <label style="font-size:0.85rem;font-weight:700;color:var(--text-dim);">Block Type</label>
                                                <select id="edit-type" style="background:rgba(0,0,0,0.3);border:1px solid var(--border);padding:8px 12px;border-radius:8px;color:var(--text);outline:none;font-size:0.9rem;">
                                                    <option value="custom_html" ?selected=${editingBlock.block_type === 'custom_html'}>Custom HTML Block</option>
                                                    <option value="text" ?selected=${editingBlock.block_type === 'text'}>Simple Text Block</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div style="display:flex;flex-direction:column;gap:6px;">
                                            <label style="font-size:0.85rem;font-weight:700;color:var(--text-dim);">
                                                ${editingBlock.block_type === 'custom_html' ? 'Custom HTML Content' : 'Simple Text/Markdown Content'}
                                            </label>
                                            <textarea id="edit-content" rows="6" style="background:rgba(0,0,0,0.3);border:1px solid var(--border);padding:10px 12px;border-radius:8px;color:var(--text);outline:none;font-family:monospace;font-size:0.88rem;resize:vertical;" required>${editingBlock.block_type === 'custom_html' ? (editingBlock.data?.html || '') : (editingBlock.data?.text || '')}</textarea>
                                        </div>

                                        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:0.5rem;">
                                            <button type="button" id="btn-cancel-edit" style="background:transparent;border:1px solid var(--border);color:var(--text);padding:8px 18px;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;">Cancel</button>
                                            <button type="submit" style="background:#f97316;border:none;color:white;padding:8px 20px;border-radius:8px;font-size:0.85rem;font-weight:800;cursor:pointer;">Save Block</button>
                                        </div>
                                    </form>
                                </div>
                            ` : ''}

                            <!-- Blocks table list -->
                            <div style="background:rgba(30,41,59,0.3);border:1px solid var(--border);border-radius:12px;overflow:hidden;">
                                <table style="width:100%;border-collapse:collapse;text-align:left;font-size:0.9rem;">
                                    <thead>
                                        <tr style="background:rgba(0,0,0,0.2);border-bottom:1px solid var(--border);">
                                            <th style="padding:1rem 1.5rem;font-family:'Outfit',sans-serif;font-weight:700;color:var(--text-dim);width:30px;">#</th>
                                            <th style="padding:1rem 1.5rem;font-family:'Outfit',sans-serif;font-weight:700;color:var(--text-dim);">Block Title</th>
                                            <th style="padding:1rem 1.5rem;font-family:'Outfit',sans-serif;font-weight:700;color:var(--text-dim);">Type</th>
                                            <th style="padding:1rem 1.5rem;font-family:'Outfit',sans-serif;font-weight:700;color:var(--text-dim);text-align:right;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${blocks.length === 0 ? html`
                                            <tr>
                                                <td colspan="4" style="padding:2rem;text-align:center;color:var(--text-dim);font-style:italic;">No blocks created yet. Click "+ HTML Block" to create one.</td>
                                            </tr>
                                        ` : blocks.map((b, idx) => html`
                                            <tr style="border-bottom:1px solid rgba(255,255,255,0.04);transition:background 0.2s;" class="data-row">
                                                <td style="padding:1rem 1.5rem;color:var(--text-dim);">${idx + 1}</td>
                                                <td style="padding:1rem 1.5rem;">
                                                    <span style="font-weight:600;color:var(--text);">${b.data?.title || 'Untitled Block'}</span>
                                                </td>
                                                <td style="padding:1rem 1.5rem;">
                                                    <span style="font-size:0.7rem;text-transform:uppercase;background:rgba(99,102,241,0.15);color:#818cf8;padding:2px 8px;border-radius:4px;font-weight:700;letter-spacing:0.05em;">${b.block_type}</span>
                                                </td>
                                                <td style="padding:1rem 1.5rem;text-align:right;">
                                                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                                                        <button class="btn-edit-block-lib" data-id="${b.id}" style="background:transparent;color:#f97316;border:none;cursor:pointer;font-weight:600;font-size:0.85rem;">Edit</button>
                                                        <button class="btn-delete-block-lib" data-id="${b.id}" style="background:transparent;color:#ef4444;border:none;cursor:pointer;font-weight:600;font-size:0.85rem;">Delete</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        `)}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ` : ''}

                    <!-- Views Designer Tab Content -->
                    ${activeTab === 'designer' ? html`
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;">
                            <!-- Designer Form -->
                            <div style="background:rgba(30,41,59,0.4);border:1px solid var(--border);border-radius:12px;padding:2rem;">
                                <h3 style="font-family:'Outfit',sans-serif;font-weight:800;color:var(--text);margin-bottom:1.5rem;font-size:1.3rem;display:flex;align-items:center;gap:8px;">
                                    <span>⚙️</span> Dynamic View Query Settings
                                </h3>

                                <div style="display:flex;flex-direction:column;gap:1.25rem;">
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                                        <div style="display:flex;flex-direction:column;gap:6px;">
                                            <label style="font-size:0.85rem;font-weight:700;color:var(--text-dim);">View Block Title</label>
                                            <input type="text" id="design-title" value="${designer.title}" style="background:rgba(0,0,0,0.3);border:1px solid var(--border);padding:8px 12px;border-radius:8px;color:var(--text);outline:none;font-size:0.9rem;" />
                                        </div>
                                    </div>

                                    <div style="display:grid;grid-template-columns:1fr;gap:1rem;">
                                        <div style="display:flex;flex-direction:column;gap:6px;">
                                            <label style="font-size:0.85rem;font-weight:700;color:var(--text-dim);">Entity Type</label>
                                            <select id="design-entity" style="background:rgba(0,0,0,0.3);border:1px solid var(--border);padding:8px 12px;border-radius:8px;color:var(--text);outline:none;font-size:0.9rem;">
                                                <option value="node">Lekhak Node (Content)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                                        <div style="display:flex;flex-direction:column;gap:6px;">
                                            <label style="font-size:0.85rem;font-weight:700;color:var(--text-dim);">Result Limit</label>
                                            <input type="number" id="design-limit" value="${designer.limit}" style="background:rgba(0,0,0,0.3);border:1px solid var(--border);padding:8px 12px;border-radius:8px;color:var(--text);outline:none;font-size:0.9rem;" />
                                        </div>
                                        <div style="display:flex;flex-direction:column;gap:6px;">
                                            <label style="font-size:0.85rem;font-weight:700;color:var(--text-dim);">Sort Criteria</label>
                                            <select id="design-sort" style="background:rgba(0,0,0,0.3);border:1px solid var(--border);padding:8px 12px;border-radius:8px;color:var(--text);outline:none;font-size:0.9rem;">
                                                <option value="created DESC" ?selected=${designer.sort === 'created DESC'}>Date Created (Newest First)</option>
                                                <option value="created ASC" ?selected=${designer.sort === 'created ASC'}>Date Created (Oldest First)</option>
                                                <option value="changed DESC" ?selected=${designer.sort === 'changed DESC'}>Date Modified (Newest First)</option>
                                                <option value="title ASC" ?selected=${designer.sort === 'title ASC'}>Alphabetical (A-Z)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                                        <div style="display:flex;flex-direction:column;gap:6px;">
                                            <label style="font-size:0.85rem;font-weight:700;color:var(--text-dim);">Display Layout Style</label>
                                            <select id="design-style" style="background:rgba(0,0,0,0.3);border:1px solid var(--border);padding:8px 12px;border-radius:8px;color:var(--text);outline:none;font-size:0.9rem;">
                                                <option value="grid" ?selected=${designer.display_style === 'grid'}>Vibrant Content Grid</option>
                                                <option value="list" ?selected=${designer.display_style === 'list'}>Elegant List Layout</option>
                                                <option value="table" ?selected=${designer.display_style === 'table'}>Responsive Data Table</option>
                                            </select>
                                        </div>
                                        <div style="display:flex;flex-direction:column;gap:6px;">
                                            <label style="font-size:0.85rem;font-weight:700;color:var(--text-dim);">Filter Status</label>
                                            <select id="design-status" style="background:rgba(0,0,0,0.3);border:1px solid var(--border);padding:8px 12px;border-radius:8px;color:var(--text);outline:none;font-size:0.9rem;">
                                                <option value="published" ?selected=${designer.conditions?.status === 'published'}>Only Published</option>
                                                <option value="draft" ?selected=${designer.conditions?.status === 'draft'}>Only Drafts</option>
                                            </select>
                                        </div>
                                    </div>

                                    <button id="btn-save-designer" style="background:#f97316;color:white;border:none;padding:12px;border-radius:8px;font-family:'Outfit',sans-serif;font-weight:800;font-size:0.95rem;cursor:pointer;margin-top:1rem;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 12px rgba(249,115,22,0.2);">
                                        <span>💾</span> Compile & Save View Block
                                    </button>
                                </div>
                            </div>

                            <!-- Live Preview Mockup -->
                            <div>
                                <h3 style="font-family:'Outfit',sans-serif;font-weight:800;color:var(--text);margin-bottom:1.5rem;font-size:1.3rem;display:flex;align-items:center;justify-content:center;gap:8px;">
                                    <span>👁️</span> Real-Time Layout Preview
                                </h3>

                                <div style="background:rgba(30,41,59,0.3);border:1px solid var(--border);border-radius:12px;padding:2rem;min-height:380px;display:flex;flex-direction:column;justify-content:space-between;backdrop-filter:blur(8px);">
                                    <div>
                                        <div style="border-bottom:1px solid rgba(255,255,255,0.06);padding-bottom:1rem;margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;">
                                            <h4 style="font-family:'Outfit',sans-serif;font-weight:700;color:#f8fafc;font-size:1.15rem;margin:0;">
                                                ${designer.title || 'Dynamic View'}
                                            </h4>
                                            <span style="font-size:0.7rem;text-transform:uppercase;background:rgba(16,185,129,0.15);color:#10b981;padding:2px 8px;border-radius:4px;font-weight:700;letter-spacing:0.05em;">Live Compiled Preview</span>
                                        </div>

                                        ${previewLoading ? html`
                                            <div style="display:flex;justify-content:center;align-items:center;height:200px;">
                                                <div style="width:30px;height:30px;border:3px solid var(--border);border-top-color:#f97316;border-radius:50%;animation:sppSpin 0.8s linear infinite;"></div>
                                            </div>
                                        ` : html`
                                            <div>
                                                <!-- Dynamic layouts rendering mockup -->
                                                ${designer.display_style === 'grid' ? html`
                                                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem;">
                                                        ${previewNodes.length === 0 ? html`
                                                            <p style="color:var(--text-dim);font-size:0.85rem;font-style:italic;">No nodes matching criteria found.</p>
                                                        ` : previewNodes.map(node => html`
                                                            <div style="background:rgba(255,255,255,0.03);padding:1rem;border-radius:10px;border:1px solid rgba(255,255,255,0.06);">
                                                                <h5 style="color:#f97316;margin:0 0 0.5rem 0;font-size:0.95rem;font-family:'Outfit',sans-serif;font-weight:700;">${node.title}</h5>
                                                                <p style="font-size:0.75rem;color:var(--text-dim);margin:0;line-height:1.4;">${node.body ? node.body.replace(/<[^>]*>/g, '').substring(0, 70) + '...' : 'Empty body content.'}</p>
                                                            </div>
                                                        `)}
                                                    </div>
                                                ` : ''}

                                                ${designer.display_style === 'table' ? html`
                                                    <table style="width:100%;border-collapse:collapse;font-size:0.82rem;color:var(--text);">
                                                        <thead>
                                                            <tr style="background:rgba(255,255,255,0.04);border-bottom:1px solid rgba(255,255,255,0.08);"><th style="text-align:left;padding:8px 10px;color:var(--text-dim);">Title</th><th style="text-align:left;padding:8px 10px;color:var(--text-dim);">Type</th></tr>
                                                        </thead>
                                                        <tbody>
                                                            ${previewNodes.length === 0 ? html`
                                                                <tr><td colspan="2" style="padding:1rem;text-align:center;color:var(--text-dim);">No nodes found.</td></tr>
                                                            ` : previewNodes.map(node => html`
                                                                <tr style="border-bottom:1px solid rgba(255,255,255,0.04);"><td style="padding:8px 10px;font-weight:600;">${node.title}</td><td style="padding:8px 10px;color:var(--text-dim);">${node.bundle || 'Page'}</td></tr>
                                                            `)}
                                                        </tbody>
                                                    </table>
                                                ` : ''}

                                                ${designer.display_style === 'list' ? html`
                                                    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px;">
                                                        ${previewNodes.length === 0 ? html`
                                                            <p style="color:var(--text-dim);font-size:0.85rem;font-style:italic;">No nodes found.</p>
                                                        ` : previewNodes.map(node => html`
                                                            <li style="border-bottom:1px solid rgba(255,255,255,0.04);padding-bottom:8px;display:flex;flex-direction:column;gap:2px;">
                                                                <span style="color:#f97316;font-weight:600;font-size:0.88rem;">${node.title}</span>
                                                                <span style="font-size:0.7rem;color:var(--text-dim);">Published node criteria</span>
                                                            </li>
                                                        `)}
                                                    </ul>
                                                ` : ''}
                                            </div>
                                        `}
                                    </div>
                                    <div style="font-size:0.75rem;color:var(--text-dim);background:rgba(255,255,255,0.03);padding:8px 12px;border-radius:8px;border:1px solid rgba(255,255,255,0.06);margin-top:1.5rem;line-height:1.4;">
                                        💡 <strong>Aesthetic Note:</strong> The preview mimics the theme stylesheet engine's layout rendering using standard HSL palettes for saffron gradients, ensuring perfect content integration when output in live templates.
                                    </div>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                </div>
            </div>
            <style>
                @keyframes sppSpin { to { transform: rotate(360deg); } }
                .toolbar-tab:hover {
                    color: #ffffff !important;
                    background: rgba(255,255,255,0.04);
                }
                .data-row:hover {
                    background: rgba(255,255,255,0.02) !important;
                }
            </style>
        `;
    }

    afterUpdate() {
        // Tab click bindings
        const tabL = document.getElementById('tab-layout');
        if (tabL && !tabL._bound) {
            tabL.onclick = () => this.setTab('layout');
            tabL._bound = true;
        }

        const tabLib = document.getElementById('tab-library');
        if (tabLib && !tabLib._bound) {
            tabLib.onclick = () => this.setTab('library');
            tabLib._bound = true;
        }

        const tabDes = document.getElementById('tab-designer');
        if (tabDes && !tabDes._bound) {
            tabDes.onclick = () => this.setTab('designer');
            tabDes._bound = true;
        }

        // Library action button bindings
        const btnCreate = document.getElementById('btn-create-html');
        if (btnCreate && !btnCreate._bound) {
            btnCreate.onclick = () => this.startCreateBlock('custom_html');
            btnCreate._bound = true;
        }

        // Form cancel button binding
        const cancelBtn = document.getElementById('btn-cancel-edit');
        if (cancelBtn && !cancelBtn._bound) {
            cancelBtn.onclick = () => this.cancelEdit();
            cancelBtn._bound = true;
        }

        // Form submit binding
        const form = document.getElementById('block-editor-form');
        if (form && !form._bound) {
            form.onsubmit = (e) => this.saveBlock(e);
            form._bound = true;
        }

        // Editor live field syncing
        const editTitle = document.getElementById('edit-title');
        if (editTitle && !editTitle._bound) {
            editTitle.oninput = (e) => this.updateEditingField('title', e.target.value, true);
            editTitle._bound = true;
        }

        const editRegion = document.getElementById('edit-region');
        if (editRegion && !editRegion._bound) {
            editRegion.onchange = (e) => this.updateEditingField('region', e.target.value);
            editRegion._bound = true;
        }

        const editType = document.getElementById('edit-type');
        if (editType && !editType._bound) {
            editType.onchange = (e) => this.updateEditingField('block_type', e.target.value);
            editType._bound = true;
        }

        const editWeight = document.getElementById('edit-weight');
        if (editWeight && !editWeight._bound) {
            editWeight.oninput = (e) => this.updateEditingField('weight', e.target.value);
            editWeight._bound = true;
        }

        const editContent = document.getElementById('edit-content');
        if (editContent && !editContent._bound) {
            editContent.oninput = (e) => {
                const key = this.state.editingBlock.block_type === 'custom_html' ? 'html' : 'text';
                this.updateEditingField(key, e.target.value, true);
            };
            editContent._bound = true;
        }

        // Weight and Remove button action bindings inside Region list
        document.querySelectorAll('.btn-weight-up').forEach(btn => {
            if (!btn._bound) {
                btn.onclick = () => {
                    const block = this.state.blocks.find(b => b.id == btn.dataset.id);
                    if (block) this.adjustWeight(block, 'up');
                };
                btn._bound = true;
            }
        });

        document.querySelectorAll('.btn-weight-down').forEach(btn => {
            if (!btn._bound) {
                btn.onclick = () => {
                    const block = this.state.blocks.find(b => b.id == btn.dataset.id);
                    if (block) this.adjustWeight(block, 'down');
                };
                btn._bound = true;
            }
        });

        document.querySelectorAll('.btn-remove-block').forEach(btn => {
            if (!btn._bound) {
                btn.onclick = () => this.deleteBlock(btn.dataset.id);
                btn._bound = true;
            }
        });

        // Edit/Delete list buttons inside Blocks Library
        document.querySelectorAll('.btn-edit-block-lib').forEach(btn => {
            if (!btn._bound) {
                btn.onclick = () => {
                    const block = this.state.blocks.find(b => b.id == btn.dataset.id);
                    if (block) this.startEditBlock(block);
                };
                btn._bound = true;
            }
        });

        document.querySelectorAll('.btn-delete-block-lib').forEach(btn => {
            if (!btn._bound) {
                btn.onclick = () => this.deleteBlock(btn.dataset.id);
                btn._bound = true;
            }
        });

        // Designer Fields Syncing
        const designTitle = document.getElementById('design-title');
        if (designTitle && !designTitle._bound) {
            designTitle.oninput = (e) => this.updateDesignerField('title', e.target.value);
            designTitle._bound = true;
        }

        const designRegion = document.getElementById('design-region');
        if (designRegion && !designRegion._bound) {
            designRegion.onchange = (e) => this.updateDesignerField('region', e.target.value);
            designRegion._bound = true;
        }

        const designWeight = document.getElementById('design-weight');
        if (designWeight && !designWeight._bound) {
            designWeight.oninput = (e) => this.updateDesignerField('weight', e.target.value);
            designWeight._bound = true;
        }

        const designLimit = document.getElementById('design-limit');
        if (designLimit && !designLimit._bound) {
            designLimit.oninput = (e) => this.updateDesignerField('limit', e.target.value);
            designLimit._bound = true;
        }

        const designSort = document.getElementById('design-sort');
        if (designSort && !designSort._bound) {
            designSort.onchange = (e) => this.updateDesignerField('sort', e.target.value);
            designSort._bound = true;
        }

        const designStyle = document.getElementById('design-style');
        if (designStyle && !designStyle._bound) {
            designStyle.onchange = (e) => this.updateDesignerField('display_style', e.target.value);
            designStyle._bound = true;
        }

        const designStatus = document.getElementById('design-status');
        if (designStatus && !designStatus._bound) {
            designStatus.onchange = (e) => this.updateDesignerField('status', e.target.value, true);
            designStatus._bound = true;
        }

        const saveDesignerBtn = document.getElementById('btn-save-designer');
        if (saveDesignerBtn && !saveDesignerBtn._bound) {
            saveDesignerBtn.onclick = () => this.saveDesignerBlock();
            saveDesignerBtn._bound = true;
        }
    }
}
