/**
 * Standalone Lekhak Admin Shell
 * 
 * Provides a self-contained runtime for Lekhak reactive components.
 */

class LekhakAdminShell {
    constructor() {
        this.config = window.LEKHAK_CONFIG || {};
        this.container = document.getElementById('view-container') || 
                         document.querySelector('.main-content') || 
                         document.querySelector('main') || 
                         document.querySelector('.animate-fade') || 
                         document.body;
        this.loader = document.getElementById('view-loader') || { style: {} };
        this.titleEl = document.getElementById('view-title') || { textContent: '' };
        this.headerActions = document.getElementById('header-actions') || { innerHTML: '' };
        
        this.selectedApp = 'lekhak';
        this.currentView = null;
        this.activeComponent = null;
        this.version = '2026_05_20_v1';

        // Compatibility mapping for existing components
        window.admin = this; 
        this.api = this.api.bind(this);
        this.apiPost = this.apiPost.bind(this);
    }

    async init() {
        this.setupNavigation();
        this.handleInitialRoute();
        
        window.addEventListener('hashchange', () => this.handleInitialRoute());
        console.log("Lekhak Standalone Shell Initialized.");
        
        // Preload key views in background after a short delay
        setTimeout(() => this.preloadViews(), 1500);
    }

    preloadViews() {
        const components = ['lekhak', 'content', 'editor'];
        const nocache = new URLSearchParams(window.location.search).has('nocache') || this.config.debug;
        const cacheBuster = nocache ? `t=${Date.now()}` : `v=${this.version}`;
        components.forEach(comp => {
            const modulePath = `../../comp/${comp}.js?${cacheBuster}`;
            import(modulePath).catch(() => {});
        });
    }

