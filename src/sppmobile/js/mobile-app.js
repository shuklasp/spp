/**
 * Mobile Studio Application Core
 */
import MobileView from './views/mobile.js';

class MobileStudioApp {
    constructor() {
        this.container = document.getElementById('view-container');
        this.currentProjectId = localStorage.getItem('current_project_id') || '';
        this.projects = [];
        
        // Initialize Core View
        this.view = new MobileView(this, this.container);
        
        // Register SPPUX Dialog Bridges
        if (window.SPPUX) {
            window.SPPUX.Prompt = (msg, def) => this.studioPrompt("Studio Action", msg, def);
            window.SPPUX.Confirm = (msg) => this.studioConfirm("Studio Confirmation", msg);
        }

        // Bind UI Events ONCE
        this.bindGlobalActions();
        this.bindNavigation();
        
        // Start Loading Data
        this.init();
    }

    async init() {
        try {
            this.updateLoadingProgress(30, "Discovering Project Blueprints...");
            await this.refreshProjectList();
            
            this.updateLoadingProgress(60, "Hydrating Workspace Context...");
            if (this.currentProjectId) {
                await this.loadProject(this.currentProjectId);
            } else if (this.projects && this.projects.length > 0) {
                this.renderPortfolioState();
            } else {
                this.renderEmptyState();
            }

            this.updateLoadingProgress(100, "System Ready");
            
            // Graceful Reveal
            setTimeout(() => {
                const loader = document.getElementById('studio-loading-screen');
                if (loader) {
                    loader.style.opacity = '0';
                    setTimeout(() => loader.style.display = 'none', 600);
                }
                document.getElementById('workspace-layer')?.classList.add('active');
            }, 500);
        } catch (e) {
            console.error("[MobileStudio] Critical Initialization Error:", e);
            this.updateLoadingProgress(100, "Initialization Failed");
            setTimeout(() => {
                const loader = document.getElementById('studio-loading-screen');
                if (loader) loader.style.display = 'none';
            }, 1000);
            this.notify("Failed to synchronize workspace orchestrator. Check console for details.", 'error');
            this.renderEmptyState();
        }
    }

    updateLoadingProgress(percent, status) {
        const bar = document.getElementById('loading-progress-bar');
        const text = document.getElementById('loading-status-text');
        if (bar) bar.style.width = percent + '%';
        if (text) text.innerText = status;
        console.log(`[Loading] ${percent}% - ${status}`);
    }

    renderEmptyState() {
        this.currentProjectId = '';
        localStorage.removeItem('current_project_id');
        const nameEl = document.getElementById('current-project-name');
        if (nameEl) nameEl.innerText = "Select Project";

        this.container.innerHTML = `
            <div class="fade-in" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #888; background: rgba(0,0,0,0.2); backdrop-filter: blur(20px);">
                <div style="width: 180px; margin-bottom: 30px; filter: drop-shadow(0 20px 40px rgba(0,0,0,0.5));">
                    <img src="assets/satya_logo.png" style="width: 100%; animation: float 6s ease-in-out infinite;" alt="Satya Studio">
                </div>
                <h2 style="color: #fff; margin-bottom: 10px; font-weight: 700;">Welcome to Satya Studio Pro</h2>
                <p style="margin-bottom: 30px; font-size: 1rem; opacity: 0.6; max-width: 400px; text-align: center;">Orchestrate your mobile vision with Satya Studio's advanced design system engine.</p>
                <button class="btn primary-btn" id="create-first-project-btn" style="padding: 15px 40px; font-size: 1rem; font-weight: 700; border-radius: 12px; box-shadow: 0 10px 30px var(--primary-glow);">🚀 Create Your First Project</button>
            </div>
        `;
        document.getElementById('create-first-project-btn')?.addEventListener('click', () => this.createNewProject());
    }

