import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js?v=2026_05_13_v1';
import { registerNavHandlers, setPageMeta } from './lekhak-nav.js';

/**
 * Lekhak Settings View Controller (Drupal Appearance / Theme Engine)
 * Manages premium declarative theme configuration maps and live interface presets.
 */
export default class SettingsView extends BaseComponent {
    async onInit(params = {}) {
        console.log("Lekhak Settings View Initialized with params:", params);
        
        // Premium Drupal-style theme objects inventory
        this.state = {
            activeTab: params.tab || 'themes',
            activeAdminTheme: localStorage.getItem('lekhak-admin-theme-engine') || 'glass_admin',
            activeSiteTheme: localStorage.getItem('lekhak-site-theme-engine') || 'premium',
            selectedThemeForConfig: null,
            themes: [
                { id: 'glass_admin', title: 'Glass Admin Sovereign', ver: '8.4.1', type: 'admin', desc: 'Premium responsive glassmorphism workspace shell featuring real-time ambient lighting matrices.', icon: '🔮' },
                { id: 'premium', title: 'Premium Frontend Layout', ver: '2.0.0', type: 'site', desc: 'Curated consumer-facing presentation layer optimized for edge rendering consensus workflows.', icon: '💎' },
                { id: 'saffron_minimal', title: 'Saffron Aura Minimal', ver: '1.2.5', type: 'both', desc: 'A very mild saffron aesthetic with beautiful smooth linear-radial background gradients.', icon: '🪷' },
                { id: 'dark_sovereign', title: 'Deep Slate Terminal', ver: '4.1.0', type: 'admin', desc: 'High-contrast development suite oriented for CLI orchestration and real-time buffer dumps.', icon: '💻' },
                { id: 'eduxpro', title: 'Edu X Pro Layout', ver: '11.0.1', type: 'site', desc: 'Premium Drupal Theme For Educational Institutes featuring modular regions layout layer.', icon: '💧' }
            ],
            configs: {
                theme: 'dark',
                enable_edge_consensus: true,
                enable_merkle_trace: false,
                speculative_offline: true,
                strict_sri: false,
                ambient_scale: '1.05',
                primary_accent: '#f97316',
                lekhni_default_mode: 'document',
                lekhni_ai_copilot: false,
                lekhni_code_language: 'html',
                designer_grid_snap: true,
                designer_autosave: 300,
                structure_strict_schema: false,
                content_default_status: 'draft',
                content_revision_tracking: true
            }
        };

        try {
            const res = await this.api.getSettings();
            if (res && res.success) {
                this.setState({
                    configs: res.configs || this.state.configs,
                    activeAdminTheme: res.activeAdminTheme || this.state.activeAdminTheme,
                    activeSiteTheme: res.activeSiteTheme || this.state.activeSiteTheme
                });
            }
        } catch (e) {
            console.error("Failed to load settings from API:", e);
        }

        try {
            const blocksRes = await this.api.listBlocks();
            if (blocksRes && blocksRes.success) {
                this.setState({ blocks: blocksRes.blocks || [] });
            }
        } catch (e) {
            console.error("Failed to load blocks:", e);
        }

        // SPPEX: Shared navigation handlers (replaces duplicated lines)
        registerNavHandlers();
        setPageMeta('Appearance', 'Theme registry and visual configuration');

        // Dynamically poll backend Drishyam engine directory mapping service to discover freshly unpacked physical modules
        setTimeout(async () => {
            const endpoints = [
                (window.LEKHAK_CONFIG?.baseUrl || '') + '/index.php?__svc=drishyam:list',
                '?__svc=drishyam:list',
                (window.LEKHAK_CONFIG?.baseUrl || '') + '/lekhak/admin?__svc=drishyam:list'
            ];
            for (const url of endpoints) {
                try {
                    const res = await fetch(url);
                    if (res.ok) {
                        const fetchedThemes = await res.json();
                        if (Array.isArray(fetchedThemes) && fetchedThemes.length > 0) {
                            const currentMap = new Map(this.state.themes.map(t => [t.id, t]));
                            fetchedThemes.forEach(ft => {
                                if (currentMap.has(ft.id)) {
                                    const exist = currentMap.get(ft.id);
                                    exist.title = ft.title || exist.title;
                                    exist.ver = ft.ver || exist.ver;
                                    exist.type = ft.type || exist.type;
                                    exist.icon = ft.icon || exist.icon;
                                } else {
                                    currentMap.set(ft.id, ft);
                                }
                            });
                            this.setState({ themes: Array.from(currentMap.values()) });
                            break; // Stop immediately upon successful inventory resolution
                        }
                    }
                } catch (e) { /* ignore fallback iteration disconnects */ }
            }
        }, 50);
    }

