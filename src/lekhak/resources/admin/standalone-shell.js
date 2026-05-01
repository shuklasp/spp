/**
 * Standalone Lekhak Admin Shell
 * 
 * Provides a self-contained runtime for Lekhak reactive components.
 */

class LekhakAdminShell {
    constructor() {
        this.config = window.LEKHAK_CONFIG || {};
        this.container = document.getElementById('view-container');
        this.loader = document.getElementById('view-loader');
        this.titleEl = document.getElementById('view-title');
        this.headerActions = document.getElementById('header-actions');
        
        this.selectedApp = 'lekhak';
        this.currentView = null;
        this.activeComponent = null;

        // Compatibility mapping for existing components
        window.admin = this; 
    }

    async init() {
        this.setupNavigation();
        this.handleInitialRoute();
        
        window.addEventListener('hashchange', () => this.handleInitialRoute());
        console.log("Lekhak Standalone Shell Initialized.");
    }

    setupNavigation() {
        document.querySelectorAll('.nav-link[data-view]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const view = e.currentTarget.getAttribute('data-view');
                location.hash = view;
            });
        });
    }

    handleInitialRoute() {
        const fullHash = location.hash.replace('#', '') || 'dashboard';
        const [view, query] = fullHash.split('?');
        
        const params = {};
        if (query) {
            query.split('&').forEach(pair => {
                const [k, v] = pair.split('=');
                params[k] = decodeURIComponent(v);
            });
        }
        
        this.loadView(view, params);
    }

    async loadView(view, params = {}) {
        this.showLoader(true);
        this.currentView = view;
        this.updateNavUI(view);

        try {
            const viewMap = {
                'dashboard': 'lekhak',
                'content': 'content',
                'canvas': 'canvas',
                'editor': 'editor',
                'settings': 'lekhak'
            };

            const compName = viewMap[view] || 'lekhak';
            const modulePath = `../../comp/${compName}.js`;
            
            const module = await import(modulePath);
            const ComponentClass = module.default;

            // Clear previous actions
            this.headerActions.innerHTML = '';
            
            // Instantiate and mount
            this.activeComponent = new ComponentClass(this, this.container, params);
            
            if (this.activeComponent.onInit) {
                await this.activeComponent.onInit(params);
            }

            this.titleEl.textContent = this.capitalize(view);
            this.activeComponent.update();

            if (this.activeComponent.onMount) {
                await this.activeComponent.onMount();
            }

        } catch (err) {
            console.error("View Load Error:", err);
            this.container.innerHTML = `<div class="error-state"><h3>Load Failure</h3><p>${err.message}</p></div>`;
        } finally {
            this.showLoader(false);
        }
    }

    // --- Workbench Compatibility Layer ---

    async api(action, params = {}) {
        const url = new URL(this.config.apiBase, window.location.origin);
        url.searchParams.append('action', action);
        
        const response = await fetch(url.toString(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(params)
        });
        
        return await response.json();
    }

    async apiPost(action, params = {}) {
        return this.api(action, params);
    }

    notify(message, type = 'info') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icon = type === 'success' ? '✅' : (type === 'error' ? '❌' : 'ℹ️');
        toast.innerHTML = `<span>${icon}</span> <span>${message}</span>`;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    openAppView(view) {
        location.hash = view;
    }

    openModuleSettings(modname, title) {
        this.notify(`Settings for ${title} are only available in the full SPP Admin workbench.`, 'info');
    }

    onAppContextChange(app) {
        console.log("App context change requested (Standalone):", app);
        // In standalone mode, we stay in 'lekhak' context
    }

    // --- UI Helpers ---

    showLoader(show) {
        this.loader.style.opacity = show ? '1' : '0';
        this.loader.style.pointerEvents = show ? 'all' : 'none';
    }

    updateNavUI(view) {
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.toggle('active', link.getAttribute('data-view') === view);
        });
    }

    capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    truncatePath(path, len = 40) {
        if (!path || path.length <= len) return path;
        return '...' + path.slice(-len);
    }
}

// Auto-init
const shell = new LekhakAdminShell();
shell.init();
export default shell;