    setupNavigation() {
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a');
            if (!link) return;

            // Allow PHP-backed routes to pass through for full page navigation
            if (link.getAttribute('data-php-route') === 'true') {
                // Let the browser handle this link naturally (full page load)
                return;
            }
            
            const href = link.getAttribute('href');
            
            const possibleViews = ['dashboard', 'lekhak', 'content', 'canvas', 'commerce', 'translations', 'editor', 'settings', 'media', 'structure', 'blocks', 'views', 'lekhak_forum', 'lekhak_community', 'lekhak_qa', 'lekhak_newsletter', 'lekhak_popups', 'lekhak_academy', 'lekhak_helpdesk', 'lekhak_events', 'lekhak_classifieds', 'lekhak_realestate', 'lekhak_healthcare', 'lekhak_donations', 'lekhak_gallery', 'lekhak_portfolio', 'lekhak_documents', 'lekhak_widgets', 'lekhak_lightbox', 'lekhak_subscriptions', 'lekhak_memberships', 'lekhak_backend_shield', 'lekhak_journal', 'lekhak_reviews', 'lekhak_glossary', 'lekhak_reading_time', 'lekhak_authors', 'lekhak_migrations', 'lekhak_webhooks', 'lekhak_ab_testing', 'lekhak_audit_trail', 'lekhak_pwa', 'lekhak_pdf', 'lekhak_watermark', 'lekhak_affiliates', 'lekhak_gdpr', 'lekhak_search_pro'];
            let targetView = null;
            
            for (const v of possibleViews) {
                if (link.getAttribute('data-view') === v || link.getAttribute('data-spp-evt')?.replace('nav-', '') === v || (href && (href.endsWith('#' + v) || href === '#' + v))) {
                    targetView = v;
                    break;
                }
            }
            
            if (targetView) {
                e.preventDefault();
                const targetHash = targetView === 'dashboard' ? 'dashboard' : targetView;
                if (location.hash.replace('#', '') !== targetHash) {
                    location.hash = targetHash;
                }
            }
        });
    }

    handleInitialRoute() {
        const fullHash = location.hash.replace('#', '') || 'lekhak';
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
        if (this.loader && this.loader.style) {
            this.showLoader(true);
        }
        if (this.activeComponent && typeof this.activeComponent.dispose === 'function') {
            console.log(`[StandaloneShell] Disposing previous component: ${this.activeComponent.constructor.name}`);
            this.activeComponent.dispose();
        }

        this.currentView = view;
        this.updateNavUI(view);

        if (view === 'dashboard') view = 'lekhak';

        try {
            const viewMap = {
                'dashboard': 'lekhak',
                'lekhak': 'lekhak',
                'content': 'content',
                'canvas': 'canvas',
                'commerce': 'commerce',
                'translations': 'translations',
                'editor': 'editor',
                'settings': 'settings',
                'media': 'media',
                'structure': 'structure',
                'blocks': 'blocks',
                'views': 'views',
                'lekhak_forum': 'lekhak_forum',
                'lekhak_community': 'lekhak_community',
                'lekhak_qa': 'lekhak_qa',
                'lekhak_newsletter': 'lekhak_newsletter',
                'lekhak_popups': 'lekhak_popups',
                'lekhak_academy': 'lekhak_academy',
                'lekhak_helpdesk': 'lekhak_helpdesk',
                'lekhak_events': 'lekhak_events',
                'lekhak_classifieds': 'lekhak_classifieds',
                'lekhak_realestate': 'lekhak_realestate',
                'lekhak_healthcare': 'lekhak_healthcare',
                'lekhak_donations': 'lekhak_donations',
                'lekhak_gallery': 'lekhak_gallery',
                'lekhak_portfolio': 'lekhak_portfolio',
                'lekhak_documents': 'lekhak_documents',
                'lekhak_widgets': 'lekhak_widgets',
                'lekhak_lightbox': 'lekhak_lightbox',
                'lekhak_subscriptions': 'lekhak_subscriptions',
                'lekhak_memberships': 'lekhak_memberships',
                'lekhak_backend_shield': 'lekhak_backend_shield',
                'lekhak_journal': 'lekhak_journal',
                'lekhak_reviews': 'lekhak_reviews',
                'lekhak_glossary': 'lekhak_glossary',
                'lekhak_reading_time': 'lekhak_reading_time',
                'lekhak_authors': 'lekhak_authors',
                'lekhak_migrations': 'lekhak_migrations',
                'lekhak_webhooks': 'lekhak_webhooks',
                'lekhak_ab_testing': 'lekhak_ab_testing',
                'lekhak_audit_trail': 'lekhak_audit_trail',
                'lekhak_pwa': 'lekhak_pwa',
                'lekhak_pdf': 'lekhak_pdf',
                'lekhak_watermark': 'lekhak_watermark',
                'lekhak_affiliates': 'lekhak_affiliates',
                'lekhak_gdpr': 'lekhak_gdpr',
                'lekhak_search_pro': 'lekhak_search_pro'
            };

            const compName = viewMap[view] || 'lekhak';
            const nocache = new URLSearchParams(window.location.search).has('nocache') || this.config.debug;
            const cacheBuster = nocache ? `t=${Date.now()}` : `v=${this.version}`;
            const modulePath = `../../comp/${compName}.js?${cacheBuster}`;
            
            // Start fetching content in parallel if we have an ID for the editor
            let contentPromise = null;
            if (view === 'editor' && params.id) {
                console.log(`[StandaloneShell] Initiating parallel content fetch for ID: ${params.id}`);
                contentPromise = this.api('get_node', { id: params.id });
            }

            console.log(`[StandaloneShell] Loading module: ${modulePath}`);
            const module = await import(modulePath);
            const ComponentClass = module.default;

            // Clear previous actions and container contents
            if (this.headerActions && this.headerActions.innerHTML !== undefined) {
                this.headerActions.innerHTML = '';
            }
            if (this.container) {
                this.container.innerHTML = '';
            }
            
            // Pass the in-progress promise to component props
            if (contentPromise) {
                params.contentPromise = contentPromise;
            }

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
        url.searchParams.set('action', action);
        
        const response = await fetch(url.toString(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(params)
        });

        if (!response.ok) {
            return { success: false, message: `API request failed with HTTP ${response.status}` };
        }

        return await response.json();
    }

    async apiPost(action, params = {}) {
        return this.api(action, params);
    }

    notify(message, type = 'info') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = 'position:fixed; bottom:24px; right:24px; z-index:10000; display:flex; flex-direction:column; gap:12px; pointer-events:none;';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.style.cssText = 'background:#1e293b; color:#f8fafc; padding:12px 20px; border-radius:8px; border-left:4px solid ' + (type === 'success' ? '#10b981' : (type === 'error' ? '#ef4444' : '#38bdf8')) + '; box-shadow:0 10px 25px rgba(0,0,0,0.3); display:flex; align-items:center; gap:10px; font-family:system-ui,sans-serif; font-size:0.9rem; transition:opacity 0.3s, transform 0.3s; pointer-events:all;';
        
        const icon = type === 'success' ? '✅' : (type === 'error' ? '❌' : 'ℹ️');
        toast.innerHTML = `<span style="font-size:1.1rem;">${icon}</span> <span>${message}</span>`;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
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