    render() {
        const state = this.state || {};
        const themes = state.themes || [];
        const configs = state.configs || {};
        
        const contentStr = `
<div class="lekhak-settings-repository-shell">
    <!-- Ceiling Sticky Admin Ribbon -->
    <div class="lekhak-admin-toolbar">
        <div class="toolbar-brand">
            <span class="logo-icon" style="background: linear-gradient(135deg, #f97316, #ea580c); color: white; width: 24px; height: 24px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem; vertical-align: middle; box-shadow: 0 2px 6px rgba(0,0,0,0.2);">🎨</span>
            <span class="brand-label">Drupal Declarative Appearance Engine</span>
        </div>
        <div class="toolbar-links">
            <a class="toolbar-tab" data-spp-evt="nav-lekhak" data-spp-type="click">Dashboard</a>
            <a class="toolbar-tab" data-spp-evt="nav-content" data-spp-type="click">Content List</a>
            <a class="toolbar-tab" data-spp-evt="nav-canvas" data-spp-type="click">Visual Layouts</a>
            <a class="toolbar-tab" data-spp-evt="nav-commerce" data-spp-type="click">Catalog</a>
            <a class="toolbar-tab" data-spp-evt="nav-translations" data-spp-type="click">Translations</a>
            <a class="toolbar-tab active" id="spp-set-tab-themes">Appearance</a>
        </div>
    </div>

    <div class="lekhak-main-container">
        <header class="repository-header">
            <div class="header-main">
                <h1>Theme Registry & Mapping Hub</h1>
                <p class="desc">Discover installed presentation interfaces, toggle dynamic administrative shell overlays, and bind real-time context consensus parameters.</p>
            </div>
            
            <div class="lekhak-local-tasks">
                <button class="task-pill ${state.activeTab === 'themes' ? 'active' : ''}" id="spp-set-tab-themes">
                    Installed Layout Engines <span class="pill-badge">${themes.length}</span>
                </button>
                <button class="task-pill ${state.activeTab === 'global' ? 'active' : ''}" id="spp-set-tab-global">
                    ⚙️ Edge Consensus Configuration
                </button>
            </div>
        </header>

        <!-- Tab 1: Drupal-Style Installed Themes Inventory Grid -->
        <div style="display: ${state.activeTab === 'themes' ? 'block' : 'none'};">
            <div class="themes-grid">
                ${themes.map(t => `
                    <div class="theme-card ${state.activeAdminTheme === t.id ? 'admin-active' : ''} ${state.activeSiteTheme === t.id ? 'site-active' : ''}">
                        <div class="theme-cover" style="background: ${t.id === 'glass_admin' ? 'linear-gradient(135deg, #1e293b, #0f172a)' : (t.id === 'premium' ? 'linear-gradient(135deg, #0284c7, #0369a1)' : (t.id === 'saffron_minimal' ? 'linear-gradient(135deg, #f97316, #ea580c)' : (t.id === 'eduxpro' || t.icon === '💧' ? 'linear-gradient(135deg, #1e3a8a, #3b82f6)' : 'linear-gradient(135deg, #334155, #1e293b)')))};">
                            <span class="cover-icon" style="font-size: 3rem; filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3));">${t.icon || '📦'}</span>
                            <div class="cover-badges">
                                ${state.activeAdminTheme === t.id ? '<span class="tag-badge badge-admin">Active Admin Shell</span>' : ''}
                                ${state.activeSiteTheme === t.id ? '<span class="tag-badge badge-site">Primary Site Engine</span>' : ''}
                            </div>
                        </div>
                        <div class="theme-meta">
                            <div class="meta-head">
                                <h3 class="theme-title">${t.title}</h3>
                                <span class="theme-ver">${t.ver}</span>
                            </div>
                            <p class="theme-desc">${t.desc}</p>
                            
                            <div class="theme-actions-bar">
                                <div class="action-btn-group">
                                    <button class="btn-theme-action btn-sm" data-action="configure" data-theme-id="${t.id}">Configure</button>
                                </div>
                                <div class="action-btn-group">
                                    ${state.activeAdminTheme !== t.id ? '<button class="btn-theme-action btn-sm highlight" data-action="set-admin" data-theme-id="' + t.id + '">Set as Admin</button>' : ''}
                                    ${state.activeSiteTheme !== t.id ? '<button class="btn-theme-action btn-sm success" data-action="set-site" data-theme-id="' + t.id + '">Set as Site</button>' : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>

        <!-- Tab 2: Global Configuration Registry -->
        <div style="display: ${state.activeTab === 'global' ? 'block' : 'none'};">
            <div class="lekhak-table-card" style="padding: 2rem;">
                <h2 style="font-family: 'Outfit', sans-serif; margin-top: 0; color: var(--text-main, #0f172a);">Global Platform Configuration</h2>
                <p style="color: var(--text-dim, #64748b); font-size: 0.9rem; margin-bottom: 2rem;">Manage central system settings, appearance, editor defaults, and content rules.</p>
                
                <div class="settings-panel">
                    <h3>System & Integrity</h3>
                    <div class="config-row">
                        <div class="toggle-info">
                            <span class="toggle-label">Enable Edge Consensus Protocol</span>
                        </div>
                        <label class="switch"><input type="checkbox" class="spp-config-input" data-config-key="enable_edge_consensus" ${configs.enable_edge_consensus ? 'checked' : ''}><span class="slider round"></span></label>
                    </div>
                    <div class="config-row">
                        <div class="toggle-info">
                            <span class="toggle-label">Merkle Lineage Telemetry Dump</span>
                        </div>
                        <label class="switch"><input type="checkbox" class="spp-config-input" data-config-key="enable_merkle_trace" ${configs.enable_merkle_trace ? 'checked' : ''}><span class="slider round"></span></label>
                    </div>
                    <div class="config-row">
                        <div class="toggle-info">
                            <span class="toggle-label">Speculative Offline Caching Matrix</span>
                        </div>
                        <label class="switch"><input type="checkbox" class="spp-config-input" data-config-key="speculative_offline" ${configs.speculative_offline ? 'checked' : ''}><span class="slider round"></span></label>
                    </div>
                    <div class="config-row">
                        <div class="toggle-info">
                            <span class="toggle-label">Sub-Resource Integrity Strict Sandboxing</span>
                        </div>
                        <label class="switch"><input type="checkbox" class="spp-config-input" data-config-key="strict_sri" ${configs.strict_sri ? 'checked' : ''}><span class="slider round"></span></label>
                    </div>
                </div>

                <div class="settings-panel">
                    <h3>Appearance & Accent</h3>
                    <div class="config-row">
                        <div class="toggle-info"><span class="toggle-label">Theme Mode</span></div>
                        <select class="form-input spp-config-input" data-config-key="theme" style="width: 200px;">
                            <option value="dark" ${configs.theme === 'dark' ? 'selected' : ''}>Dark</option>
                            <option value="light" ${configs.theme === 'light' ? 'selected' : ''}>Light</option>
                            <option value="system" ${configs.theme === 'system' ? 'selected' : ''}>System</option>
                        </select>
                    </div>
                    <div class="config-row">
                        <div class="toggle-info"><span class="toggle-label">Ambient Radius Vector Scale</span></div>
                        <input type="text" class="form-input spp-config-input" data-config-key="ambient_scale" value="${configs.ambient_scale || ''}" style="width: 200px;">
                    </div>
                    <div class="config-row">
                        <div class="toggle-info"><span class="toggle-label">Primary Theme Accent Matrix</span></div>
                        <div style="display: flex; gap: 10px; align-items: center; width: 200px;">
                            <input type="color" class="spp-config-input" data-config-key="primary_accent" value="${configs.primary_accent || '#f97316'}" style="width: 50px; height: 40px; border: none; border-radius: 6px; cursor: pointer;">
                            <input type="text" class="form-input spp-config-input" data-config-key="primary_accent" value="${configs.primary_accent || '#f97316'}" style="flex-grow: 1;">
                        </div>
                    </div>
                </div>

                <div class="settings-panel">
                    <h3>Lekhni Editor</h3>
                    <div class="config-row">
                        <div class="toggle-info"><span class="toggle-label">Default Editor Mode</span></div>
                        <select class="form-input spp-config-input" data-config-key="lekhni_default_mode" style="width: 200px;">
                            <option value="document" ${configs.lekhni_default_mode === 'document' ? 'selected' : ''}>Document</option>
                            <option value="code" ${configs.lekhni_default_mode === 'code' ? 'selected' : ''}>Code Editor</option>
                        </select>
                    </div>
                    <div class="config-row">
                        <div class="toggle-info"><span class="toggle-label">Enable AI Copilot</span></div>
                        <label class="switch"><input type="checkbox" class="spp-config-input" data-config-key="lekhni_ai_copilot" ${configs.lekhni_ai_copilot ? 'checked' : ''}><span class="slider round"></span></label>
                    </div>
                    <div class="config-row">
                        <div class="toggle-info"><span class="toggle-label">Default Code Language</span></div>
                        <select class="form-input spp-config-input" data-config-key="lekhni_code_language" style="width: 200px;">
                            <option value="html" ${configs.lekhni_code_language === 'html' ? 'selected' : ''}>HTML</option>
                            <option value="css" ${configs.lekhni_code_language === 'css' ? 'selected' : ''}>CSS</option>
                            <option value="javascript" ${configs.lekhni_code_language === 'javascript' ? 'selected' : ''}>JavaScript</option>
                            <option value="php" ${configs.lekhni_code_language === 'php' ? 'selected' : ''}>PHP</option>
                        </select>
                    </div>
                </div>

                <div class="settings-panel">
                    <h3>Visual Designer</h3>
                    <div class="config-row">
                        <div class="toggle-info"><span class="toggle-label">Enable Grid Snap</span></div>
                        <label class="switch"><input type="checkbox" class="spp-config-input" data-config-key="designer_grid_snap" ${configs.designer_grid_snap ? 'checked' : ''}><span class="slider round"></span></label>
                    </div>
                    <div class="config-row">
                        <div class="toggle-info"><span class="toggle-label">Autosave Interval (seconds)</span></div>
                        <input type="number" class="form-input spp-config-input" data-config-key="designer_autosave" value="${configs.designer_autosave || '300'}" style="width: 200px;">
                    </div>
                </div>

                <div class="settings-panel">
                    <h3>Content & Structure</h3>
                    <div class="config-row">
                        <div class="toggle-info"><span class="toggle-label">Strict Schema Validation</span></div>
                        <label class="switch"><input type="checkbox" class="spp-config-input" data-config-key="structure_strict_schema" ${configs.structure_strict_schema ? 'checked' : ''}><span class="slider round"></span></label>
                    </div>
                    <div class="config-row">
                        <div class="toggle-info"><span class="toggle-label">Default Content Status</span></div>
                        <select class="form-input spp-config-input" data-config-key="content_default_status" style="width: 200px;">
                            <option value="draft" ${configs.content_default_status === 'draft' ? 'selected' : ''}>Draft</option>
                            <option value="published" ${configs.content_default_status === 'published' ? 'selected' : ''}>Published</option>
                            <option value="archived" ${configs.content_default_status === 'archived' ? 'selected' : ''}>Archived</option>
                        </select>
                    </div>
                    <div class="config-row">
                        <div class="toggle-info"><span class="toggle-label">Enable Revision Tracking</span></div>
                        <label class="switch"><input type="checkbox" class="spp-config-input" data-config-key="content_revision_tracking" ${configs.content_revision_tracking ? 'checked' : ''}><span class="slider round"></span></label>
                    </div>
                </div>

                <div style="margin-top: 2rem; border-top: 1px solid var(--glass-border, #e2e8f0); padding-top: 1.5rem;">
                    <button class="btn-toolbar-primary" id="spp-save-global-config-btn" style="padding: 10px 24px; font-size: 1rem; border: none; border-radius: 6px; background: var(--accent-primary, #f97316); color: white; cursor: pointer; font-weight: bold; transition: opacity 0.2s;">Save Global Settings</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stunning Inline Animated Config Overrides Drawer Overlay -->
    ${state.selectedThemeForConfig ? `
        <div class="spp-config-drawer-overlay" id="spp-drawer-close" onclick="document.getElementById('spp-drawer-close')?.style.setProperty('display', 'none'); window.admin?.activeComponent?.setState?.({ selectedThemeForConfig: null });">
            <div class="spp-config-drawer" onclick="event.stopPropagation()">
                <div class="drawer-header">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 1.8rem;">⚙️</span>
                        <div>
                            <h3 style="margin: 0; font-family: 'Outfit', sans-serif; color: inherit;">${state.selectedThemeForConfig.title || ''}</h3>
                            <span style="font-size: 0.75rem; opacity: 0.8;">Overrides mapping target: drishyam.yml</span>
                        </div>
                    </div>
                    <button class="btn-close-drawer" id="spp-drawer-close-inner" onclick="document.getElementById('spp-drawer-close')?.style.setProperty('display', 'none'); window.admin?.activeComponent?.setState?.({ selectedThemeForConfig: null });">✕</button>
                </div>
                
                <div class="drawer-body">
                    ${state.selectedThemeForConfig.icon === '💧' || state.selectedThemeForConfig.id === 'eduxpro' ? `
                        <div class="drupal-config-banner" style="background: rgba(30, 58, 138, 0.08); border: 1px solid rgba(30, 58, 138, 0.25); padding: 12px 15px; border-radius: 8px; margin-bottom: 1.5rem;">
                            <span style="font-weight: bold; color: #1e3a8a; display: block; font-size: 0.95rem;">💧 Drupal Block Layout & Appearance Mode</span>
                            <span style="font-size: 0.8rem; opacity: 0.85;">Configuring active .info.yml regions & rendering options natively.</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Theme Logo Image Path</label>
                            <input type="text" class="form-input" value="themes/eduxpro/logo.svg">
                            <span class="form-hint">Path relative to site root or absolute external URI vector.</span>
                        </div>

                        <div class="form-group" style="margin-top: 1.2rem;">
                            <label class="form-label">Block to Region Assignments</label>
                            <div style="background: rgba(0,0,0,0.02); padding: 12px; border-radius: 6px; border: 1px solid rgba(0,0,0,0.08); max-height: 260px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px;">
                                ${(state.blocks || []).map(b => \`
                                    <div style="display:flex; justify-content:space-between; align-items:center; padding: 8px; border: 1px solid rgba(0,0,0,0.05); border-radius: 4px; background: var(--input-bg, #ffffff);">
                                        <div style="font-weight: 600; font-size: 0.85rem;">\${b.data?.title || 'Untitled'} <span style="opacity:0.5; font-size:0.7rem;">(\${b.block_type})</span></div>
                                        <select class="form-input spp-config-input" data-config-key="block_theme_\${state.selectedThemeForConfig.id}_\${b.id}" style="width: 140px; padding: 4px; font-size: 0.8rem;">
                                            <option value="">-- Unassigned --</option>
                                            <option value="header_top_left" \${configs['block_theme_' + state.selectedThemeForConfig.id + '_' + b.id] === 'header_top_left' ? 'selected' : ''}>Header Top Left</option>
                                            <option value="primary_menu" \${configs['block_theme_' + state.selectedThemeForConfig.id + '_' + b.id] === 'primary_menu' ? 'selected' : ''}>Primary Menu</option>
                                            <option value="slider" \${configs['block_theme_' + state.selectedThemeForConfig.id + '_' + b.id] === 'slider' ? 'selected' : ''}>Slider</option>
                                            <option value="sidebar_first" \${configs['block_theme_' + state.selectedThemeForConfig.id + '_' + b.id] === 'sidebar_first' ? 'selected' : ''}>Sidebar First</option>
                                            <option value="footer_one" \${configs['block_theme_' + state.selectedThemeForConfig.id + '_' + b.id] === 'footer_one' ? 'selected' : ''}>Footer One</option>
                                        </select>
                                    </div>
                                \`).join('')}
                                \${(!state.blocks || state.blocks.length === 0) ? \`<div style="font-size:0.8rem; color: #888;">No blocks found. Create them in Block Management.</div>\` : ''}
                            </div>
                            <span class="form-hint" style="margin-top: 4px;">Assign custom blocks and dynamic views to theme regions.</span>
                        </div>

                        <div class="form-group" style="margin-top: 1.2rem;">
                            <label class="form-label">Twig Template Cache Strategy</label>
                            <select class="form-input" style="background-color: var(--input-bg, #ffffff);">
                                <option>Auto-recompile (Development Mode)</option>
                                <option>Strict Cache Aggregation (Production)</option>
                                <option>Disable Twig Output Buffers</option>
                            </select>
                        </div>
                    ` : `
                        <div class="form-group">
                            <label class="form-label">Ambient Radius Vector Scale</label>
                            <input type="text" class="form-input" value="${configs.ambient_scale || ''}">
                            <span class="form-hint">Controls dynamic global border bounds across viewport edges.</span>
                        </div>

                        <div class="form-group" style="margin-top: 1.5rem;">
                            <label class="form-label">Primary Theme Accent Matrix</label>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="color" value="${configs.primary_accent || '#f97316'}" style="width: 50px; height: 40px; border: none; border-radius: 6px; cursor: pointer;">
                                <input type="text" class="form-input" value="${configs.primary_accent || '#f97316'}" style="flex-grow: 1;">
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 1.5rem;">
                            <label class="form-label">Sub-Resource Integrity Strict Sandboxing</label>
                            <label class="switch" style="margin-top: 8px; display: block;">
                                <input type="checkbox" ${configs.strict_sri ? 'checked' : ''}>
                                <span class="slider round"></span>
                            </label>
                            <span class="form-hint" style="display: block; margin-top: 4px;">Injects cryptographic hash rules preventing runtime payload modification.</span>
                        </div>
                    `}
                </div>

                <div class="drawer-footer">
                    <button class="btn-toolbar-primary" style="width: 100%; padding: 12px; font-size: 0.95rem;" id="spp-save-config-btn">Commit Presentation Rules</button>
                </div>
            </div>
        </div>
    ` : ''}
</div>

<style>
    .lekhak-settings-repository-shell { font-family: 'Inter', sans-serif; color: var(--text-main, #0f172a); min-height: 100vh; background: transparent; }
    
    .lekhak-admin-toolbar {
        position: sticky; top: 45px; z-index: 1000;
        background: var(--glass-bg, #ffffff); border-bottom: 2px solid var(--glass-border, #e2e8f0);
        padding: 0 1.5rem; height: 50px;
        display: flex; justify-content: space-between; align-items: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .toolbar-brand { display: flex; align-items: center; gap: 8px; font-weight: bold; font-family: 'Outfit', sans-serif; font-size: 1rem; color: var(--text-main, #0f172a); }
    .toolbar-links { display: flex; height: 100%; }
    .toolbar-tab {
        padding: 0 1rem; display: flex; align-items: center;
        color: var(--text-dim, #64748b); font-size: 0.8rem; font-weight: 600;
        text-decoration: none; cursor: pointer; transition: all 0.2s;
        border-bottom: 2px solid transparent; height: 100%;
    }
    .toolbar-tab:hover, .toolbar-tab.active { color: var(--accent-primary, #f97316); border-bottom-color: var(--accent-primary, #f97316); background: rgba(128,128,128,0.05); }

    .lekhak-main-container { padding: 2.5rem 2rem; max-width: 1400px; margin: 0 auto; }
    
    .repository-header { margin-bottom: 2rem; border-bottom: 1px solid var(--glass-border, #e2e8f0); padding-bottom: 1.5rem; }
    .header-main h1 { font-family: 'Outfit', sans-serif; font-size: 2.2rem; font-weight: 800; margin: 0; color: var(--text-main, #0f172a); }
    .header-main .desc { color: var(--text-dim, #64748b); font-size: 0.95rem; margin-top: 4px; }

    .lekhak-local-tasks { display: flex; gap: 8px; margin-top: 1.5rem; flex-wrap: wrap; }
    .task-pill {
        background: var(--glass-bg, #f1f5f9); border: 1px solid var(--glass-border, #cbd5e1);
        color: var(--text-main, #0f172a); padding: 6px 14px; border-radius: 20px;
        font-size: 0.8rem; font-weight: 600; cursor: pointer;
        display: flex; align-items: center; gap: 8px; transition: all 0.15s;
    }
    .task-pill:hover, .task-pill.active { background: var(--accent-primary, #f97316); color: #ffffff; border-color: var(--accent-primary, #f97316); }
    .pill-badge { background: rgba(0,0,0,0.1); color: inherit; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px; font-weight: bold; }
    .task-pill.active .pill-badge { background: rgba(255,255,255,0.25); color: white; }

    /* Stunning Grid Architecture */
    .themes-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;
    }
    .theme-card {
        background: var(--glass-bg, #ffffff); border: 1px solid var(--glass-border, #e2e8f0);
        border-radius: 12px; overflow: hidden; display: flex; flex-direction: column;
        transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s; position: relative;
    }
    .theme-card:hover {
        transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.15);
    }
    .theme-card.admin-active { border-color: var(--accent-primary, #f97316); border-width: 2px; }
    .theme-card.site-active { border-color: #10b981; border-width: 2px; }

    .theme-cover {
        height: 160px; display: flex; align-items: center; justify-content: center;
        position: relative; overflow: hidden;
    }
    .cover-badges {
        position: absolute; top: 12px; left: 12px; right: 12px; display: flex; gap: 6px; justify-content: flex-start; flex-wrap: wrap;
    }
    .tag-badge {
        font-size: 0.65rem; font-weight: 800; text-transform: uppercase; padding: 4px 8px; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }
    .badge-admin { background: var(--accent-primary, #f97316); color: white; }
    .badge-site { background: #10b981; color: white; }

    .theme-meta { padding: 1.2rem; display: flex; flex-direction: column; flex-grow: 1; justify-content: space-between; }
    .meta-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .theme-title { font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 700; margin: 0; color: var(--text-main, #0f172a); }
    .theme-ver { font-size: 0.75rem; font-family: monospace; background: rgba(128,128,128,0.15); padding: 2px 6px; border-radius: 4px; color: var(--text-dim, #64748b); }
    .theme-desc { font-size: 0.85rem; color: var(--text-dim, #64748b); line-height: 1.4; margin: 0 0 1.2rem 0; flex-grow: 1; }

    .theme-actions-bar {
        display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--glass-border, #e2e8f0); padding-top: 1rem; gap: 8px;
    }
    .action-btn-group { display: flex; gap: 6px; }
    .btn-theme-action {
        background: var(--glass-bg, #f8fafc); border: 1px solid var(--glass-border, #cbd5e1); color: var(--text-main, #0f172a);
        padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; cursor: pointer; transition: all 0.15s;
    }
    .btn-theme-action:hover { background: var(--accent-primary, #f97316); color: #ffffff; border-color: var(--accent-primary, #f97316); }
    .btn-theme-action.highlight { background: var(--accent-primary, #f97316); color: #ffffff; border-color: var(--accent-primary, #f97316); }
    .btn-theme-action.success { background: #10b981; color: #ffffff; border-color: #10b981; }

    /* Configuration Toggles Row */
    .config-toggle-row {
        display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid var(--glass-border, #e2e8f0);
    }
    .toggle-info { display: flex; flex-direction: column; gap: 4px; }
    .toggle-label { font-weight: 600; font-size: 0.95rem; color: var(--text-main, #0f172a); }
    .toggle-desc { font-size: 0.85rem; color: var(--text-dim, #64748b); }

    /* Switches */
    .switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--glass-border, #cbd5e1); transition: .3s; }
    .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; }
    input:checked + .slider { background-color: var(--accent-primary, #f97316); }
    input:checked + .slider:before { transform: translateX(20px); }
    .slider.round { border-radius: 24px; }
    .slider.round:before { border-radius: 50%; }

    /* Drawer Overlay */
    .spp-config-drawer-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000;
        display: flex; justify-content: flex-end; animation: fadeIn 0.2s ease-out forwards; backdrop-filter: blur(4px);
    }
    .spp-config-drawer {
        width: 100%; max-width: 450px; background: var(--sidebar-bg, #ffffff); color: var(--sidebar-text, #0f172a); height: 100%; box-shadow: -8px 0 32px rgba(0,0,0,0.3);
        display: flex; flex-direction: column; border-left: 1px solid var(--glass-border, #e2e8f0); animation: slideInLeft 0.25s ease-out forwards;
        padding-top: 45px;
    }
    .drawer-header {
        padding: 1.5rem; border-bottom: 1px solid var(--glass-border, #e2e8f0); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.05);
    }
    .btn-close-drawer { background: transparent; border: none; color: inherit; font-size: 1.2rem; cursor: pointer; opacity: 0.7; }
    .btn-close-drawer:hover { opacity: 1; }
    .drawer-body { padding: 1.5rem; flex-grow: 1; overflow-y: auto; }
    .drawer-footer { padding: 1.5rem; border-top: 1px solid var(--glass-border, #e2e8f0); background: rgba(0,0,0,0.05); }

    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-label { font-size: 0.85rem; font-weight: 600; color: inherit; }
    .form-input {
        background: var(--input-bg, #ffffff); border: 1px solid var(--glass-border, #cbd5e1); color: inherit;
        padding: 10px 12px; border-radius: 6px; font-size: 0.9rem; outline: none; transition: border-color 0.15s; width: 100%;
    }
    .form-input:focus { border-color: var(--accent-primary, #f97316); }
    .form-hint { font-size: 0.75rem; opacity: 0.7; }

    .settings-panel { background: rgba(0,0,0,0.02); border: 1px solid var(--glass-border, #e2e8f0); border-radius: 8px; margin-bottom: 1.5rem; padding: 1.5rem; }
    .settings-panel h3 { margin-top: 0; font-family: 'Outfit', sans-serif; font-size: 1.1rem; border-bottom: 1px solid var(--glass-border, #e2e8f0); padding-bottom: 0.5rem; margin-bottom: 1rem; }
    .config-row { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--glass-border, #e2e8f0); }
    .config-row:last-child { border-bottom: none; padding-bottom: 0; }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideInLeft { from { transform: translateX(100%); } to { transform: translateX(0); } }
</style>
        `;
        
        return { content: contentStr, __isTrusted: true, toString: function() { return this.content; } };
    }

    afterUpdate() {
        // Hydrate configuration UI actions
        const tabThemes = document.getElementById('spp-set-tab-themes');
        const tabGlobal = document.getElementById('spp-set-tab-global');
        
        if (tabThemes && !tabThemes.onclick) {
            tabThemes.onclick = () => {
                this.setState({ activeTab: 'themes', selectedThemeForConfig: null });
            };
        }
        if (tabGlobal && !tabGlobal.onclick) {
            tabGlobal.onclick = () => {
                this.setState({ activeTab: 'global', selectedThemeForConfig: null });
            };
        }

        // Attach listeners for interactive theme buttons
        document.querySelectorAll('.btn-theme-action').forEach(btn => {
            if (btn.onclick) return;
            btn.onclick = async (e) => {
                const action = btn.getAttribute('data-action');
                const themeId = btn.getAttribute('data-theme-id');
                
                if (action === 'set-admin') {
                    localStorage.setItem('lekhak-admin-theme-engine', themeId);
                    document.cookie = `lekhak_admin_theme_engine=${themeId}; path=/; max-age=31536000; SameSite=Lax`;
                    this.setState({ activeAdminTheme: themeId });
                    this.admin?.notify?.(`Successfully assigned ${themeId} as primary Admin Shell.`, "success");
                    
                    if (themeId === 'glass_admin' || themeId === 'saffron_light') {
                        document.documentElement.setAttribute('data-theme', 'saffron');
                        localStorage.setItem('lekhak-admin-theme', 'saffron');
                    }
                    try {
                        await this.api.saveSettings({ adminTheme: themeId });
                    } catch (err) {
                        console.error("Failed to persist admin theme on backend:", err);
                    }
                } else if (action === 'set-site') {
                    localStorage.setItem('lekhak-site-theme-engine', themeId);
                    document.cookie = `lekhak_site_theme_engine=${themeId}; path=/; max-age=31536000; SameSite=Lax`;
                    this.setState({ activeSiteTheme: themeId });
                    this.admin?.notify?.(`Synchronized ${themeId} directly to site presentation router.`, "success");
                    try {
                        await this.api.saveSettings({ siteTheme: themeId });
                    } catch (err) {
                        console.error("Failed to persist site theme on backend:", err);
                    }
                } else if (action === 'configure') {
                    const targetTheme = this.state.themes.find(t => t.id === themeId);
                    this.setState({ selectedThemeForConfig: targetTheme });
                }
            };
        });

        // Config drawer listeners
        const closeDrawer = document.getElementById('spp-drawer-close');
        if (closeDrawer) {
            closeDrawer.onclick = () => this.setState({ selectedThemeForConfig: null });
        }
        const innerCloseBtn = document.getElementById('spp-drawer-close-inner');
        if (innerCloseBtn) {
            innerCloseBtn.onclick = (e) => {
                e.stopPropagation();
                this.setState({ selectedThemeForConfig: null });
            };
        }

        const saveConfigBtn = document.getElementById('spp-save-config-btn');
        if (saveConfigBtn && !saveConfigBtn.onclick) {
            saveConfigBtn.onclick = async () => {
                this.admin?.notify?.("Writing drishyam.yml presentation mapping variables...", "info");
                try {
                    const saveRes = await this.api.saveSettings({ configs: this.state.configs });
                    if (saveRes && saveRes.success) {
                        this.setState({ selectedThemeForConfig: null });
                        this.admin?.notify?.("Theme configurations and consensus bounds securely committed.", "success");
                    } else {
                        this.admin?.notify?.(saveRes?.message || "Failed to commit presentation rules.", "error");
                    }
                } catch (e) {
                    console.error("Failed to save config options via API:", e);
                    this.admin?.notify?.("Failed to commit settings.", "error");
                }
            };
        }
        
        // Input interactive toggles update handler
        document.querySelectorAll('.spp-config-input').forEach(inp => {
            inp.addEventListener('change', (e) => {
                const key = inp.getAttribute('data-config-key');
                let val = inp.value;
                if (inp.type === 'checkbox') val = inp.checked;
                else if (inp.type === 'number') val = parseInt(inp.value, 10);
                this.state.configs[key] = val;
                
                if (key === 'primary_accent') {
                    document.querySelectorAll(`.spp-config-input[data-config-key="primary_accent"]`).forEach(syncInp => {
                        syncInp.value = val;
                    });
                }
            });
            if (inp.getAttribute('data-config-key') === 'primary_accent') {
                inp.addEventListener('input', (e) => {
                    const key = inp.getAttribute('data-config-key');
                    const val = inp.value;
                    this.state.configs[key] = val;
                    document.querySelectorAll(`.spp-config-input[data-config-key="primary_accent"]`).forEach(syncInp => {
                        if (syncInp !== inp) syncInp.value = val;
                    });
                });
            }
        });

        const saveGlobalBtn = document.getElementById('spp-save-global-config-btn');
        if (saveGlobalBtn && !saveGlobalBtn.onclick) {
            saveGlobalBtn.onclick = async () => {
                this.admin?.notify?.("Writing global configuration variables...", "info");
                try {
                    const saveRes = await this.api.saveSettings({ configs: this.state.configs });
                    if (saveRes && saveRes.success) {
                        this.admin?.notify?.("Global configurations securely committed.", "success");
                    } else {
                        this.admin?.notify?.(saveRes?.message || "Failed to commit settings.", "error");
                    }
                } catch (e) {
                    console.error("Failed to save global config options via API:", e);
                    this.admin?.notify?.("Failed to commit settings.", "error");
                }
            };
        }

        // SPPEX.ColorPicker: Enhanced color input styling
        if (typeof SPPEX !== 'undefined' && SPPEX.ColorPicker) {
            SPPEX.ColorPicker.init('input[type="color"]');
        }
    }
}