    renderPortfolioState() {
        this.currentProjectId = '';
        localStorage.removeItem('current_project_id');
        const nameEl = document.getElementById('current-project-name');
        if (nameEl) nameEl.innerText = "Project Portfolio";

        this.container.innerHTML = `
            <div class="fade-in" style="padding: 40px; height: 100%; overflow-y: auto; background: var(--panel-bg);">
                <div style="max-width: 1000px; margin: 0 auto;">
                    <header style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 40px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 20px;">
                        <div>
                            <h2 style="margin: 0; font-size: 2rem; color: #fff;">Project Portfolio</h2>
                            <p style="margin: 10px 0 0 0; color: #888;">Manage and orchestrate your mobile application ecosystem.</p>
                        </div>
                        <button class="btn primary-btn" id="portfolio-create-btn" style="padding: 12px 30px; font-weight: 700;">➕ New Project</button>
                    </header>

                    <div id="main-portfolio-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px;">
                        ${this.projects.map(p => `
                            <div class="glass-panel portfolio-card" style="padding: 25px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.05); transition: all 0.3s var(--transition); cursor: default;" 
                                 onmouseover="this.style.borderColor='var(--primary-color)'; this.style.transform='translateY(-5px)'" 
                                 onmouseout="this.style.borderColor='rgba(255,255,255,0.05)'; this.style.transform='translateY(0)'">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                    <div style="font-size: 2rem;">📱</div>
                                    <div style="display: flex; gap: 8px;">
                                        <button class="btn-icon btn-sm" onclick="mobileStudio.duplicateProject('${p.id}')" title="Duplicate">📋</button>
                                        <button class="btn-icon btn-sm" onclick="mobileStudio.renameProject('${p.id}', '${p.name}')" title="Rename">✏️</button>
                                        <button class="btn-icon btn-sm" onclick="mobileStudio.deleteProject('${p.id}', '${p.name}')" style="color: var(--danger-color) !important;" title="Delete">🗑️</button>
                                    </div>
                                </div>
                                <h3 style="margin: 0 0 5px 0; color: #fff; font-size: 1.1rem;">${p.name}</h3>
                                <p style="margin: 0 0 20px 0; font-size: 0.75rem; color: #666; font-family: 'JetBrains Mono';">${p.id}</p>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-size: 0.7rem; color: var(--primary-color); font-weight: 700; background: var(--primary-subtle); padding: 4px 10px; border-radius: 20px;">v${p.version}</span>
                                    <button class="btn btn-sm studio-btn-soft" onclick="mobileStudio.loadProject('${p.id}')" style="padding: 8px 20px; font-weight: 600;">Open Project</button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        `;
        document.getElementById('portfolio-create-btn')?.addEventListener('click', () => this.createNewProject());
    }

    async refreshProjectList() {
        try {
            const res = await this.api('list_projects');
            if (res.success) {
                this.projects = res.data.projects;
                this.renderProjectDropdown();
                this.renderPortfolioList();
            } else {
                this.notify(res.message, 'error');
            }
        } catch (e) {
            console.error("[MobileStudio] Project Discovery Failure:", e);
            this.notify("Unable to reach project synchronization service.", 'error');
        }
    }

    renderProjectDropdown() {
        const container = document.getElementById('project-list-container');
        if (!container) return;

        if (this.projects.length === 0) {
            container.innerHTML = '<div style="padding: 10px 15px; font-size: 0.7rem; color: #555;">No projects found.</div>';
            return;
        }

        container.innerHTML = this.projects.map(p => `
            <div class="dropdown-item ${p.id === this.currentProjectId ? 'active' : ''}" 
                 onclick="mobileStudio.loadProject('${p.id}')"
                 style="padding: 10px 15px; font-size: 0.75rem; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.03); display: flex; flex-direction: column; gap: 2px;">
                <span style="font-weight: 500; color: ${p.id === this.currentProjectId ? 'var(--primary-color)' : '#fff'};">${p.name}</span>
                <span style="font-size: 0.6rem; opacity: 0.4;">v${p.version} • ${p.id}</span>
            </div>
        `).join('');
    }

    // --- Project Portfolio Management ---
    openPortfolio() {
        document.getElementById('portfolio-modal-overlay').style.display = 'flex';
        this.renderPortfolioList();
    }

    renderPortfolioList() {
        const container = document.getElementById('portfolio-list');
        const countEl = document.getElementById('portfolio-count');
        if (!container) return;

        countEl.innerText = `${this.projects.length} Projects found.`;

        container.innerHTML = this.projects.map(p => `
            <div class="portfolio-item" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                <div style="display: flex; flex-direction: column; gap: 5px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 1rem; font-weight: 600; color: #fff;">${p.name}</span>
                        ${p.id === this.currentProjectId ? '<span style="font-size: 0.6rem; background: var(--primary-color); color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 800;">ACTIVE</span>' : ''}
                    </div>
                    <span style="font-size: 0.75rem; color: #666; font-family: 'JetBrains Mono';">${p.id} • v${p.version} • Updated ${p.updated_at}</span>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button class="btn btn-sm studio-btn-soft" onclick="mobileStudio.loadProject('${p.id}')" style="padding: 6px 12px; font-size: 0.75rem;">Open</button>
                    <button class="btn btn-sm studio-btn-soft" onclick="mobileStudio.duplicateProject('${p.id}')" title="Duplicate Project" style="padding: 6px 10px;">📋</button>
                    <button class="btn btn-sm studio-btn-soft" onclick="mobileStudio.renameProject('${p.id}', '${p.name}')" title="Rename Project" style="padding: 6px 10px;">✏️</button>
                    <button class="btn btn-sm studio-btn-soft" onclick="mobileStudio.deleteProject('${p.id}', '${p.name}')" title="Delete Project" style="padding: 6px 10px; color: var(--danger-color) !important;">🗑️</button>
                </div>
            </div>
        `).join('');
    }

    async renameProject(id, oldName) {
        const name = await this.studioPrompt("Rename Project", `Enter a new name for "${oldName}".`, oldName);
        if (!name || name === oldName) return;

        const res = await this.api('rename_project', { id, name });
        if (res.success) {
            this.notify("Project structurally rebranded.", 'success');
            
            // If the renamed project was the active one, update the state
            if (id === this.currentProjectId) {
                this.currentProjectId = res.data.new_id;
                localStorage.setItem('current_project_id', res.data.new_id);
                document.getElementById('current-project-name').innerText = name;
            }
            
            await this.refreshProjectList();
        } else {
            this.notify(res.message, 'error');
        }
    }

    async duplicateProject(id) {
        this.notify("Duplicating project portfolio...", 'info');
        const res = await this.api('duplicate_project', { id });
        if (res.success) {
            this.notify(res.message, 'success');
            await this.refreshProjectList();
        } else {
            this.notify(res.message, 'error');
        }
    }

    async deleteProject(id, name) {
        const confirmed = await this.studioConfirm("Delete Project", `Are you sure you want to permanently delete "${name}"? All assets and configurations will be lost.`);
        if (!confirmed) return;

        const res = await this.api('delete_project', { id });
        if (res.success) {
            this.notify("Project deleted.", 'success');
            await this.refreshProjectList();

            if (this.projects.length === 0 || id === this.currentProjectId) {
                this.currentProjectId = '';
                localStorage.removeItem('current_project_id');
                await this.init();
            }
        } else {
            this.notify(res.message, 'error');
        }
    }

    async saveProject() {
        if (!this.currentProjectId) return;
        this.notify("Synchronizing project configuration...", 'info');
        if (this.view && this.view.saveConfig) {
            await this.view.saveConfig();
        }
    }

    async exportSource() {
        if (!this.currentProjectId) return;
        this.notify("Packaging Flutter source code...", 'info');
        const res = await this.api('export_source', { id: this.currentProjectId });
        if (res.success) {
            this.notify("Source code ready for download.", 'success');
            window.open(res.data.download_url, '_blank');
        } else {
            this.notify(res.message, 'error');
        }
    }

    async loadProject(id) {
        console.log(`[MobileStudio] Loading project: ${id}`);
        const res = await this.api('load_project', { id });
        if (res.success) {
            this.currentProjectId = id;
            localStorage.setItem('current_project_id', id);
            
            const nameEl = document.getElementById('current-project-name');
            if (nameEl) nameEl.innerText = res.data.config.app_name;
            
            try {
                await this.view.onInit(res.data.config);
                this.view.update();
            } catch (initErr) {
                console.error("[MobileStudio] View Initialization Crash:", initErr);
                this.container.innerHTML = `
                    <div class="empty-state" style="color: var(--danger-color); padding: 50px; text-align: center;">
                        <h3 style="font-size: 1.5rem; margin-bottom: 20px;">Component Runtime Crash</h3>
                        <p style="opacity: 0.8; max-width: 500px; margin: 0 auto 30px;">
                            The visual orchestrator encountered a fatal error during project initialization.
                        </p>
                        <div style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px; font-family: monospace; font-size: 0.75rem; text-align: left; margin-bottom: 30px;">
                            ${initErr.message}
                        </div>
                        <button class="btn primary-btn" onclick="location.reload()">Reload Workbench</button>
                    </div>
                `;
            }
            this.renderProjectDropdown();
            
            // Auto-Close Management Modals
            const dd = document.getElementById('project-dropdown');
            if (dd) dd.style.display = 'none';
            
            const portfolio = document.getElementById('portfolio-modal-overlay');
            if (portfolio) portfolio.style.display = 'none';
        } else {
            // Self-Healing: If project is missing, clear and reset
            console.warn(`[MobileStudio] Project ${id} not found. Purging local state.`);
            if (id === this.currentProjectId) {
                this.currentProjectId = '';
                localStorage.removeItem('current_project_id');
                await this.init(); 
            }
            this.notify("Project not found. Reverting to portfolio...", 'warning');
        }
    }

    async createNewProject() {
        const data = await this.studioPrompt("Create Project", "Enter a name and select a starting blueprint.", "Project name...", true);
        if (!data || !data.name) return;

        const res = await this.api('create_project', { name: data.name, blueprint: data.blueprint });
        if (res.success) {
            this.notify(res.message, 'success');
            await this.refreshProjectList();
            await this.loadProject(res.data.project_id);
        } else {
            this.notify(res.message, 'error');
        }
    }

    async deleteCurrentProject() {
        if (!this.currentProjectId) return;
        const confirmed = await this.studioConfirm("Delete Project", `Are you sure you want to delete "${document.getElementById('current-project-name').innerText}"? This action is irreversible.`);
        if (!confirmed) return;

        const res = await this.api('delete_project', { id: this.currentProjectId });
        if (res.success) {
            this.notify("Project deleted.", 'success');
            if (id === this.currentProjectId) {
                this.currentProjectId = '';
                localStorage.removeItem('current_project_id');
                await this.init();
            } else {
                await this.refreshProjectList();
            }
        } else {
            this.notify(res.message, 'error');
        }
    }

    // --- Studio Modal Suite ---
    studioPrompt(title, desc, placeholder = "", showBlueprints = false) {
        return new Promise((resolve) => {
            const overlay = document.getElementById('studio-modal-overlay');
            const input = document.getElementById('modal-input');
            const blueprintSelect = document.getElementById('modal-blueprint');
            const confirmBtn = document.getElementById('modal-confirm-btn');
            const cancelBtn = document.getElementById('modal-cancel-btn');

            document.getElementById('modal-title').innerText = title;
            document.getElementById('modal-desc').innerText = desc;
            input.value = "";
            input.placeholder = placeholder;
            
            const labels = overlay.querySelectorAll('label');
            if (showBlueprints) {
                labels.forEach(l => l.style.display = 'block');
                blueprintSelect.style.display = 'block';
            } else {
                labels.forEach(l => l.style.display = 'none');
                blueprintSelect.style.display = 'none';
            }

            overlay.style.display = 'flex';
            input.focus();

            const cleanup = () => {
                overlay.style.display = 'none';
                confirmBtn.onclick = null;
                cancelBtn.onclick = null;
                document.removeEventListener('keydown', keyHandler);
            };

            const confirmAction = () => {
                const name = input.value.trim();
                const blueprint = blueprintSelect.value;
                cleanup();
                resolve(showBlueprints ? { name, blueprint } : name);
            };

            const cancelAction = () => {
                cleanup();
                resolve(null);
            };

            const keyHandler = (e) => {
                if (e.key === 'Enter') { e.preventDefault(); confirmAction(); }
                if (e.key === 'Escape') { e.preventDefault(); cancelAction(); }
            };

            confirmBtn.onclick = confirmAction;
            cancelBtn.onclick = cancelAction;
            document.addEventListener('keydown', keyHandler);
        });
    }

    studioConfirm(title, desc) {
        return new Promise((resolve) => {
            const overlay = document.getElementById('studio-modal-overlay');
            const input = document.getElementById('modal-input');
            const confirmBtn = document.getElementById('modal-confirm-btn');
            const cancelBtn = document.getElementById('modal-cancel-btn');

            document.getElementById('modal-title').innerText = title;
            document.getElementById('modal-desc').innerText = desc;
            input.style.display = 'none';
            overlay.querySelectorAll('label').forEach(l => l.style.display = 'none');
            document.getElementById('modal-blueprint').style.display = 'none';
            
            overlay.style.display = 'flex';

            const cleanup = () => {
                overlay.style.display = 'none';
                confirmBtn.onclick = null;
                cancelBtn.onclick = null;
                document.removeEventListener('keydown', keyHandler);
            };

            const confirmAction = () => { cleanup(); resolve(true); };
            const cancelAction = () => { cleanup(); resolve(false); };

            const keyHandler = (e) => {
                if (e.key === 'Enter') { e.preventDefault(); confirmAction(); }
                if (e.key === 'Escape') { e.preventDefault(); cancelAction(); }
            };

            confirmBtn.onclick = confirmAction;
            cancelBtn.onclick = cancelAction;
            document.addEventListener('keydown', keyHandler);
        });
    }

    bindGlobalActions() {
        // Portfolio Modal Keyboard Shortcuts
        document.addEventListener('keydown', (e) => {
            const portfolio = document.getElementById('portfolio-modal-overlay');
            if (portfolio && portfolio.style.display === 'flex') {
                if (e.key === 'Escape') {
                    portfolio.style.display = 'none';
                }
            }
        });

        // Project Dropdown Toggle
        document.getElementById('project-menu-btn')?.addEventListener('click', (e) => {
            e.stopPropagation();
            const dd = document.getElementById('project-dropdown');
            dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
        });

        // Build Dropdown Toggle
        document.getElementById('build-menu-btn')?.addEventListener('click', (e) => {
            e.stopPropagation();
            const dd = document.getElementById('build-dropdown');
            dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
        });

        document.addEventListener('click', () => {
            const pdd = document.getElementById('project-dropdown');
            const bdd = document.getElementById('build-dropdown');
            if (pdd) pdd.style.display = 'none';
            if (bdd) bdd.style.display = 'none';
        });

        // Action Buttons
        document.getElementById('create-project-btn')?.addEventListener('click', () => this.createNewProject());
        document.getElementById('open-portfolio-btn')?.addEventListener('click', () => this.openPortfolio());
        document.getElementById('close-portfolio-btn')?.addEventListener('click', () => {
            document.getElementById('portfolio-modal-overlay').style.display = 'none';
        });
        document.getElementById('delete-current-project-btn')?.addEventListener('click', () => this.deleteCurrentProject());
        
        document.getElementById('save-project-btn')?.addEventListener('click', () => {
            this.saveProject();
        });

        document.getElementById('export-source-btn')?.addEventListener('click', () => {
            this.exportSource();
            document.getElementById('build-dropdown').style.display = 'none';
        });

        // Platform Build Bindings
        document.querySelectorAll('#build-dropdown .dropdown-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const platform = e.currentTarget.dataset.platform;
                this.triggerBuild(platform);
            });
        });
    }

    async triggerBuild(platform) {
        if (!this.currentProjectId) return;
        this.notify(`Starting Flutter compilation for ${platform}...`, 'info');
        const res = await this.api('build_flutter', { id: this.currentProjectId, platform });
        if (res.success) {
            this.notify(res.message, 'success');
            // In a real app, we would offer the download link: res.data.artifact
        } else {
            this.notify(res.message, 'error');
        }
    }

    bindNavigation() {
        const links = {
            'nav-studio': 'studio',
            'nav-assets': 'assets',
            'nav-code': 'code'
        };

        Object.entries(links).forEach(([id, mode]) => {
            document.getElementById(id)?.addEventListener('click', (e) => {
                e.preventDefault();
                this.switchView(mode, id);
            });
        });
    }

    switchView(mode, activeId) {
        document.querySelectorAll('.mini-sidebar a').forEach(a => a.classList.remove('active'));
        document.getElementById(activeId)?.classList.add('active');
        if (this.view && this.view.setViewMode) {
            this.view.setViewMode(mode);
        }
    }

    notify(msg, type) {
        if (window.SPPUX && SPPUX.Notify) SPPUX.Notify.show(msg, type);
        else console.log(`[${type}] ${msg}`);
    }

    async api(action, data = {}) {
        const endpoint = window.SPP_CONFIG?.apiEndpoint || 'api.php';
        const res = await fetch(`${endpoint}?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return await res.json();
    }
}

window.mobileStudio = new MobileStudioApp();
