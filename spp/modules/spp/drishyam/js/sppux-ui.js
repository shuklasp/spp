/**
 * SPP-UX Visual Component Library (v12 - Legendary Universe Edition)
 *
 * The ultimate expansion. Includes Theme Management, Confetti Physics,
 * and advanced component extensions.
 */

(function(SPPUX) {
    if (!SPPUX) {
        console.error("SPPUX Core not found.");
        return;
    }

    const BaseComponent = SPPUX.BaseComponent || (typeof window !== 'undefined' ? window.BaseComponent : class {});
    const html = SPPUX.html || (typeof window !== 'undefined' ? window.html : (s, ...v) => s.join(''));
    const Fragment = SPPUX.Fragment || (typeof window !== 'undefined' ? window.Fragment : '');

    /**
     * Theme Manager (Legendary) - Upgraded to SPPUX Global Store
     */
    const _themeSchemes = {
        midnight: { primary: '#4f46e5', panel: 'rgba(14, 16, 28, 0.98)', glow: 'rgba(99, 102, 241, 0.4)', text: '#f3f4f6', bgDark: '#06070d', bgSurface: '#0d0f18' },
        night: { primary: '#4f46e5', panel: 'rgba(14, 16, 28, 0.98)', glow: 'rgba(99, 102, 241, 0.4)', text: '#f3f4f6', bgDark: '#06070d', bgSurface: '#0d0f18' },
        day: { isLight: true, primary: '#2563eb', panel: '#ffffff', glow: 'rgba(37, 99, 235, 0.15)', text: '#0f172a', bgDark: '#f8fafc', bgSurface: '#ffffff' },
        emerald: { primary: '#047857', panel: 'rgba(6, 78, 59, 0.98)', glow: 'rgba(16, 185, 129, 0.4)', text: '#f3f4f6', bgDark: '#022c22', bgSurface: '#043b2c' },
        royal: { primary: '#6d28d9', panel: 'rgba(46, 16, 101, 0.98)', glow: 'rgba(139, 92, 246, 0.4)', text: '#f3f4f6', bgDark: '#1e053a', bgSurface: '#2e1052' },
        cyberpunk: { primary: '#c026d3', panel: 'rgba(20, 0, 40, 0.98)', glow: 'rgba(255, 0, 255, 0.4)', text: '#ffffff', bgDark: '#0d001a', bgSurface: '#17002e' },
        ocean: { primary: '#0369a1', panel: 'rgba(7, 89, 133, 0.98)', glow: 'rgba(14, 165, 233, 0.4)', text: '#f3f4f6', bgDark: '#03293e', bgSurface: '#053c5a' },
        saffron: { isLight: true, primary: '#c2410c', panel: 'rgba(255, 247, 237, 0.98)', glow: 'rgba(255, 153, 51, 0.4)', text: '#431407', bgDark: '#fff7ed', bgSurface: '#ffedd5' }
    };

    SPPUX.Theme = (SPPUX.createStore ? SPPUX.createStore({ current: 'midnight' }) : { current: 'midnight' });
    
    SPPUX.Theme.schemes = _themeSchemes;
    SPPUX.Theme.set = function(name, manualOverride = false) {
        const theme = _themeSchemes[name];
        if (!theme) return;
        this.current = name; // If it's a proxy store, this triggers reactivity globally!
        const root = document.documentElement;
        
        if (theme.isLight || name === 'day') {
            root.setAttribute('data-theme', 'day');
        } else {
            root.removeAttribute('data-theme');
        }

        root.style.setProperty('--sppux-primary', theme.primary);
        root.style.setProperty('--sppux-panel', theme.panel);
        root.style.setProperty('--sppux-primary-glow', theme.glow);
        root.style.setProperty('--sppux-text', theme.text || '#f3f4f6');
        if (theme.bgDark) root.style.setProperty('--sppux-bg-dark', theme.bgDark);
        if (theme.bgSurface) root.style.setProperty('--sppux-bg-surface', theme.bgSurface);
        document.body.classList.add('sppux-theme-transitioning');
        setTimeout(() => document.body.classList.remove('sppux-theme-transitioning'), 600);
        localStorage.setItem('sppux_theme', name);
        if (manualOverride) localStorage.setItem('sppux_theme_override', 'true');
    };
    SPPUX.Theme.init = function() {
        const saved = localStorage.getItem('sppux_theme');
        if (saved) {
            this.set(saved);
        } else if (window.matchMedia) {
            this.set(window.matchMedia('(prefers-color-scheme: light)').matches ? 'day' : 'night');
        }
        
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', e => {
                if (!localStorage.getItem('sppux_theme_override')) {
                    this.set(e.matches ? 'day' : 'night');
                }
            });
        }
    };

    /**
     * Confetti Physics (For "Victory" moments)
     */
    SPPUX.Celebrate = {
        burst() {
            const container = document.createElement('div');
            container.style.position = 'fixed';
            container.style.top = '0'; container.style.left = '0';
            container.style.width = '100vw'; container.style.height = '100vh';
            container.style.pointerEvents = 'none'; container.style.zIndex = '99999';
            document.body.appendChild(container);

            for (let i = 0; i < 50; i++) {
                const conf = document.createElement('div');
                conf.className = 'sppux-confetti';
                conf.style.left = Math.random() * 100 + 'vw';
                conf.style.backgroundColor = `hsl(${Math.random() * 360}, 70%, 60%)`;
                conf.style.animationDelay = Math.random() * 2 + 's';
                conf.style.transform = `rotate(${Math.random() * 360}deg)`;
                container.appendChild(conf);
            }
            setTimeout(() => container.remove(), 4000);
        }
    };

    /**
     * Modal Helper
     */
    SPPUX.Modal = {
        open(title, content, actions = [], context = {}) {
            // Ensure content is treated as HTML if it contains tags
            if (typeof content === 'string' && content.includes('<')) {
                content = new SPPUX.TrustedHTML(content);
            }
            
            // Resolve string actions to functions while preserving metadata (label, type)
            const resolvedActions = (actions && actions.length ? actions : [{ label: 'Close', type: 'secondary', fn: 'close' }]).map(act => {
                let resolvedFn = act.fn;
                if (typeof act.fn === 'string') {
                    const actionName = act.fn;
                    resolvedFn = (modal) => {
                        if (window.admin && typeof admin.handleModalAction === 'function') {
                            admin.handleModalAction(actionName, modal, { title, content, actions }, context);
                        } else {
                            if (actionName === 'close') modal.close();
                            else console.warn(`Unhandled modal action: ${actionName}`);
                        }
                    };
                }
                return { ...act, fn: resolvedFn };
            });

            SPPUX.Modal.close(); // Gracefully trigger lifecycle close on active instance
            
            let container = document.getElementById('sppux-modal-root');
            if (container) container.remove(); // Force immediate DOM removal to avoid duplicate IDs during the 50ms animation timeout
            
            container = document.createElement('div');
            container.id = 'sppux-modal-root';
            document.body.appendChild(container);

            const modal = new (class extends BaseComponent {
                onInit() {
                    this.close = this.close.bind(this);
                    this.state = {
                        title: this.props.title,
                        content: this.props.content,
                        actions: this.props.actions
                    };
                }
                
                render() {
                    const title = this.state.title || this.props.title;
                    const content = this.state.content || this.props.content;
                    const actions = this.state.actions || this.props.actions || [];
                    return html`
                        <div class="glass-overlay active">
                            <div class="glass-panel modal-box sppux-modal-animate">
                                <div class="modal-header">
                                    <h3>${title}</h3>
                                    <button class="close-icon" @click=${() => this.close()}>✕</button>
                                </div>
                                <div class="modal-body">
                                    ${content}
                                </div>
                                <div class="modal-footer">
                                    ${actions.map(act => html`
                                        <button class="btn ${act.type || 'secondary'}-btn" @click=${() => act.fn(this)}>
                                            <span>${act.label || act.text || act.title || 'OK'}</span>
                                        </button>
                                    `)}
                                </div>
                            </div>
                        </div>
                    `;
                }

                onMount() {
                    this._escListener = (e) => {
                        if (e.key === 'Escape' || e.key === 'Esc') {
                            this.close();
                        }
                    };
                    
                    this._tabListener = (e) => {
                        if (e.key === 'Tab') {
                            const focusable = this.container.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                            if (focusable.length) {
                                const first = focusable[0];
                                const last = focusable[focusable.length - 1];
                                if (e.shiftKey && document.activeElement === first) {
                                    e.preventDefault();
                                    last.focus();
                                } else if (!e.shiftKey && document.activeElement === last) {
                                    e.preventDefault();
                                    first.focus();
                                }
                            }
                        }
                    };

                    document.addEventListener('keydown', this._escListener);
                    this.container.addEventListener('keydown', this._tabListener);
                    
                    setTimeout(() => {
                        const focusable = this.container.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                        if (focusable.length && focusable[0].id !== 'sppux-prompt-input') focusable[0].focus();
                        else if (document.getElementById('sppux-prompt-input')) document.getElementById('sppux-prompt-input').focus();
                    }, 50);
                    
                    const body = this.container.querySelector('.modal-body');
                    if (body && window.SPPForm) {
                        try { SPPForm.autoInit(body); } catch(e) { console.warn('Form autoInit error', e); }
                    }
                }

                close() {
                    if (this._escListener) {
                        document.removeEventListener('keydown', this._escListener);
                        this._escListener = null;
                    }
                    if (this._tabListener && this.container) {
                        this.container.removeEventListener('keydown', this._tabListener);
                        this._tabListener = null;
                    }
                    if (SPPUX._activeModalInstance === this) {
                        SPPUX._activeModalInstance = null;
                    }
                    if (this.container) {
                        this.container.classList.add('sppux-modal-closing');
                        setTimeout(() => {
                            try { this.dispose(); } catch(e) { console.warn('Modal dispose error', e); }
                            if (this.container) this.container.remove();
                        }, 50);
                    }
                }
            })(null, container, { title, content, actions: resolvedActions });
            modal.update();
            modal.onMount();
            return modal;
        },
        close() {
            if (SPPUX._activeModalInstance) {
                SPPUX._activeModalInstance.close();
                SPPUX._activeModalInstance = null;
            } else {
                const root = document.getElementById('sppux-modal-root');
                if (root) root.remove();
            }
        }
    };

    SPPUX._activeModalInstance = null;

    SPPUX.openModal = (title, content, actions = [], context = {}) => {
        SPPUX._activeModalInstance = SPPUX.Modal.open(title, content, actions, context);
        return SPPUX._activeModalInstance;
    };
    
    SPPUX.updateModal = (title, content, actions = [], context = {}) => {
        if (SPPUX._activeModalInstance && !SPPUX._activeModalInstance.isDisposed) {
            // Re-resolve actions
            const resolvedActions = (actions && actions.length ? actions : [{ label: 'Close', type: 'secondary', fn: 'close' }]).map(act => {
                let resolvedFn = act.fn;
                if (typeof act.fn === 'string') {
                    const actionName = act.fn;
                    resolvedFn = (modal) => {
                        if (window.admin && typeof admin.handleModalAction === 'function') {
                            admin.handleModalAction(actionName, modal, { title, content, actions }, context);
                        } else {
                            if (actionName === 'close') modal.close();
                            else console.warn(`Unhandled modal action: ${actionName}`);
                        }
                    };
                }
                return { ...act, fn: resolvedFn };
            });

            if (typeof content === 'string' && content.includes('<')) {
                content = new SPPUX.TrustedHTML(content);
            }
            
            SPPUX._activeModalInstance.setState({
                title: title || SPPUX._activeModalInstance.state.title,
                content: content,
                actions: actions && actions.length ? resolvedActions : SPPUX._activeModalInstance.state.actions
            });
            return SPPUX._activeModalInstance;
        }
        return SPPUX.openModal(title, content, actions, context);
    };
    
    SPPUX.Prompt = {
        show(title, message, callback) {
            const content = SPPUX.html`
                <div class="input-group" style="padding-top: 10px;">
                    <label style="display:block; margin-bottom: 10px; opacity: 0.8;">${message}</label>
                    <input type="text" id="sppux-prompt-input" class="spp-element" style="width:100%; padding: 12px; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); border-radius: 8px; color: white;" autofocus>
                </div>
            `;
            SPPUX.Modal.open(title, content, [
                { label: 'Cancel', type: 'secondary', fn: (m) => m.close() },
                { label: 'Confirm', type: 'primary', fn: (m) => {
                    const val = document.getElementById('sppux-prompt-input').value;
                    callback(val);
                    m.close();
                }}
            ]);
            setTimeout(() => document.getElementById('sppux-prompt-input')?.focus(), 100);
        }
    };

    /**
     * Universal Confirmation Dialog
     */
    SPPUX.Confirm = (msg) => {
        return new Promise((resolve) => {
            SPPUX.Modal.open('Confirm', msg, [
                { label: 'Cancel', type: 'secondary', fn: (m) => { m.close(); resolve(false); } },
                { label: 'Confirm', type: 'primary', fn: (m) => { m.close(); resolve(true); } }
            ]);
        });
    };

    /**
     * Sub-Editor / Visual Designer Helper
     */
    SPPUX.openSubEditor = (title, content, data = {}, onSave = null) => {
        const subModal = document.createElement('div');
        subModal.className = 'glass-overlay active sub-modal';
        subModal.style.zIndex = '4000';
        const contentDiv = document.createElement('div');
        contentDiv.className = 'modal-content glass-panel sppux-sub-editor-animate';
        contentDiv.style.cssText = 'width: 80vw; max-width: 1000px; height: 80vh; background: var(--sppux-panel); display: flex; flex-direction: column;';
        
        const headerDiv = document.createElement('div');
        headerDiv.className = 'modal-header';
        const h3 = document.createElement('h3');
        h3.textContent = title;
        const closeBtnEl = document.createElement('button');
        closeBtnEl.className = 'close-btn';
        closeBtnEl.textContent = '✕';
        headerDiv.appendChild(h3);
        headerDiv.appendChild(closeBtnEl);
        
        const bodyDiv = document.createElement('div');
        bodyDiv.className = 'modal-body';
        bodyDiv.id = 'sub-editor-body';
        bodyDiv.style.cssText = 'flex: 1; overflow-y: auto; padding: 1.5rem;';
        
        const footerDiv = document.createElement('div');
        footerDiv.className = 'modal-footer';
        const cancelBtnEl = document.createElement('button');
        cancelBtnEl.className = 'btn secondary-btn';
        cancelBtnEl.textContent = 'Cancel';
        const saveBtnEl = document.createElement('button');
        saveBtnEl.className = 'btn primary-btn';
        saveBtnEl.id = 'sub-modal-save';
        saveBtnEl.textContent = 'Apply Changes';
        footerDiv.appendChild(cancelBtnEl);
        footerDiv.appendChild(saveBtnEl);
        
        contentDiv.appendChild(headerDiv);
        contentDiv.appendChild(bodyDiv);
        contentDiv.appendChild(footerDiv);
        subModal.appendChild(contentDiv);
        document.body.appendChild(subModal);

        const escListener = (e) => {
            if (e.key === 'Escape') {
                document.removeEventListener('keydown', escListener);
                subModal.remove();
            }
        };
        document.addEventListener('keydown', escListener);

        // Add cleanup to close button
        const closeBtn = subModal.querySelector('.close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                document.removeEventListener('keydown', escListener);
                subModal.remove();
            });
        }
        
        const cancelBtn = subModal.querySelector('.secondary-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                document.removeEventListener('keydown', escListener);
                subModal.remove();
            });
        }

        const body = subModal.querySelector('#sub-editor-body');
        if (typeof content === 'string') {
            body.innerHTML = content;
        } else if (content && typeof content.render === 'function') {
            content.render(body);
        } else if (typeof SPPUX !== 'undefined' && typeof SPPUX.render === 'function') {
            SPPUX.render(content, body);
        } else if (typeof render === 'function') {
            render(content, body);
        } else if (content && typeof content.toString === 'function') {
            body.innerHTML = content.toString();
        }
        
        // Initialize reactive dependencies if SPPForm is available
        if (window.SPPForm) SPPForm.autoInit(body);

        const saveBtn = subModal.querySelector('#sub-modal-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', async () => {
                const form = body.querySelector('form');
                let resultData = {};
                if (form) {
                    const fd = new FormData(form);
                    fd.forEach((v, k) => resultData[k] = v);
                } else {
                    // Manual extraction if no form
                    body.querySelectorAll('input, select, textarea').forEach(el => {
                        if (el.name) resultData[el.name] = el.type === 'checkbox' ? el.checked : el.value;
                    });
                }
                if (onSave) await onSave(resultData);
                document.removeEventListener('keydown', escListener);
                subModal.remove();
            });
        }

        // Fill initial data
        for (let [k, v] of Object.entries(data)) {
            const el = body.querySelector(`[name="${k}"], [id="${k}"]`);
            if (el) {
                if (el.type === 'checkbox') el.checked = !!v;
                else el.value = v;
            }
        }

        return subModal;
    };

    SPPUX.updateSubEditor = (content) => {
        const body = document.querySelector('.sub-modal #sub-editor-body');
        if (body) {
            if (typeof content === 'string') {
                body.innerHTML = content;
            } else if (content && typeof content.render === 'function') {
                content.render(body);
            } else if (typeof SPPUX !== 'undefined' && typeof SPPUX.render === 'function') {
                SPPUX.render(content, body);
            } else if (typeof render === 'function') {
                render(content, body);
            } else if (content && typeof content.toString === 'function') {
                body.innerHTML = content.toString();
            }
            if (window.SPPForm) SPPForm.autoInit(body);
        }
    };

    /**
     * Popover Helper
     */
    SPPUX.Popover = {
        open(triggerEl, title, content) {
            const existing = document.querySelector('.sppux-popover');
            if (existing) existing.remove();
            const pop = document.createElement('div'); pop.className = 'sppux-popover';
            const header = document.createElement('div');
            header.className = 'sppux-popover-header';
            const b = document.createElement('b');
            b.textContent = title;
            header.appendChild(b);
            
            const body = document.createElement('div');
            body.className = 'sppux-popover-body';
            // We use DOM methods if possible, but content might be complex string. We will append directly.
            if (typeof content === 'string') {
                body.innerHTML = content;
            } else {
                body.appendChild(content);
            }
            
            pop.appendChild(header);
            pop.appendChild(body);
            document.body.appendChild(pop);
            const rect = (triggerEl && typeof triggerEl.getBoundingClientRect === 'function') ? triggerEl.getBoundingClientRect() : { left: 100, bottom: 100 };
            pop.style.position = 'fixed'; pop.style.left = rect.left + 'px'; pop.style.top = rect.bottom + 8 + 'px';
            const close = (e) => { 
                if (e.type === 'keydown' && e.key === 'Escape') { pop.remove(); cleanup(); } 
                else if (e.type === 'mousedown' && !pop.contains(e.target) && (!triggerEl || typeof triggerEl.contains !== 'function' || !triggerEl.contains(e.target))) { pop.remove(); cleanup(); } 
            };
            const cleanup = () => { document.removeEventListener('mousedown', close); document.removeEventListener('keydown', close); };
            document.addEventListener('mousedown', close);
            document.addEventListener('keydown', close);
            return pop;
        }
    };

    /**
     * Stepper Component
     */
    SPPUX.Stepper = {
        render(steps, activeIndex) {
            return html`
                <div class="sppux-stepper">
                    ${steps.map((step, idx) => html`
                        <div class="sppux-step ${idx <= activeIndex ? 'active' : ''} ${idx < activeIndex ? 'completed' : ''}">
                            <div class="sppux-step-circle">${idx < activeIndex ? '✓' : idx + 1}</div>
                            <div class="sppux-step-label">${step}</div>
                            ${idx < steps.length - 1 ? html`<div class="sppux-step-line"></div>` : Fragment}
                        </div>
                    `)}
                </div>
            `;
        }
    };

    /**
     * Spotlight / Command Palette
     */
    SPPUX.Spotlight = {
        open(items, onSelect) {
            let container = document.getElementById('sppux-spotlight-root') || (document.body.appendChild(Object.assign(document.createElement('div'), {id:'sppux-spotlight-root'})));
            const spotlight = new (class extends BaseComponent {
                onInit() { this.setState({ query: '', filtered: this.props.items }); }
                onMount() { 
                    setTimeout(() => {
                        this.container.querySelector('input')?.focus(); 
                    }, 50);
                    this._escListener = (e) => { if (e.key === 'Escape') this.close(); };
                    document.addEventListener('keydown', this._escListener);
                }
                render() {
                    const { query, filtered } = this.state;
                    return html`
                        <div class="sppux-spotlight-overlay active" @click=${(e) => e.target.classList.contains('sppux-spotlight-overlay') && this.close()}>
                            <div class="sppux-spotlight">
                                <div class="sppux-spotlight-search"><span>🔍</span><input type="text" placeholder="Search..." value="${query}" @input=${(e) => this.filter(e.target.value)}><span class="sppux-spotlight-esc">ESC</span></div>
                                <div class="sppux-spotlight-results">${filtered.map(item => html`<div class="sppux-spotlight-item" @click=${() => { this.props.onSelect(item); this.close(); }}><span class="sppux-item-icon">${item.icon || '📄'}</span><div class="sppux-item-info"><div class="sppux-item-title">${item.title}</div><div class="sppux-item-desc">${item.desc || ''}</div></div></div>`)}</div>
                            </div>
                        </div>
                    `;
                }
                filter(q) { this.setState({ query: q, filtered: this.props.items.filter(i => i.title.toLowerCase().includes(q.toLowerCase())) }); }
                close() { 
                    if (this._escListener) document.removeEventListener('keydown', this._escListener);
                    const overlay = this.container.querySelector('.sppux-spotlight-overlay');
                    if (overlay) overlay.classList.remove('active');
                    setTimeout(() => {
                        this.container.innerHTML = '';
                        this.dispose();
                    }, 100); 
                }
            })(null, container, { items, onSelect });
            if (typeof spotlight.onInit === 'function') spotlight.onInit();
            spotlight.update();
            spotlight.onMount();
            return spotlight;
        },
        bindKey(key = 'k', itemsOrProvider, onSelect) {
            const listener = (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === key.toLowerCase()) {
                    e.preventDefault();
                    const items = typeof itemsOrProvider === 'function' ? itemsOrProvider() : itemsOrProvider;
                    SPPUX.Spotlight.open(items, onSelect || ((item) => {
                        if (item.action) item.action(item);
                        else if (item.href) window.location.href = item.href;
                    }));
                }
            };
            window.addEventListener('keydown', listener);
            return () => window.removeEventListener('keydown', listener);
        }
    };

    /**
     * Notify / Toast Notification Stack
     */
    SPPUX.Notify = { 
        show(m, t = 'info', d = 4000, o = {}) { 
            if(t === 'success') this.elegantSuccess(); 
            let container = document.getElementById('sppux-toast-root');
            if (!container) {
                container = document.createElement('div');
                container.id = 'sppux-toast-root';
                container.style.cssText = 'position: fixed; top: 24px; right: 24px; z-index: 999999; display: flex; flex-direction: column; gap: 10px; max-width: 380px; width: calc(100vw - 48px); pointer-events: none;';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div'); 
            toast.className = `sppux-toast sppux-toast-${t}`; 
            toast.style.cssText = 'pointer-events: auto; display: flex; flex-direction: column; background: var(--sppux-panel, rgba(15,23,42,0.95)); border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.12)); border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); overflow: hidden; backdrop-filter: blur(12px); color: var(--sppux-text, #f3f4f6); font-family: inherit; transition: all 0.25s ease-out; transform: translateY(-10px); opacity: 0;';

            const iconMap = { success: '✓', error: '✕', danger: '✕', warning: '⚠️', info: 'ℹ️' };
            const icon = o.icon || iconMap[t] || 'ℹ️';
            const colorMap = { success: '#22c55e', error: '#ef4444', danger: '#ef4444', warning: '#f59e0b', info: '#38bdf8' };
            const accentColor = colorMap[t] || '#38bdf8';

            const header = document.createElement('div');
            header.style.cssText = 'display: flex; align-items: center; justify-content: space-between; padding: 10px 14px 4px 14px; gap: 10px;';

            const iconSpan = document.createElement('span');
            iconSpan.style.cssText = `font-weight: bold; font-size: 1.1rem; color: ${accentColor};`;
            iconSpan.textContent = icon;

            const titleSpan = document.createElement('span');
            titleSpan.style.cssText = 'font-weight: 600; font-size: 0.9rem; flex: 1;';
            titleSpan.textContent = o.title || (t.charAt(0).toUpperCase() + t.slice(1));

            const closeBtn = document.createElement('button');
            closeBtn.textContent = '✕';
            closeBtn.style.cssText = 'background: transparent; border: none; color: inherit; opacity: 0.6; cursor: pointer; font-size: 0.9rem; padding: 0 4px;';
            closeBtn.onclick = () => removeToast();

            header.appendChild(iconSpan);
            header.appendChild(titleSpan);
            header.appendChild(closeBtn);
            toast.appendChild(header);

            const body = document.createElement('div');
            body.style.cssText = 'padding: 4px 14px 12px 14px; font-size: 0.85rem; opacity: 0.9; line-height: 1.4;';
            if (typeof m === 'string') body.textContent = m;
            else if (m instanceof Node) body.appendChild(m);
            toast.appendChild(body);

            if (o.action && typeof o.action.fn === 'function') {
                const actionRow = document.createElement('div');
                actionRow.style.cssText = 'padding: 0 14px 10px 14px; display: flex; justify-content: flex-end;';
                const actBtn = document.createElement('button');
                actBtn.className = 'btn primary-btn btn-sm';
                actBtn.textContent = o.action.label || 'Action';
                actBtn.onclick = () => { o.action.fn(); removeToast(); };
                actionRow.appendChild(actBtn);
                toast.appendChild(actionRow);
            }

            let progBar = null;
            if (d > 0) {
                progBar = document.createElement('div');
                progBar.style.cssText = `height: 3px; width: 100%; background: ${accentColor}; transition: width ${d}ms linear;`;
                toast.appendChild(progBar);
            }

            container.appendChild(toast);

            // Animate In
            requestAnimationFrame(() => {
                toast.style.transform = 'translateY(0)';
                toast.style.opacity = '1';
                if (progBar) {
                    requestAnimationFrame(() => { progBar.style.width = '0%'; });
                }
            });

            const timer = d > 0 ? setTimeout(() => removeToast(), d) : null;

            function removeToast() {
                if (timer) clearTimeout(timer);
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(20px)';
                setTimeout(() => toast.remove(), 250);
            }
        },
        elegantSuccess() {
            const beam = document.createElement('div');
            beam.className = 'sppux-success-beam';
            document.body.appendChild(beam);
            setTimeout(() => {
                beam.classList.add('fade-out');
                setTimeout(() => beam.remove(), 800);
            }, 200);
        }
    };
    SPPUX.StatsCard = { 
        render(t, v, o = {}) { 
            const { trend = null, trendType = 'success', icon = '📈', sparkline = null, sparklineType = 'line' } = o; 
            return html`
                <div class="sppux-stats-card" style="padding: 16px; border-radius: var(--sppux-radius-md, 10px); background: var(--sppux-panel, rgba(15,23,42,0.8)); border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.1)); display: flex; flex-direction: column; gap: 8px;">
                    <div class="sppux-stats-header" style="display: flex; justify-content: space-between; align-items: center; opacity: 0.8; font-size: 0.85rem;">
                        <span>${t}</span>
                        <span>${icon}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                        <div>
                            <div class="sppux-stats-value" style="font-size: 1.6rem; font-weight: 700; color: var(--sppux-text, #f8fafc);">${v}</div>
                            ${trend ? html`<div class="sppux-trend-${trendType}" style="font-size: 0.8rem; margin-top: 4px; color: ${trendType === 'success' ? '#22c55e' : '#ef4444'};">${trend}</div>` : Fragment}
                        </div>
                        ${sparkline && SPPUX.Chart ? SPPUX.Chart.renderSparkline(sparkline, sparklineType, trendType === 'success' ? '#22c55e' : 'var(--sppux-primary, #6366f1)', 100, 36) : Fragment}
                    </div>
                </div>
            `; 
        } 
    };
    SPPUX.Button = { render(l, o = {}) { const { variant = 'primary', size = 'md', icon = null, loading = false, onClick = null } = o; const btnClass = `sppux-btn sppux-btn-${variant} sppux-btn-${size} ${loading ? 'loading' : ''}`; return html`<button class="${btnClass}" ?disabled="${loading}" @click=${onClick}>${loading ? html`<span class="sppux-spinner"></span>` : icon ? html`<span>${icon}</span>` : Fragment}<span>${l}</span></button>`; } };
    SPPUX.Tooltip = { init() { document.addEventListener('mouseover', (e) => { const target = e.target.closest('[data-spp-tooltip]'); if (target && !target.dataset.tooltipActive) { target.dataset.tooltipActive = "true"; const tip = document.createElement('div'); tip.className = 'sppux-tooltip'; tip.textContent = target.dataset.sppTooltip; document.body.appendChild(tip); const rect = target.getBoundingClientRect(); tip.style.left = rect.left + (rect.width / 2) - (tip.offsetWidth / 2) + 'px'; tip.style.top = rect.top - tip.offsetHeight - 8 + 'px'; target.addEventListener('mouseleave', () => { tip.remove(); delete target.dataset.tooltipActive; }, { once: true }); } }); } };
    SPPUX.Avatar = { render(n, s = null, z = 'md') { const initials = n ? n.split(' ').map(x => x[0]).join('').substring(0, 2).toUpperCase() : '?'; return html`<div class="sppux-avatar sppux-avatar-${z}">${s ? html`<img src="${s}">` : html`<span>${initials}</span>`}</div>`; } };

    /**
     * Tabs Component (Reactive Class & Static Function)
     */
    SPPUX.Tabs = class extends BaseComponent {
        onInit() {
            this.state = {
                activeTab: this.props.active || (this.props.tabs && this.props.tabs[0]?.id) || 'tab_0'
            };
        }
        render() {
            const tabs = this.props.tabs || [];
            const activeId = this.state.activeTab;
            const currentTab = tabs.find(t => t.id === activeId) || tabs[0];
            return html`
                <div class="sppux-tabs-container" style="display: flex; flex-direction: column; width: 100%;">
                    <div class="sppux-tabs-header" style="display: flex; gap: 4px; border-bottom: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.1)); padding-bottom: 6px; margin-bottom: 12px; overflow-x: auto;">
                        ${tabs.map(t => html`
                            <button type="button" class="sppux-tab-btn ${t.id === activeId ? 'active' : ''}" 
                                style="padding: 6px 14px; border-radius: 6px; border: none; background: ${t.id === activeId ? 'var(--sppux-primary, #6366f1)' : 'transparent'}; color: ${t.id === activeId ? '#fff' : 'var(--sppux-text-dim, #94a3b8)'}; font-weight: 500; font-size: 0.85rem; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px;"
                                @click=${() => {
                                    this.setState({ activeTab: t.id });
                                    if (typeof this.props.onChange === 'function') this.props.onChange(t.id);
                                    if (typeof t.onClick === 'function') t.onClick(t);
                                }}>
                                ${t.icon ? html`<span>${t.icon}</span>` : Fragment}
                                <span>${t.label}</span>
                                ${t.badge !== undefined ? html`<span class="badge" style="background: rgba(255,255,255,0.2); font-size: 0.7rem; padding: 1px 6px; border-radius: 10px;">${t.badge}</span>` : Fragment}
                            </button>
                        `)}
                    </div>
                    <div class="sppux-tabs-content" style="flex: 1;">
                        ${currentTab?.content || Fragment}
                    </div>
                </div>
            `;
        }
    };
    SPPUX.Tabs.render = (tabs, activeId, onSelect) => {
        return html`
            <div class="sppux-tabs-container">
                <div class="sppux-tabs-header">
                    ${tabs.map(t => html`
                        <button class="${t.id === activeId ? 'active' : ''}" @click=${() => {
                            if (onSelect) onSelect(t.id);
                            if (t.onClick) t.onClick();
                        }}>
                            ${t.icon ? html`<span>${t.icon}</span> ` : Fragment}${t.label}
                        </button>
                    `)}
                </div>
                <div class="sppux-tabs-content">${tabs.find(t => t.id === activeId)?.content || Fragment}</div>
            </div>
        `;
    };
    SPPUX.Drawer = { open(t, c, s = 'right') { 
        if (typeof c === 'string' && c.includes('<')) c = new SPPUX.TrustedHTML(c);
        let container = document.getElementById('sppux-drawer-root') || (document.body.appendChild(Object.assign(document.createElement('div'), {id:'sppux-drawer-root'}))); 
        const drawer = new (class extends BaseComponent { 
            onMount() {
                this._escListener = (e) => { if (e.key === 'Escape') this.close(); };
                document.addEventListener('keydown', this._escListener);
                // Force a reflow before adding active class to ensure CSS transitions trigger
                setTimeout(() => {
                    const overlay = this.container.querySelector('.sppux-drawer-overlay');
                    if (overlay) overlay.classList.add('active');
                }, 10);
            }
            render() { return html`<div class="sppux-drawer-overlay" @click=${(e) => { if(e.target.classList.contains('sppux-drawer-overlay')) this.close(); }}><div class="sppux-drawer sppux-drawer-${this.props.side}"><div class="modal-header"><h3>${this.props.title}</h3><button class="btn ghost" style="padding: 4px 10px; min-width: auto; font-size: 1.2rem; margin: -10px -10px -10px 0;" @click=${this.close}>✕</button></div><div class="modal-body" style="flex:1;">${this.props.content}</div></div></div>`; } 
            close() { 
                if (this._escListener) document.removeEventListener('keydown', this._escListener);
                const overlay = this.container.querySelector('.sppux-drawer-overlay');
                if (overlay) overlay.classList.remove('active'); 
                setTimeout(() => {
                    this.container.innerHTML = '';
                    this.dispose();
                }, 300); 
            } 
        })(null, container, { title: t, content: c, side: s }); 
        drawer.update(); 
        if(typeof drawer.onMount === 'function') drawer.onMount();
        return drawer; 
    } };

    // SPPUX V2 COMPONENTS
    
    SPPUX.Switch = {
        render(checked, onChange) {
            return html`
                <label class="sppux-switch">
                    <input type="checkbox" ?checked="${checked}" @change=${(e) => onChange(e.target.checked)}>
                    <span class="sppux-switch-slider"></span>
                </label>
            `;
        }
    };

    SPPUX.Chips = {
        render(items, onAdd, onRemove) {
            return html`
                <div class="sppux-chip-container" @click=${(e) => {
                    const container = e.target.closest('.sppux-chip-container');
                    if (container) {
                        const input = container.querySelector('.sppux-chip-input');
                        if (input) input.focus();
                    }
                }}>
                    ${items.map((item, idx) => html`
                        <span class="sppux-chip">
                            ${item} <span class="sppux-chip-close" @click=${(e) => { e.stopPropagation(); onRemove(idx); }}>✕</span>
                        </span>
                    `)}
                    <input type="text" class="sppux-chip-input" placeholder="Add tag..." @keyup=${(e) => {
                        if (e.key === 'Enter' && e.target.value.trim()) {
                            const parentNode = e.target.closest('.sppux-chip-container').parentNode;
                            onAdd(e.target.value.trim());
                            e.target.value = '';
                            setTimeout(() => {
                                if (parentNode) {
                                    const input = parentNode.querySelector('.sppux-chip-input');
                                    if (input) input.focus();
                                }
                            }, 50);
                        }
                    }}>
                </div>
            `;
        }
    };

    SPPUX.Skeleton = {
        render() {
            return html`<div class="sppux-skeleton"></div>`;
        }
    };

    SPPUX.Accordion = {
        render(items, activeIndex, onToggle) {
            return html`
                <div class="sppux-accordion">
                    ${items.map((item, idx) => html`
                        <div class="sppux-accordion-item ${idx === activeIndex ? 'active' : ''}">
                            <div class="sppux-accordion-header" @click=${() => onToggle(idx)}>
                                <span>${item.title}</span>
                                <span>${idx === activeIndex ? '▲' : '▼'}</span>
                            </div>
                            <div class="sppux-accordion-content">
                                <div style="padding: 16px;">${item.content}</div>
                            </div>
                        </div>
                    `)}
                </div>
            `;
        }
    };

    SPPUX.Dropzone = {
        render(onDrop, text = "Drag & Drop files here or click to browse", files = null, onRemove = null) {
            const handleDragOver = (e) => {
                e.preventDefault();
                const zone = e.target.closest('.sppux-dropzone');
                if (zone) zone.classList.add('drag-over');
            };
            const handleDragLeave = (e) => {
                const zone = e.target.closest('.sppux-dropzone');
                if (zone) zone.classList.remove('drag-over');
            };
            const handleDrop = (e) => {
                e.preventDefault();
                const zone = e.target.closest('.sppux-dropzone');
                if (zone) zone.classList.remove('drag-over');
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    onDrop(Array.from(e.dataTransfer.files));
                }
            };
            const handleClick = (e) => {
                const input = document.createElement('input');
                input.type = 'file';
                input.multiple = true;
                input.addEventListener('change', () => {
                    if (input.files && input.files.length > 0) onDrop(Array.from(input.files));
                });
                input.click();
            };

            const renderFiles = () => {
                if (!files || files.length === 0) return '';
                return html`
                    <div style="margin-top: 15px; display: flex; flex-direction: column; gap: 8px; text-align: left; width: 100%; max-width: 300px; margin-left: auto; margin-right: auto;">
                        ${files.map((f, index) => html`
                            <div style="display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.05); padding: 8px 12px; border-radius: 8px; font-size: 0.9rem;">
                                <span>📄</span>
                                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1;" title="${f.name}">${f.name}</span>
                                <span style="opacity: 0.6; font-size: 0.8rem;">${(f.size / 1024).toFixed(1)} KB</span>
                                ${onRemove ? html`
                                    <button style="background:transparent; border:none; color:#ef4444; font-size:1.2rem; line-height:1; padding:0 4px; cursor:pointer; border-radius:4px; opacity:0.8;" 
                                        @mouseenter=${(e) => { e.target.style.opacity='1'; e.target.style.background='rgba(239,68,68,0.1)'; }} 
                                        @mouseleave=${(e) => { e.target.style.opacity='0.8'; e.target.style.background='transparent'; }}
                                        title="Remove File" 
                                        @click=${(e) => { e.stopPropagation(); onRemove(index); }}>&times;</button>
                                ` : ''}
                            </div>
                        `)}
                    </div>
                `;
            };

            return html`
                <div class="sppux-dropzone" 
                    @dragover=${handleDragOver} 
                    @dragleave=${handleDragLeave} 
                    @drop=${handleDrop}
                    @click=${handleClick}>
                    <div>☁️</div>
                    <div style="margin-top: 10px;">${text}</div>
                    ${renderFiles()}
                </div>
            `;
        }
    };

    SPPUX.ContextMenu = {
        open(e, items) {
            e.preventDefault();
            const existing = document.getElementById('sppux-context-menu-root');
            if (existing) existing.remove();
            
            const menu = document.createElement('div');
            menu.id = 'sppux-context-menu-root';
            menu.className = 'sppux-context-menu';
            menu.style.left = e.clientX + 'px';
            menu.style.top = e.clientY + 'px';
            
            items.forEach(item => {
                const el = document.createElement('div');
                el.className = 'sppux-context-item';
                const iconSpan = document.createElement('span');
                iconSpan.textContent = item.icon || '';
                const labelSpan = document.createElement('span');
                labelSpan.textContent = item.label;
                el.appendChild(iconSpan);
                el.appendChild(document.createTextNode(' '));
                el.appendChild(labelSpan);
                el.addEventListener('click', () => {
                    if (item.action) item.action();
                    menu.remove();
                });
                menu.appendChild(el);
            });
            
            document.body.appendChild(menu);
            
            const close = (evt) => {
                if (evt.type === 'keydown' && evt.key === 'Escape') { menu.remove(); cleanup(); }
                else if (evt.type === 'mousedown' && !menu.contains(evt.target)) { menu.remove(); cleanup(); }
            };
            const cleanup = () => { document.removeEventListener('mousedown', close); document.removeEventListener('keydown', close); };
            document.addEventListener('mousedown', close);
            document.addEventListener('keydown', close);
            return menu;
        }
    };

    SPPUX.Alert = {
        render(message, type = 'info', onClose = null) {
            const variant = (typeof type === 'object' && type !== null) ? (type.variant || 'info') : type;
            const title = (typeof type === 'object' && type !== null) ? type.title : null;
            const closeFn = (typeof type === 'object' && type !== null) ? type.onClose : onClose;
            return html`
                <div class="sppux-alert sppux-alert-${variant}">
                    <span style="font-size: 1.2rem; line-height: 1;">
                        ${variant === 'success' ? '✓' : variant === 'warning' ? '⚠️' : variant === 'error' || variant === 'danger' ? '✕' : 'ℹ️'}
                    </span>
                    <div style="flex: 1;">
                        ${title ? html`<strong style="display: block; margin-bottom: 2px;">${title}</strong>` : Fragment}
                        <div>${message}</div>
                    </div>
                    ${closeFn ? html`<span class="sppux-alert-close" @click=${closeFn}>✕</span>` : Fragment}
                </div>
            `;
        }
    };

    SPPUX.Progress = {
        render(value = 'indeterminate') {
            const isIndeterminate = value === 'indeterminate';
            return html`
                <div class="sppux-progress ${isIndeterminate ? 'indeterminate' : ''}">
                    <div class="sppux-progress-bar" style="${!isIndeterminate ? `width: ${value}%` : ''}"></div>
                </div>
            `;
        }
    };

    SPPUX.Combobox = {
        render(options, value, onChange, placeholder = "Search...") {
            return html`
                <div class="sppux-combobox" style="position: relative;">
                    <input type="text" class="spp-input" placeholder="${placeholder}" value="${options.find(o => o.value === value)?.label || ''}" 
                        @focus=${(e) => e.target.nextElementSibling.style.display = 'block'}
                        @blur=${(e) => setTimeout(() => e.target.nextElementSibling.style.display = 'none', 200)}
                        @input=${(e) => {
                            const q = e.target.value.toLowerCase();
                            Array.from(e.target.nextElementSibling.children).forEach(child => {
                                child.style.display = child.textContent.toLowerCase().includes(q) ? 'block' : 'none';
                            });
                        }}>
                    <div class="sppux-combobox-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: var(--sppux-panel); border: 1px solid var(--sppux-glass-border); border-radius: var(--sppux-radius-sm); max-height: 200px; overflow-y: auto; z-index: 100;">
                        ${options.map(opt => html`
                            <div style="padding: 10px; cursor: pointer;" 
                                @mousedown=${(e) => {
                                    const input = e.target.closest('.sppux-combobox').querySelector('input');
                                    if (input) input.value = opt.label;
                                }}
                                @click=${() => {
                                    onChange(opt.value);
                                }}
                                @mouseenter=${(e) => e.target.style.background='var(--sppux-primary-subtle)'}
                                @mouseleave=${(e) => e.target.style.background='transparent'}>
                                ${opt.label}
                            </div>
                        `)}
                    </div>
                </div>
            `;
        }
    };

    SPPUX.DataGrid = {
        render(columns, data) {
            return html`
                <div style="overflow-x: auto; border: 1px solid var(--sppux-glass-border); border-radius: var(--sppux-radius-md);">
                    <table class="sppux-table">
                        <thead>
                            <tr>${columns.map(col => html`<th>${col.label}</th>`)}</tr>
                        </thead>
                        <tbody>
                            ${data.map(row => html`
                                <tr>${columns.map(col => html`<td>${row[col.key]}</td>`)}</tr>
                            `)}
                        </tbody>
                    </table>
                </div>
            `;
        }
    };

    SPPUX.TreeView = {
        render(nodes) {
            const renderNode = (node) => html`
                <div style="margin-left: 20px;">
                    <div class="sppux-tree-row" style="cursor: pointer; padding: 4px 0;" @click=${(e) => {
                        const row = e.target.closest('.sppux-tree-row');
                        if (!row) return;
                        const childContainer = row.nextElementSibling;
                        if (childContainer) {
                            const isHidden = childContainer.style.display === 'none';
                            childContainer.style.display = isHidden ? 'block' : 'none';
                            row.querySelector('.sppux-tree-icon').textContent = isHidden ? '▼' : '▶';
                        }
                    }}>
                        <span class="sppux-tree-icon" style="display: inline-block; width: 15px; font-size: 0.8rem;">
                            ${node.children && node.children.length ? '▼' : '•'}
                        </span>
                        ${node.label}
                    </div>
                    ${node.children && node.children.length ? html`
                        <div class="sppux-tree-children">
                            ${node.children.map(child => renderNode(child))}
                        </div>
                    ` : Fragment}
                </div>
            `;
            return html`<div class="sppux-tree">${nodes.map(n => renderNode(n))}</div>`;
        }
    };

    SPPUX.SplitPane = {
        render(leftContent, rightContent) {
            return html`
                <div style="display: flex; width: 100%; height: 100%;">
                    <div class="sppux-split-left" style="flex: 1; overflow: auto;">${leftContent}</div>
                    <div class="sppux-split-resizer" style="width: 5px; cursor: col-resize; background: var(--sppux-glass-border); transition: 0.2s;"
                        @mouseenter=${(e) => e.target.style.background='var(--sppux-primary)'}
                        @mouseleave=${(e) => e.target.style.background='var(--sppux-glass-border)'}
                        @pointerdown=${(e) => {
                            e.preventDefault();
                            const resizer = e.target;
                            const leftPane = resizer.previousElementSibling;
                            const startX = e.clientX;
                            const startWidth = leftPane.getBoundingClientRect().width;
                            const onMove = (evt) => {
                                const newWidth = startWidth + (evt.clientX - startX);
                                leftPane.style.flex = '0 0 ' + newWidth + 'px';
                            };
                            const onUp = () => {
                                document.removeEventListener('pointermove', onMove);
                                document.removeEventListener('pointerup', onUp);
                            };
                            document.addEventListener('pointermove', onMove);
                            document.addEventListener('pointerup', onUp);
                            resizer.setPointerCapture(e.pointerId);
                        }}></div>
                    <div class="sppux-split-right" style="flex: 1; overflow: auto;">${rightContent}</div>
                </div>
            `;
        }
    };

    // SPPUX V3 COMPONENTS

    SPPUX.Chart = {
        renderSparkline(data, type = 'line', color = 'var(--sppux-primary)', width = 200, height = 60) {
            if (!data || !data.length) return Fragment;
            const min = Math.min(...data), max = Math.max(...data);
            const range = max - min || 1;
            const stepX = width / (data.length - 1 || 1);
            
            if (type === 'bar') {
                const barWidth = Math.max(2, (width / data.length) - 2);
                return html`
                    <svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
                        ${data.map((val, i) => {
                            const h = ((val - min) / range) * height;
                            return html`<rect x="${i * (width / data.length)}" y="${height - h}" width="${barWidth}" height="${h}" fill="${color}" rx="2"/>`;
                        })}
                    </svg>
                `;
            }
            
            // Line Chart
            const points = data.map((val, i) => {
                const x = i * stepX;
                const y = height - (((val - min) / range) * (height - 4)) - 2; // -2 for padding
                return `${x},${y}`;
            }).join(' ');
            return html`
                <svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
                    <polyline points="${points}" fill="none" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            `;
        }
    };

    SPPUX.Pagination = {
        render(currentPage, totalPages, onPageChange) {
            const pages = [];
            for(let i=1; i<=totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    pages.push(i);
                } else if (pages[pages.length-1] !== '...') {
                    pages.push('...');
                }
            }
            return html`
                <div class="sppux-pagination" style="display:flex;gap:4px;align-items:center;">
                    <button class="btn ghost" ?disabled=${currentPage===1} @click=${()=>onPageChange(currentPage-1)}>«</button>
                    ${pages.map(p => p === '...' ? html`<span style="padding:4px 8px;color:var(--sppux-text-dim);">...</span>` : html`
                        <button class="btn ${p===currentPage ? 'primary' : 'ghost'}" @click=${()=>onPageChange(p)}>${p}</button>
                    `)}
                    <button class="btn ghost" ?disabled=${currentPage===totalPages} @click=${()=>onPageChange(currentPage+1)}>»</button>
                </div>
            `;
        }
    };

    SPPUX.ColorPicker = {
        render(value, onChange) {
            return html`
                <div class="sppux-color-picker" style="display:inline-flex;align-items:center;gap:8px;background:var(--sppux-input-bg);border:1px solid var(--sppux-glass-border);border-radius:var(--sppux-radius-md);padding:4px 8px;">
                    <input type="color" value="${value}" @input=${(e)=>onChange(e.target.value)} style="width:24px;height:24px;border:none;border-radius:4px;cursor:pointer;padding:0;background:none;">
                    <span style="font-family:monospace;font-size:0.9rem;text-transform:uppercase;">${value}</span>
                </div>
            `;
        }
    };

    SPPUX.Rating = {
        render(value, max = 5, onChange = null) {
            const stars = Array.from({length: max});
            return html`
                <div class="sppux-rating" style="display:inline-flex;gap:4px;color:var(--sppux-warning);font-size:1.5rem;cursor:${onChange ? 'pointer' : 'default'}">
                    ${stars.map((_, i) => html`
                        <span @click=${() => onChange && onChange(i+1)} style="opacity:${i < value ? 1 : 0.3};transition:0.2s;">
                            ${i < value ? '★' : '☆'}
                        </span>
                    `)}
                </div>
            `;
        }
    };

    SPPUX.FAB = {
        render(icon = '+', options = []) {
            return html`
                <div class="sppux-fab-container" style="position:fixed;bottom:30px;right:30px;z-index:9000;" 
                     onmouseenter="this.querySelector('.sppux-fab-menu').style.display='flex';this.querySelector('.sppux-fab-menu').style.opacity='1'"
                     onmouseleave="this.querySelector('.sppux-fab-menu').style.opacity='0';setTimeout(()=>this.querySelector('.sppux-fab-menu').style.display='none',200)">
                    <div class="sppux-fab-menu" style="display:none;opacity:0;flex-direction:column-reverse;gap:10px;margin-bottom:15px;align-items:center;transition:opacity 0.2s;">
                        ${options.map(opt => html`
                            <button class="btn-icon" style="border-radius:50%;box-shadow:0 4px 10px rgba(0,0,0,0.3);" @click=${opt.action} title="${opt.label}">${opt.icon}</button>
                        `)}
                    </div>
                    <button class="btn primary" style="width:56px;height:56px;border-radius:50%;font-size:1.5rem;box-shadow:0 6px 15px var(--sppux-primary-glow);padding:0;display:flex;align-items:center;justify-content:center;">${icon}</button>
                </div>
            `;
        }
    };

    // SPPUX V4: The Pinnacle Components

    SPPUX.Cursor = {
        init() {
            if (window.matchMedia("(pointer: coarse)").matches) return; // Ignore on touch devices
            const cursor = document.createElement('div');
            cursor.className = 'sppux-cursor';
            cursor.style.cssText = 'position:fixed;top:0;left:0;width:20px;height:20px;border:2px solid var(--sppux-primary);border-radius:50%;pointer-events:none;z-index:999999;transition:transform 0.1s ease, width 0.2s, height 0.2s, background 0.2s;transform:translate(-50%, -50%);';
            document.body.appendChild(cursor);

            document.addEventListener('mousemove', (e) => {
                cursor.style.left = e.clientX + 'px';
                cursor.style.top = e.clientY + 'px';
            });

            document.addEventListener('mouseover', (e) => {
                if (e.target.closest('a, button, input, [tabindex], .sppux-card, [data-spp-magnetic], .sppux-kanban-card')) {
                    cursor.style.width = '40px';
                    cursor.style.height = '40px';
                    cursor.style.background = 'var(--sppux-primary-subtle)';
                } else {
                    cursor.style.width = '20px';
                    cursor.style.height = '20px';
                    cursor.style.background = 'transparent';
                }
            });
        }
    };

    SPPUX.Kanban = {
        render(columns, onDragEnd) {
            return html`
                <div class="sppux-kanban" style="display:flex;gap:16px;overflow-x:auto;padding:10px;align-items:flex-start;">
                    ${columns.map(col => html`
                        <div class="sppux-kanban-col" data-col-id="${col.id}" style="flex:0 0 300px;background:var(--sppux-panel);border:1px solid var(--sppux-glass-border);border-radius:var(--sppux-radius-md);display:flex;flex-direction:column;max-height:80vh;">
                            <div style="padding:16px;font-weight:600;border-bottom:1px solid var(--sppux-glass-border);display:flex;justify-content:space-between;">
                                <span>${col.title}</span>
                                <span class="badge" style="background:var(--sppux-glass-bg);">${col.cards.length}</span>
                            </div>
                            <div class="sppux-kanban-dropzone" style="flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;min-height:100px;"
                                @dragover=${(e) => { 
                                    e.preventDefault(); 
                                    const dz = e.target.closest('.sppux-kanban-dropzone');
                                    if (dz) dz.style.background = 'var(--sppux-glass-bg)'; 
                                }}
                                @dragleave=${(e) => { 
                                    const dz = e.target.closest('.sppux-kanban-dropzone');
                                    if (dz) dz.style.background = 'transparent'; 
                                }}
                                @drop=${(e) => {
                                    e.preventDefault();
                                    const dz = e.target.closest('.sppux-kanban-dropzone');
                                    if (dz) dz.style.background = 'transparent';
                                    const cardId = e.dataTransfer.getData('text/plain');
                                    onDragEnd(cardId, col.id);
                                }}>
                                ${col.cards.map(card => html`
                                    <div class="sppux-kanban-card" draggable="true" data-card-id="${card.id}" style="background:var(--sppux-card-bg);padding:12px;border:1px solid var(--sppux-glass-border);border-radius:var(--sppux-radius-sm);cursor:grab;box-shadow:0 2px 4px rgba(0,0,0,0.1);"
                                        @dragstart=${(e) => {
                                            e.dataTransfer.setData('text/plain', card.id);
                                            const c = e.target.closest('.sppux-kanban-card');
                                            if (c) c.style.opacity = '0.5';
                                        }}
                                        @dragend=${(e) => { 
                                            const c = e.target.closest('.sppux-kanban-card');
                                            if (c) c.style.opacity = '1'; 
                                        }}>
                                        ${card.content}
                                    </div>
                                `)}
                            </div>
                        </div>
                    `)}
                </div>
            `;
        }
    };

    SPPUX.VirtualList = {
        render(items, itemHeight, visibleCount, renderItem) {
            return html`
                <div class="sppux-virtual-list" style="height:${visibleCount * itemHeight}px;overflow-y:auto;position:relative;"
                     @scroll=${(e) => {
                         if (e.target.onScrollCallback) e.target.onScrollCallback(e.target.scrollTop);
                     }}>
                    <div style="height:${items.length * itemHeight}px;position:relative;">
                        <div style="position:absolute;top:0;left:0;right:0;" class="sppux-virtual-content">
                            <!-- Virtual Items Container -->
                        </div>
                    </div>
                </div>
            `;
        }
    };

    SPPUX.MediaPlayer = {
        render(src, type = 'video', poster = '') {
            return html`
                <div class="sppux-media-player" style="position:relative;background:#000;border-radius:var(--sppux-radius-md);overflow:hidden;width:100%;max-width:800px;border:1px solid var(--sppux-glass-border);">
                    ${type === 'video' 
                        ? html`<video src="${src}" poster="${poster}" style="width:100%;display:block;" controls></video>`
                        : html`<audio src="${src}" style="width:100%;" controls></audio>`
                    }
                </div>
            `;
        }
    };

    SPPUX.Lightbox = {
        open(src, alt = '') {
            const overlay = document.createElement('div');
            overlay.className = 'glass-overlay active';
            overlay.style.cssText = 'position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:100000;background:rgba(0,0,0,0.9);display:flex;align-items:center;justify-content:center;cursor:zoom-out;backdrop-filter:blur(10px);';
            const img = document.createElement('img');
            img.src = src;
            img.alt = alt;
            img.style.cssText = 'max-width:90vw;max-height:90vh;border-radius:var(--sppux-radius-md);box-shadow:0 10px 40px rgba(0,0,0,0.5);transform:scale(0.9);animation:sppux-zoom-in 0.3s forwards cubic-bezier(0.34, 1.56, 0.64, 1);';
            overlay.appendChild(img);
            
            const close = () => {
                overlay.style.opacity = '0';
                setTimeout(() => overlay.remove(), 300);
            };
            overlay.addEventListener('click', close);
            document.body.appendChild(overlay);
            
            const escClose = (e) => { if (e.key === 'Escape') close(); document.removeEventListener('keydown', escClose); };
            document.addEventListener('keydown', escClose);
        }
    };

    SPPUX.ChatWindow = {
        render(messages, onSend, isTyping = false) {
            return html`
                <div class="sppux-chat" style="display:flex;flex-direction:column;height:500px;max-height:80vh;border:1px solid var(--sppux-glass-border);border-radius:var(--sppux-radius-lg);background:var(--sppux-panel);overflow:hidden;">
                    <div class="sppux-chat-header" style="padding:16px;background:var(--sppux-glass-bg);border-bottom:1px solid var(--sppux-glass-border);font-weight:600;display:flex;align-items:center;gap:10px;">
                        <span style="font-size:1.5rem;">✨</span> AI Assistant
                    </div>
                    <div class="sppux-chat-messages" style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:16px;">
                        ${messages.map(msg => html`
                            <div style="display:flex;justify-content:${msg.role === 'user' ? 'flex-end' : 'flex-start'};">
                                <div style="max-width:80%;padding:12px 16px;border-radius:var(--sppux-radius-md);${msg.role === 'user' ? 'background:var(--sppux-primary);color:white;' : 'background:var(--sppux-card-bg);border:1px solid var(--sppux-glass-border);'}">
                                    ${msg.content}
                                </div>
                            </div>
                        `)}
                        ${isTyping ? html`
                            <div style="display:flex;justify-content:flex-start;">
                                <div style="padding:12px 16px;border-radius:var(--sppux-radius-md);background:var(--sppux-card-bg);border:1px solid var(--sppux-glass-border);display:flex;gap:4px;align-items:center;">
                                    <span class="sppux-typing-dot" style="width:6px;height:6px;background:var(--sppux-text-dim);border-radius:50%;animation:sppux-typing 1.4s infinite ease-in-out both;"></span>
                                    <span class="sppux-typing-dot" style="width:6px;height:6px;background:var(--sppux-text-dim);border-radius:50%;animation:sppux-typing 1.4s infinite ease-in-out both;animation-delay:0.2s;"></span>
                                    <span class="sppux-typing-dot" style="width:6px;height:6px;background:var(--sppux-text-dim);border-radius:50%;animation:sppux-typing 1.4s infinite ease-in-out both;animation-delay:0.4s;"></span>
                                </div>
                            </div>
                        ` : Fragment}
                    </div>
                    <div class="sppux-chat-input" style="padding:16px;border-top:1px solid var(--sppux-glass-border);background:var(--sppux-glass-bg);display:flex;gap:10px;">
                        <input type="text" class="spp-input" placeholder="Type a message..." style="flex:1;" 
                            @keydown=${(e) => {
                                if (e.key === 'Enter' && e.target.value.trim()) {
                                    onSend(e.target.value.trim());
                                    e.target.value = '';
                                }
                            }}>
                        <button class="btn primary" @click=${(e) => {
                            const input = e.currentTarget.previousElementSibling;
                            if (input.value.trim()) {
                                onSend(input.value.trim());
                                input.value = '';
                            }
                        }}>Send</button>
                    </div>
                </div>
            `;
        }
    };

    // SPPUX V5: Data Viz & Web APIs
    
    SPPUX.RadarChart = {
        render(labels, data, color = 'var(--sppux-primary)', width = 300, height = 300) {
            const centerX = width / 2;
            const centerY = height / 2;
            const radius = Math.min(centerX, centerY) - 30;
            const angleStep = (Math.PI * 2) / labels.length;
            
            // Draw background grid
            const levels = 5;
            let gridHtml = '';
            for (let i = 1; i <= levels; i++) {
                const r = radius * (i / levels);
                let points = '';
                for (let j = 0; j < labels.length; j++) {
                    const x = centerX + r * Math.sin(j * angleStep);
                    const y = centerY - r * Math.cos(j * angleStep);
                    points += `${x},${y} `;
                }
                gridHtml += `<polygon points="${points}" fill="none" stroke="var(--sppux-glass-border)" stroke-width="1"/>`;
            }
            
            // Draw axes and labels
            let axesHtml = '';
            for (let j = 0; j < labels.length; j++) {
                const x = centerX + radius * Math.sin(j * angleStep);
                const y = centerY - radius * Math.cos(j * angleStep);
                axesHtml += `<line x1="${centerX}" y1="${centerY}" x2="${x}" y2="${y}" stroke="var(--sppux-glass-border)" stroke-width="1"/>`;
                const lx = centerX + (radius + 15) * Math.sin(j * angleStep);
                const ly = centerY - (radius + 15) * Math.cos(j * angleStep);
                axesHtml += `<text x="${lx}" y="${ly}" fill="var(--sppux-text-dim)" font-size="12" text-anchor="middle" dominant-baseline="middle">${labels[j]}</text>`;
            }
            
            // Draw data
            let dataPoints = '';
            for (let j = 0; j < data.length; j++) {
                const val = Math.max(0, Math.min(1, data[j])); // normalize 0-1
                const x = centerX + (radius * val) * Math.sin(j * angleStep);
                const y = centerY - (radius * val) * Math.cos(j * angleStep);
                dataPoints += `${x},${y} `;
            }
            const dataPolygon = `<polygon points="${dataPoints}" fill="${color}" fill-opacity="0.3" stroke="${color}" stroke-width="2"/>`;
            
            return html`
                <svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
                    ${new SPPUX.TrustedHTML(gridHtml)}
                    ${new SPPUX.TrustedHTML(axesHtml)}
                    ${new SPPUX.TrustedHTML(dataPolygon)}
                </svg>
            `;
        }
    };

    SPPUX.DonutChart = {
        render(percentage, label, color = 'var(--sppux-primary)', size = 150, strokeWidth = 15) {
            const radius = (size - strokeWidth) / 2;
            const circumference = 2 * Math.PI * radius;
            const offset = circumference - (percentage / 100) * circumference;
            return html`
                <div style="position:relative;width:${size}px;height:${size}px;">
                    <svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" style="transform:rotate(-90deg);">
                        <circle cx="${size/2}" cy="${size/2}" r="${radius}" fill="none" stroke="var(--sppux-glass-border)" stroke-width="${strokeWidth}"/>
                        <circle cx="${size/2}" cy="${size/2}" r="${radius}" fill="none" stroke="${color}" stroke-width="${strokeWidth}" stroke-linecap="round" stroke-dasharray="${circumference}" stroke-dashoffset="${offset}" style="transition:stroke-dashoffset 1s ease-out;"/>
                    </svg>
                    <div style="position:absolute;top:0;left:0;width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;line-height:1.2;">
                        <span style="font-size:1.5rem;font-weight:bold;">${percentage}%</span>
                        <span style="font-size:0.8rem;color:var(--sppux-text-dim);">${label}</span>
                    </div>
                </div>
            `;
        }
    };

    SPPUX.Heatmap = {
        render(data, cellSize = 12, cellGap = 3, colorMap = ['var(--sppux-glass-border)', 'rgba(16,185,129,0.3)', 'rgba(16,185,129,0.6)', 'rgba(16,185,129,1)']) {
            if (!data || !data.length) return Fragment;
            const width = data.length * (cellSize + cellGap);
            const height = 7 * (cellSize + cellGap);
            return html`
                <div style="overflow-x:auto;">
                    <svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
                        ${data.map((week, i) => html`
                            <g transform="translate(${i * (cellSize + cellGap)}, 0)">
                                ${week.map((val, j) => {
                                    const cIdx = Math.min(val, colorMap.length - 1);
                                    return html`<rect x="0" y="${j * (cellSize + cellGap)}" width="${cellSize}" height="${cellSize}" fill="${colorMap[cIdx]}" rx="2"/>`;
                                })}
                            </g>
                        `)}
                    </svg>
                </div>
            `;
        }
    };

    SPPUX.RichText = {
        render(content, onChange) {
            return html`
                <div class="sppux-richtext" style="border:1px solid var(--sppux-glass-border);border-radius:var(--sppux-radius-md);overflow:hidden;background:var(--sppux-input-bg);">
                    <div class="sppux-richtext-toolbar" style="padding:8px;background:var(--sppux-glass-bg);border-bottom:1px solid var(--sppux-glass-border);display:flex;gap:4px;">
                        <button class="btn-icon" @click=${(e)=>{e.preventDefault();document.execCommand('bold', false, null);}}><b>B</b></button>
                        <button class="btn-icon" @click=${(e)=>{e.preventDefault();document.execCommand('italic', false, null);}}><i>I</i></button>
                        <button class="btn-icon" @click=${(e)=>{e.preventDefault();document.execCommand('underline', false, null);}}><u>U</u></button>
                        <span style="width:1px;background:var(--sppux-glass-border);margin:0 4px;"></span>
                        <button class="btn-icon" @click=${(e)=>{e.preventDefault();document.execCommand('insertUnorderedList', false, null);}}>•</button>
                        <button class="btn-icon" @click=${(e)=>{e.preventDefault();const url = prompt('Enter link URL:'); if(url) document.execCommand('createLink', false, url);}}>🔗</button>
                    </div>
                    <div class="sppux-richtext-content" contenteditable="true" style="padding:12px;min-height:150px;outline:none;" @input=${(e)=>onChange(e.target.innerHTML)}>
                        ${new SPPUX.TrustedHTML(content)}
                    </div>
                </div>
            `;
        }
    };

    SPPUX.System = {
        init() {
            window.addEventListener('online', () => SPPUX.notify('You are back online', 'success'));
            window.addEventListener('offline', () => SPPUX.notify('You are offline. Some features may be unavailable.', 'warning'));

            if ('getBattery' in navigator) {
                navigator.getBattery().then(battery => {
                    const checkBattery = () => {
                        if (!battery.charging && battery.level <= 0.15) {
                            SPPUX.notify('Battery is low (' + Math.round(battery.level * 100) + '%). Please plug in your device.', 'danger');
                        }
                    };
                    battery.addEventListener('levelchange', checkBattery);
                    battery.addEventListener('chargingchange', checkBattery);
                });
            }
        }
    };

    // Legendary Inits
    SPPUX.Theme.init();
    SPPUX.Tooltip.init();
    // SPPUX.Cursor.init(); // Disabled to remove circle on mouse pointer
    SPPUX.System.init();
    window.admin_notify = SPPUX.Notify.show.bind(SPPUX.Notify);
    SPPUX.notify = SPPUX.Notify.show.bind(SPPUX.Notify);

    SPPUX.MasterGrid = class extends BaseComponent {
        onInit() {
            this.setState({
                columns: this.props.columns || [],
                data: this.props.data || [],
                rowHeight: this.props.rowHeight || 45,
                scrollTop: 0
            });
        }
        render() {
            const { columns, data, rowHeight, scrollTop } = this.state;
            const containerHeight = 450; // default visible height for showcase
            const totalHeight = data.length * rowHeight;
            const startIndex = Math.max(0, Math.floor(scrollTop / rowHeight));
            const endIndex = Math.min(data.length - 1, Math.floor((scrollTop + containerHeight) / rowHeight) + 2);
            
            const visibleData = [];
            for (let i = startIndex; i <= endIndex; i++) {
                if (data[i]) {
                    visibleData.push({ index: i, row: data[i] });
                }
            }

            return html`
                <div class="sppux-master-grid" style="display: flex; flex-direction: column; height: 100%; border: 1px solid var(--sppux-glass-border); border-radius: var(--sppux-radius-md); overflow: hidden; background: var(--sppux-panel);">
                    <div class="sppux-grid-header" style="display: flex; background: var(--sppux-glass-bg); border-bottom: 1px solid var(--sppux-glass-border); font-weight: 600;">
                        ${columns.map(col => html`
                            <div style="flex: ${col.width ? `0 0 ${col.width}` : '1'}; padding: 12px; border-right: 1px solid rgba(255,255,255,0.05); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; opacity: 0.8;">${col.label}</div>
                        `)}
                    </div>
                    <div class="sppux-grid-body" style="flex: 1; overflow-y: auto; position: relative;" @scroll=${(e) => this.setState({ scrollTop: e.target.scrollTop })}>
                        <div style="height: ${totalHeight}px; position: relative;">
                            ${visibleData.map(item => html`
                                <div class="sppux-grid-row" style="position: absolute; top: ${item.index * rowHeight}px; left: 0; right: 0; height: ${rowHeight}px; display: flex; border-bottom: 1px solid rgba(255,255,255,0.05); transition: background 0.1s;" @mouseenter=${(e) => e.target.style.background='var(--sppux-glass-bg)'} @mouseleave=${(e) => e.target.style.background='transparent'}>
                                    ${columns.map(col => html`
                                        <div style="flex: ${col.width ? `0 0 ${col.width}` : '1'}; padding: 0 12px; display: flex; align-items: center; border-right: 1px solid rgba(255,255,255,0.02); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.95rem;">
                                            ${item.row[col.key]}
                                        </div>
                                    `)}
                                </div>
                            `)}
                        </div>
                    </div>
                </div>
            `;
        }
    };

    /**
     * SPPUX.DataTable - Zero-Boilerplate Responsive Data Table
     * Supports search filtering, click-to-sort columns, pagination, row selection, and CSV export.
     */
    SPPUX.DataTable = class extends BaseComponent {
        onInit() {
            this.state = {
                filterText: '',
                sortKey: this.props.defaultSortKey || (this.props.columns?.[0]?.key || ''),
                sortOrder: this.props.defaultSortOrder || 'asc',
                page: 1,
                pageSize: this.props.pageSize || 10,
                selectedRows: new Set()
            };
        }

        exportCsv() {
            const columns = this.props.columns || [];
            const data = this.getProcessedData();
            let csv = columns.map(c => `"${(c.label || c.key).replace(/"/g, '""')}"`).join(',') + '\n';
            data.forEach(row => {
                csv += columns.map(c => `"${String(row[c.key] !== undefined ? row[c.key] : '').replace(/"/g, '""')}"`).join(',') + '\n';
            });
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = (this.props.title || 'export').toLowerCase().replace(/[^a-z0-9]/g, '_') + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            SPPUX.notify('CSV exported successfully', 'success');
        }

        getProcessedData() {
            let list = Array.isArray(this.props.data) ? [...this.props.data] : [];
            const { filterText, sortKey, sortOrder } = this.state;
            if (filterText) {
                const term = filterText.toLowerCase();
                list = list.filter(row => {
                    return Object.values(row).some(v => String(v).toLowerCase().includes(term));
                });
            }
            if (sortKey) {
                list.sort((a, b) => {
                    let va = a[sortKey], vb = b[sortKey];
                    if (va === vb) return 0;
                    if (va === null || va === undefined) return 1;
                    if (vb === null || vb === undefined) return -1;
                    let cmp = (typeof va === 'number' && typeof vb === 'number') ? va - vb : String(va).localeCompare(String(vb));
                    return sortOrder === 'asc' ? cmp : -cmp;
                });
            }
            return list;
        }

        render() {
            const columns = this.props.columns || [];
            const selectable = !!this.props.selectable;
            const searchable = this.props.searchable !== false;
            const exportable = this.props.exportable !== false;
            const { filterText, sortKey, sortOrder, page, pageSize, selectedRows } = this.state;

            const processed = this.getProcessedData();
            const totalPages = Math.max(1, Math.ceil(processed.length / pageSize));
            const currentPage = Math.min(page, totalPages);
            const startIndex = (currentPage - 1) * pageSize;
            const pageData = processed.slice(startIndex, startIndex + pageSize);

            const allSelected = pageData.length > 0 && pageData.every((_, i) => selectedRows.has(startIndex + i));

            return html`
                <div class="sppux-datatable-wrapper" style="border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.1)); border-radius: var(--sppux-radius-md, 10px); background: var(--sppux-panel, rgba(15,23,42,0.9)); overflow: hidden; display: flex; flex-direction: column;">
                    <!-- Toolbar -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.1)); background: rgba(0,0,0,0.15); flex-wrap: wrap; gap: 10px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            ${this.props.title ? html`<strong style="font-size: 1rem; color: var(--sppux-text, #f8fafc);">${this.props.title}</strong>` : Fragment}
                            <span class="badge" style="background: rgba(99,102,241,0.2); color: #818cf8; font-size: 0.75rem; padding: 2px 8px; border-radius: 12px;">${processed.length} rows</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            ${searchable ? html`
                                <input type="text" placeholder="Search table..." value="${filterText}" 
                                    style="padding: 6px 12px; border-radius: 6px; border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.15)); background: rgba(0,0,0,0.2); color: var(--sppux-text, #fff); font-size: 0.85rem; width: 180px;"
                                    @input=${(e) => this.setState({ filterText: e.target.value, page: 1 })}>
                            ` : Fragment}
                            ${exportable ? html`
                                <button type="button" class="btn secondary-btn btn-sm" style="font-size: 0.8rem; padding: 5px 10px;" @click=${() => this.exportCsv()}>
                                    📥 Export CSV
                                </button>
                            ` : Fragment}
                        </div>
                    </div>

                    <!-- Table -->
                    <div style="overflow-x: auto; flex: 1;">
                        <table class="sppux-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.1)); background: rgba(255,255,255,0.02);">
                                    ${selectable ? html`
                                        <th style="padding: 10px 12px; width: 40px; text-align: center;">
                                            <input type="checkbox" ?checked=${allSelected} @change=${(e) => {
                                                const checked = e.target.checked;
                                                const next = new Set(selectedRows);
                                                pageData.forEach((_, i) => {
                                                    if (checked) next.add(startIndex + i);
                                                    else next.delete(startIndex + i);
                                                });
                                                this.setState({ selectedRows: next });
                                            }}>
                                        </th>
                                    ` : Fragment}
                                    ${columns.map(col => {
                                        const isSorted = sortKey === col.key;
                                        const indicator = isSorted ? (sortOrder === 'asc' ? ' ▲' : ' ▼') : '';
                                        return html`
                                            <th style="padding: 12px 14px; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; opacity: 0.85; cursor: ${col.sortable !== false ? 'pointer' : 'default'}; user-select: none;"
                                                @click=${() => {
                                                    if (col.sortable === false) return;
                                                    if (sortKey === col.key) {
                                                        this.setState({ sortOrder: sortOrder === 'asc' ? 'desc' : 'asc' });
                                                    } else {
                                                        this.setState({ sortKey: col.key, sortOrder: 'asc' });
                                                    }
                                                }}>
                                                ${col.label || col.key} <span style="color: var(--sppux-primary, #6366f1); font-size: 0.7rem;">${indicator}</span>
                                            </th>
                                        `;
                                    })}
                                </tr>
                            </thead>
                            <tbody>
                                ${pageData.length === 0 ? html`
                                    <tr>
                                        <td colspan="${columns.length + (selectable ? 1 : 0)}" style="text-align: center; padding: 2.5rem; opacity: 0.6;">
                                            No records found.
                                        </td>
                                    </tr>
                                ` : pageData.map((row, idx) => {
                                    const actualIndex = startIndex + idx;
                                    const isRowSelected = selectedRows.has(actualIndex);
                                    return html`
                                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.04); transition: background 0.15s; background: ${isRowSelected ? 'rgba(99,102,241,0.08)' : 'transparent'}; cursor: ${this.props.onRowClick ? 'pointer' : 'default'};"
                                            @mouseenter=${(e) => { if (!isRowSelected) e.currentTarget.style.background = 'rgba(255,255,255,0.03)'; }}
                                            @mouseleave=${(e) => { if (!isRowSelected) e.currentTarget.style.background = 'transparent'; }}
                                            @click=${() => {
                                                if (this.props.onRowClick) this.props.onRowClick(row, actualIndex);
                                            }}>
                                            ${selectable ? html`
                                                <td style="padding: 10px 12px; text-align: center;" @click=${(e) => e.stopPropagation()}>
                                                    <input type="checkbox" ?checked=${isRowSelected} @change=${(e) => {
                                                        const next = new Set(selectedRows);
                                                        if (e.target.checked) next.add(actualIndex);
                                                        else next.delete(actualIndex);
                                                        this.setState({ selectedRows: next });
                                                    }}>
                                                </td>
                                            ` : Fragment}
                                            ${columns.map(col => html`
                                                <td style="padding: 12px 14px; opacity: 0.95;">
                                                    ${col.render ? col.render(row[col.key], row) : (row[col.key] !== undefined ? row[col.key] : '')}
                                                </td>
                                            `)}
                                        </tr>
                                    `;
                                })}
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; border-top: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.1)); background: rgba(0,0,0,0.15); font-size: 0.85rem; flex-wrap: wrap; gap: 8px;">
                        <div style="opacity: 0.7;">
                            Showing ${processed.length ? startIndex + 1 : 0} to ${Math.min(startIndex + pageSize, processed.length)} of ${processed.length} entries
                            ${selectedRows.size > 0 ? ` (${selectedRows.size} selected)` : ''}
                        </div>
                        <div style="display: flex; gap: 6px; align-items: center;">
                            <button type="button" class="btn ghost-btn btn-sm" ?disabled=${currentPage <= 1} @click=${() => this.setState({ page: currentPage - 1 })}>« Prev</button>
                            <span style="padding: 0 8px; opacity: 0.85;">Page ${currentPage} of ${totalPages}</span>
                            <button type="button" class="btn ghost-btn btn-sm" ?disabled=${currentPage >= totalPages} @click=${() => this.setState({ page: currentPage + 1 })}>Next »</button>
                        </div>
                    </div>
                </div>
            `;
        }
    };

    /**
     * SPPUX.DateRangePicker - Zero-Boilerplate Date Range Selector
     */
    SPPUX.DateRangePicker = class extends BaseComponent {
        onInit() {
            this.state = {
                open: false,
                startDate: this.props.startDate || '',
                endDate: this.props.endDate || '',
                selectedPreset: this.props.preset || 'last_7_days'
            };
        }

        applyPreset(preset) {
            const today = new Date();
            const formatDate = (d) => d.toISOString().split('T')[0];
            let start = new Date(today);
            let end = new Date(today);

            if (preset === 'today') {
                // today
            } else if (preset === 'yesterday') {
                start.setDate(today.getDate() - 1);
                end.setDate(today.getDate() - 1);
            } else if (preset === 'last_7_days') {
                start.setDate(today.getDate() - 6);
            } else if (preset === 'last_30_days') {
                start.setDate(today.getDate() - 29);
            } else if (preset === 'this_month') {
                start = new Date(today.getFullYear(), today.getMonth(), 1);
            } else if (preset === 'last_month') {
                start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                end = new Date(today.getFullYear(), today.getMonth(), 0);
            }

            const sStr = formatDate(start);
            const eStr = formatDate(end);
            this.setState({ startDate: sStr, endDate: eStr, selectedPreset: preset, open: false });
            if (typeof this.props.onChange === 'function') {
                this.props.onChange({ start: sStr, end: eStr, preset });
            }
        }

        render() {
            const { open, startDate, endDate, selectedPreset } = this.state;
            const displayLabel = startDate && endDate ? `${startDate} → ${endDate}` : (this.props.placeholder || 'Select Date Range');

            const presets = [
                { id: 'today', label: 'Today' },
                { id: 'yesterday', label: 'Yesterday' },
                { id: 'last_7_days', label: 'Last 7 Days' },
                { id: 'last_30_days', label: 'Last 30 Days' },
                { id: 'this_month', label: 'This Month' },
                { id: 'last_month', label: 'Last Month' }
            ];

            return html`
                <div class="sppux-daterange-picker" style="position: relative; display: inline-block;">
                    <button type="button" class="btn secondary-btn" style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem;"
                        @click=${() => this.setState({ open: !open })}>
                        <span>📅</span>
                        <span>${displayLabel}</span>
                        <span style="font-size: 0.7rem; opacity: 0.7;">▼</span>
                    </button>

                    ${open ? html`
                        <div class="glass-panel" style="position: absolute; top: calc(100% + 6px); left: 0; z-index: 1000; min-width: 320px; padding: 14px; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); display: flex; flex-direction: column; gap: 12px; background: var(--sppux-panel, #0f172a);">
                            <!-- Presets -->
                            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                ${presets.map(p => html`
                                    <button type="button" class="sub-tab-btn ${selectedPreset === p.id ? 'active' : ''}" 
                                        style="font-size: 0.75rem; padding: 4px 8px;"
                                        @click=${() => this.applyPreset(p.id)}>
                                        ${p.label}
                                    </button>
                                `)}
                            </div>

                            <!-- Custom Inputs -->
                            <div style="display: flex; gap: 8px; align-items: center; border-top: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.1)); padding-top: 10px;">
                                <input type="date" value="${startDate}" style="padding: 5px 8px; border-radius: 4px; border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.2)); background: rgba(0,0,0,0.2); color: #fff; font-size: 0.8rem; flex: 1;"
                                    @input=${(e) => this.setState({ startDate: e.target.value, selectedPreset: 'custom' })}>
                                <span style="opacity: 0.5;">➔</span>
                                <input type="date" value="${endDate}" style="padding: 5px 8px; border-radius: 4px; border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.2)); background: rgba(0,0,0,0.2); color: #fff; font-size: 0.8rem; flex: 1;"
                                    @input=${(e) => this.setState({ endDate: e.target.value, selectedPreset: 'custom' })}>
                            </div>

                            <div style="display: flex; justify-content: flex-end; gap: 6px;">
                                <button type="button" class="btn secondary-btn btn-sm" @click=${() => this.setState({ open: false })}>Cancel</button>
                                <button type="button" class="btn primary-btn btn-sm" @click=${() => {
                                    this.setState({ open: false });
                                    if (typeof this.props.onChange === 'function') {
                                        this.props.onChange({ start: startDate, end: endDate, preset: selectedPreset });
                                    }
                                }}>Apply</button>
                            </div>
                        </div>
                    ` : Fragment}
                </div>
            `;
        }
    };

    /**
     * SPPUX.FilterBuilder - Multi-Condition Dynamic Query Builder
     * Lets developers configure complex filters (Field + Operator + Value) with AND/OR logic.
     */
    SPPUX.FilterBuilder = class extends BaseComponent {
        onInit() {
            const fields = this.props.fields || [
                { key: 'name', label: 'Name', type: 'string' },
                { key: 'status', label: 'Status', type: 'select', options: ['active', 'inactive', 'pending'] }
            ];
            const defaultField = fields[0]?.key || '';
            this.state = {
                conjunction: this.props.conjunction || 'AND',
                rules: this.props.rules && this.props.rules.length ? this.props.rules : [
                    { id: 'r_' + Date.now(), field: defaultField, operator: 'equals', value: '' }
                ]
            };
        }

        getOperators(fieldType) {
            switch(fieldType) {
                case 'number':
                    return [
                        { key: 'equals', label: '=' },
                        { key: 'not_equals', label: '≠' },
                        { key: 'greater_than', label: '>' },
                        { key: 'greater_equals', label: '≥' },
                        { key: 'less_than', label: '<' },
                        { key: 'less_equals', label: '≤' }
                    ];
                case 'date':
                    return [
                        { key: 'equals', label: 'On' },
                        { key: 'before', label: 'Before' },
                        { key: 'after', label: 'After' },
                        { key: 'between', label: 'Between' }
                    ];
                case 'select':
                    return [
                        { key: 'equals', label: 'Is' },
                        { key: 'not_equals', label: 'Is Not' }
                    ];
                default:
                    return [
                        { key: 'contains', label: 'Contains' },
                        { key: 'equals', label: 'Equals' },
                        { key: 'starts_with', label: 'Starts With' },
                        { key: 'ends_with', label: 'Ends With' },
                        { key: 'not_contains', label: 'Does Not Contain' },
                        { key: 'is_empty', label: 'Is Empty' },
                        { key: 'is_not_empty', label: 'Is Not Empty' }
                    ];
            }
        }

        addRule() {
            const fields = this.props.fields || [];
            const defaultField = fields[0]?.key || '';
            const newRule = {
                id: 'r_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
                field: defaultField,
                operator: 'equals',
                value: ''
            };
            const nextRules = [...this.state.rules, newRule];
            this.setState({ rules: nextRules });
            this._notifyChange(nextRules, this.state.conjunction);
        }

        removeRule(id) {
            const nextRules = this.state.rules.filter(r => r.id !== id);
            this.setState({ rules: nextRules });
            this._notifyChange(nextRules, this.state.conjunction);
        }

        updateRule(id, updates) {
            const nextRules = this.state.rules.map(r => r.id === id ? { ...r, ...updates } : r);
            this.setState({ rules: nextRules });
            this._notifyChange(nextRules, this.state.conjunction);
        }

        toggleConjunction() {
            const nextConj = this.state.conjunction === 'AND' ? 'OR' : 'AND';
            this.setState({ conjunction: nextConj });
            this._notifyChange(this.state.rules, nextConj);
        }

        _notifyChange(rules, conjunction) {
            if (typeof this.props.onChange === 'function') {
                this.props.onChange({
                    conjunction,
                    rules: rules.map(({ field, operator, value }) => ({ field, operator, value }))
                });
            }
        }

        render() {
            const fields = this.props.fields || [];
            const { conjunction, rules } = this.state;

            return html`
                <div class="sppux-filter-builder" style="background: var(--sppux-panel, rgba(15,23,42,0.9)); border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.1)); border-radius: var(--sppux-radius-md, 8px); padding: 14px; display: flex; flex-direction: column; gap: 10px;">
                    <!-- Conjunction Header -->
                    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.08)); padding-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 0.85rem; opacity: 0.8;">Match</span>
                            <button type="button" class="btn ${conjunction === 'AND' ? 'primary-btn' : 'secondary-btn'} btn-sm" style="padding: 2px 10px; font-size: 0.75rem; font-weight: 600;" @click=${() => this.toggleConjunction()}>
                                ${conjunction === 'AND' ? 'ALL (AND)' : 'ANY (OR)'}
                            </button>
                            <span style="font-size: 0.85rem; opacity: 0.8;">of the following conditions:</span>
                        </div>
                        <button type="button" class="btn ghost-btn btn-sm" style="font-size: 0.8rem; color: var(--sppux-primary, #6366f1);" @click=${() => this.addRule()}>
                            + Add Condition
                        </button>
                    </div>

                    <!-- Rules List -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        ${rules.map((rule, idx) => {
                            const fieldDef = fields.find(f => f.key === rule.field) || fields[0] || {};
                            const ops = this.getOperators(fieldDef.type);
                            const hideValueInput = rule.operator === 'is_empty' || rule.operator === 'is_not_empty';

                            return html`
                                <div class="sppux-filter-rule" style="display: flex; align-items: center; gap: 8px; background: rgba(0,0,0,0.15); padding: 6px 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); flex-wrap: wrap;">
                                    <span style="font-size: 0.75rem; opacity: 0.5; width: 20px;">#${idx + 1}</span>

                                    <!-- Field Selector -->
                                    <select style="padding: 4px 8px; border-radius: 4px; background: rgba(0,0,0,0.3); border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.15)); color: #fff; font-size: 0.8rem;"
                                        @change=${(e) => {
                                            const newField = e.target.value;
                                            const newDef = fields.find(f => f.key === newField) || {};
                                            const newOps = this.getOperators(newDef.type);
                                            this.updateRule(rule.id, { field: newField, operator: newOps[0]?.key || 'equals' });
                                        }}>
                                        ${fields.map(f => html`<option value="${f.key}" ?selected=${f.key === rule.field}>${f.label || f.key}</option>`)}
                                    </select>

                                    <!-- Operator Selector -->
                                    <select style="padding: 4px 8px; border-radius: 4px; background: rgba(0,0,0,0.3); border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.15)); color: #fff; font-size: 0.8rem;"
                                        @change=${(e) => this.updateRule(rule.id, { operator: e.target.value })}>
                                        ${ops.map(op => html`<option value="${op.key}" ?selected=${op.key === rule.operator}>${op.label}</option>`)}
                                    </select>

                                    <!-- Value Input -->
                                    ${!hideValueInput ? (
                                        fieldDef.type === 'select' && Array.isArray(fieldDef.options) ? html`
                                            <select style="padding: 4px 8px; border-radius: 4px; background: rgba(0,0,0,0.3); border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.15)); color: #fff; font-size: 0.8rem; flex: 1; min-width: 140px;"
                                                @change=${(e) => this.updateRule(rule.id, { value: e.target.value })}>
                                                <option value="">-- Select --</option>
                                                ${fieldDef.options.map(opt => {
                                                    const val = typeof opt === 'object' ? opt.value : opt;
                                                    const lbl = typeof opt === 'object' ? opt.label : opt;
                                                    return html`<option value="${val}" ?selected=${val === rule.value}>${lbl}</option>`;
                                                })}
                                            </select>
                                        ` : html`
                                            <input type="${fieldDef.type === 'number' ? 'number' : fieldDef.type === 'date' ? 'date' : 'text'}" 
                                                placeholder="Value..." value="${rule.value || ''}"
                                                style="padding: 4px 8px; border-radius: 4px; background: rgba(0,0,0,0.3); border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.15)); color: #fff; font-size: 0.8rem; flex: 1; min-width: 140px;"
                                                @input=${(e) => this.updateRule(rule.id, { value: e.target.value })}>
                                        `
                                    ) : Fragment}

                                    <!-- Delete Rule -->
                                    <button type="button" class="btn ghost-btn btn-sm" style="color: #ef4444; padding: 2px 6px; font-size: 0.85rem;" title="Remove Condition"
                                        @click=${() => this.removeRule(rule.id)}>
                                        ✕
                                    </button>
                                </div>
                            `;
                        })}
                    </div>
                </div>
            `;
        }
    };

    /**
     * SPPUX.FileUpload - Reactive Multi-File Upload & Asset Manager
     * Features drag & drop, image previews, upload progress indicators, and remove/retry controls.
     */
    SPPUX.FileUpload = class extends BaseComponent {
        onInit() {
            this.state = {
                files: [],
                isDragging: false,
                isUploading: false
            };
        }

        formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }

        addFiles(newFiles) {
            const mapped = Array.from(newFiles).map(f => {
                const item = {
                    id: 'f_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
                    file: f,
                    name: f.name,
                    size: f.size,
                    type: f.type,
                    status: 'ready',
                    progress: 0,
                    previewUrl: f.type.startsWith('image/') ? URL.createObjectURL(f) : null
                };
                return item;
            });
            const updated = [...this.state.files, ...mapped];
            this.setState({ files: updated });
            if (typeof this.props.onChange === 'function') {
                this.props.onChange(updated);
            }
        }

        removeFile(id) {
            const target = this.state.files.find(f => f.id === id);
            if (target && target.previewUrl) URL.revokeObjectURL(target.previewUrl);
            const updated = this.state.files.filter(f => f.id !== id);
            this.setState({ files: updated });
            if (typeof this.props.onChange === 'function') {
                this.props.onChange(updated);
            }
        }

        clearAll() {
            this.state.files.forEach(f => {
                if (f.previewUrl) URL.revokeObjectURL(f.previewUrl);
            });
            this.setState({ files: [] });
            if (typeof this.props.onChange === 'function') {
                this.props.onChange([]);
            }
        }

        async startUpload() {
            if (!this.state.files.length) return;
            this.setState({ isUploading: true });

            if (typeof this.props.onUpload === 'function') {
                try {
                    await this.props.onUpload(this.state.files, (fileId, progress) => {
                        const files = this.state.files.map(f => f.id === fileId ? { ...f, progress, status: progress >= 100 ? 'success' : 'uploading' } : f);
                        this.setState({ files });
                    });
                } catch (e) {
                    console.error('[SPPUX] Upload Error:', e);
                } finally {
                    this.setState({ isUploading: false });
                }
            } else {
                for (const f of this.state.files) {
                    if (f.status === 'success') continue;
                    for (let p = 20; p <= 100; p += 20) {
                        await new Promise(r => setTimeout(r, 100));
                        const files = this.state.files.map(item => item.id === f.id ? { ...item, progress: p, status: p === 100 ? 'success' : 'uploading' } : item);
                        this.setState({ files });
                    }
                }
                this.setState({ isUploading: false });
                SPPUX.notify('Files uploaded successfully', 'success');
            }
        }

        render() {
            const { files, isDragging, isUploading } = this.state;
            const accept = this.props.accept || '*';

            return html`
                <div class="sppux-fileupload-container" style="display: flex; flex-direction: column; gap: 12px; width: 100%;">
                    <!-- Drop Zone Area -->
                    <div class="sppux-dropzone-box" style="border: 2px dashed ${isDragging ? 'var(--sppux-primary, #6366f1)' : 'var(--sppux-glass-border, rgba(255,255,255,0.15))'}; border-radius: var(--sppux-radius-md, 10px); padding: 24px; text-align: center; background: ${isDragging ? 'rgba(99,102,241,0.08)' : 'rgba(0,0,0,0.15)'}; cursor: pointer; transition: all 0.2s;"
                        @dragover=${(e) => { e.preventDefault(); this.setState({ isDragging: true }); }}
                        @dragleave=${() => this.setState({ isDragging: false })}
                        @drop=${(e) => { e.preventDefault(); this.setState({ isDragging: false }); if (e.dataTransfer.files) this.addFiles(e.dataTransfer.files); }}
                        @click=${() => {
                            const inp = document.createElement('input');
                            inp.type = 'file';
                            inp.multiple = this.props.multiple !== false;
                            inp.accept = accept;
                            inp.onchange = () => { if (inp.files) this.addFiles(inp.files); };
                            inp.click();
                        }}>
                        <div style="font-size: 2.2rem; margin-bottom: 6px;">📁</div>
                        <div style="font-weight: 600; font-size: 0.95rem; color: var(--sppux-text, #f8fafc);">Drag & drop files here, or click to browse</div>
                        <div style="font-size: 0.8rem; opacity: 0.6; margin-top: 4px;">Supports ${accept === '*' ? 'all file formats' : accept} up to 50MB</div>
                    </div>

                    <!-- Selected Files List -->
                    ${files.length > 0 ? html`
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 0.85rem; font-weight: 600;">Selected Files (${files.length})</span>
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" class="btn ghost-btn btn-sm" style="font-size: 0.75rem;" @click=${() => this.clearAll()}>Clear All</button>
                                    <button type="button" class="btn primary-btn btn-sm" ?disabled=${isUploading} style="font-size: 0.75rem;" @click=${() => this.startUpload()}>
                                        ${isUploading ? 'Uploading...' : 'Upload Now'}
                                    </button>
                                </div>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 6px; max-height: 280px; overflow-y: auto;">
                                ${files.map(f => html`
                                    <div class="sppux-file-item" style="display: flex; align-items: center; gap: 12px; background: var(--sppux-panel, rgba(15,23,42,0.8)); border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.08)); border-radius: 8px; padding: 8px 12px;">
                                        <!-- Thumbnail / Icon -->
                                        ${f.previewUrl ? html`
                                            <img src="${f.previewUrl}" style="width: 36px; height: 36px; object-fit: cover; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1);">
                                        ` : html`
                                            <div style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.05); border-radius: 4px; font-size: 1.2rem;">
                                                📄
                                            </div>
                                        `}

                                        <!-- Meta & Progress -->
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                                                <span style="font-size: 0.85rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${f.name}">${f.name}</span>
                                                <span style="font-size: 0.75rem; opacity: 0.6;">${this.formatBytes(f.size)}</span>
                                            </div>
                                            <!-- Progress Bar -->
                                            <div style="height: 4px; width: 100%; background: rgba(255,255,255,0.08); border-radius: 2px; overflow: hidden;">
                                                <div style="height: 100%; width: ${f.progress}%; background: ${f.status === 'success' ? '#22c55e' : f.status === 'error' ? '#ef4444' : 'var(--sppux-primary, #6366f1)'}; transition: width 0.2s;"></div>
                                            </div>
                                        </div>

                                        <!-- Status Icon & Remove -->
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            ${f.status === 'success' ? html`<span style="color: #22c55e; font-size: 0.9rem;" title="Uploaded">✓</span>` : Fragment}
                                            ${f.status === 'error' ? html`<span style="color: #ef4444; font-size: 0.9rem;" title="Failed">✕</span>` : Fragment}
                                            <button type="button" class="btn ghost-btn btn-sm" style="color: #ef4444; padding: 2px 6px;" title="Remove" @click=${() => this.removeFile(f.id)}>✕</button>
                                        </div>
                                    </div>
                                `)}
                            </div>
                        </div>
                    ` : Fragment}
                </div>
            `;
        }
    };

    /**
     * SPPUX.Timeline - Visual Activity Feed & Audit Lineage
     */
    SPPUX.Timeline = class extends BaseComponent {
        render() {
            const items = this.props.items || [];
            const colorMap = {
                success: '#22c55e',
                warning: '#f59e0b',
                danger: '#ef4444',
                error: '#ef4444',
                info: '#38bdf8',
                primary: 'var(--sppux-primary, #6366f1)'
            };

            return html`
                <div class="sppux-timeline" style="position: relative; padding-left: 28px; display: flex; flex-direction: column; gap: 20px;">
                    <!-- Vertical Line -->
                    <div style="position: absolute; left: 11px; top: 8px; bottom: 8px; width: 2px; background: var(--sppux-glass-border, rgba(255,255,255,0.12));"></div>

                    ${items.map((item) => {
                        const color = colorMap[item.status] || colorMap.primary;
                        return html`
                            <div class="sppux-timeline-item" style="position: relative; display: flex; flex-direction: column; gap: 4px;">
                                <!-- Node Dot -->
                                <div style="position: absolute; left: -24px; top: 3px; width: 14px; height: 14px; border-radius: 50%; background: ${color}; box-shadow: 0 0 10px ${color}; border: 2px solid var(--sppux-panel, #0f172a);"></div>

                                <!-- Header: Title & Timestamp -->
                                <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 6px;">
                                    <strong style="font-size: 0.9rem; color: var(--sppux-text, #f8fafc);">${item.title}</strong>
                                    <span style="font-size: 0.75rem; opacity: 0.6;">${item.timestamp || ''}</span>
                                </div>

                                <!-- Description / Content -->
                                ${item.description ? html`
                                    <div style="font-size: 0.85rem; opacity: 0.8; line-height: 1.4;">${item.description}</div>
                                ` : Fragment}

                                <!-- Actions or Tag -->
                                ${item.tag ? html`
                                    <div style="margin-top: 2px;">
                                        <span class="badge" style="background: rgba(255,255,255,0.08); font-size: 0.7rem; padding: 2px 6px; border-radius: 4px;">${item.tag}</span>
                                    </div>
                                ` : Fragment}
                            </div>
                        `;
                    })}
                </div>
            `;
        }
    };
    SPPUX.Timeline.render = (items, options = {}) => {
        const comp = new SPPUX.Timeline(null, document.createElement('div'), { items, ...options });
        return comp.render();
    };

    /**
     * SPPUX.CodeBlock - Syntax-Themed Code Viewer with 1-Click Copy
     */
    SPPUX.CodeBlock = class extends BaseComponent {
        onInit() {
            this.state = { copied: false };
        }

        copy() {
            const code = this.props.code || this.container.querySelector('code')?.innerText || '';
            navigator.clipboard.writeText(code).then(() => {
                this.setState({ copied: true });
                setTimeout(() => this.setState({ copied: false }), 2000);
            });
        }

        render() {
            const code = this.props.code || '';
            const language = this.props.language || 'text';
            const filename = this.props.filename || this.props.title || null;
            const lineNumbers = this.props.lineNumbers !== false;
            const lines = code.split('\n');

            return html`
                <div class="sppux-codeblock-box" style="border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.12)); border-radius: var(--sppux-radius-md, 8px); background: #0b0f19; overflow: hidden; font-family: 'JetBrains Mono', Consolas, monospace; font-size: 0.85rem;">
                    <!-- Header Bar -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 12px; background: rgba(255,255,255,0.03); border-bottom: 1px solid rgba(255,255,255,0.06); font-size: 0.75rem;">
                        <span style="opacity: 0.7;">${filename || language.toUpperCase()}</span>
                        <button type="button" class="btn ghost-btn btn-sm" style="font-size: 0.75rem; padding: 2px 8px;" @click=${() => this.copy()}>
                            ${this.state.copied ? '✓ Copied' : '📋 Copy'}
                        </button>
                    </div>

                    <!-- Code Body -->
                    <div style="display: flex; overflow-x: auto; padding: 12px 0; max-height: ${this.props.maxHeight || '400px'};">
                        ${lineNumbers ? html`
                            <div style="user-select: none; opacity: 0.35; text-align: right; padding: 0 12px 0 14px; border-right: 1px solid rgba(255,255,255,0.06); line-height: 1.5;">
                                ${lines.map((_, i) => html`<div>${i + 1}</div>`)}
                            </div>
                        ` : Fragment}
                        <pre style="margin: 0; padding: 0 16px; line-height: 1.5; color: #e2e8f0; flex: 1; overflow-x: auto;"><code>${code}</code></pre>
                    </div>
                </div>
            `;
        }
    };

    /**
     * SPPUX.MetricGrid - Responsive KPI Dashboard Multi-Card Grid
     */
    SPPUX.MetricGrid = class extends BaseComponent {
        render() {
            const metrics = this.props.metrics || [];
            return html`
                <div class="sppux-metric-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; width: 100%;">
                    ${metrics.map(m => {
                        const isPositive = m.changeType === 'increase' || (typeof m.change === 'string' && m.change.startsWith('+'));
                        const isNegative = m.changeType === 'decrease' || (typeof m.change === 'string' && m.change.startsWith('-'));
                        const changeColor = isPositive ? '#22c55e' : isNegative ? '#ef4444' : 'var(--sppux-text-dim, #94a3b8)';

                        return html`
                            <div class="sppux-metric-card" style="padding: 16px; border-radius: var(--sppux-radius-md, 10px); background: var(--sppux-panel, rgba(15,23,42,0.85)); border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.1)); display: flex; flex-direction: column; gap: 8px; transition: transform 0.15s, box-shadow 0.15s;"
                                @mouseenter=${(e) => { e.currentTarget.style.transform = 'translateY(-2px)'; e.currentTarget.style.boxShadow = '0 8px 20px rgba(0,0,0,0.3)'; }}
                                @mouseleave=${(e) => { e.currentTarget.style.transform = 'translateY(0)'; e.currentTarget.style.boxShadow = 'none'; }}>
                                <div style="display: flex; justify-content: space-between; align-items: center; opacity: 0.75; font-size: 0.85rem;">
                                    <span>${m.title}</span>
                                    <span style="font-size: 1.1rem;">${m.icon || '📊'}</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                                    <div>
                                        <div style="font-size: 1.7rem; font-weight: 700; color: var(--sppux-text, #f8fafc);">${m.value}</div>
                                        ${m.change ? html`
                                            <div style="font-size: 0.75rem; color: ${changeColor}; font-weight: 500; margin-top: 3px;">
                                                ${m.change}
                                            </div>
                                        ` : Fragment}
                                    </div>
                                    ${m.sparkline && SPPUX.Chart ? SPPUX.Chart.renderSparkline(m.sparkline, 'line', isPositive ? '#22c55e' : 'var(--sppux-primary, #6366f1)', 80, 28) : Fragment}
                                </div>
                            </div>
                        `;
                    })}
                </div>
            `;
        }
    };

    /**
     * SPPUX.Badge - Standardized Enterprise Status Badges with Pulse Indicator
     */
    SPPUX.Badge = class extends BaseComponent {
        render() {
            const label = this.props.label || this.container.innerText || '';
            const variant = this.props.variant || 'primary';
            const pulse = !!this.props.pulse;
            const size = this.props.size || 'md';

            const colorMap = {
                success: { bg: 'rgba(34, 197, 94, 0.15)', text: '#4ade80', dot: '#22c55e' },
                warning: { bg: 'rgba(245, 158, 11, 0.15)', text: '#fcd34d', dot: '#f59e0b' },
                danger: { bg: 'rgba(239, 68, 68, 0.15)', text: '#f87171', dot: '#ef4444' },
                error: { bg: 'rgba(239, 68, 68, 0.15)', text: '#f87171', dot: '#ef4444' },
                info: { bg: 'rgba(56, 189, 248, 0.15)', text: '#7dd3fc', dot: '#38bdf8' },
                purple: { bg: 'rgba(168, 85, 247, 0.15)', text: '#c084fc', dot: '#a855f7' },
                neutral: { bg: 'rgba(148, 163, 184, 0.15)', text: '#cbd5e1', dot: '#94a3b8' },
                primary: { bg: 'rgba(99, 102, 241, 0.15)', text: '#a5b4fc', dot: '#6366f1' }
            };

            const theme = colorMap[variant] || colorMap.primary;
            const padding = size === 'sm' ? '2px 6px' : size === 'lg' ? '4px 12px' : '3px 8px';
            const fontSize = size === 'sm' ? '0.7rem' : size === 'lg' ? '0.85rem' : '0.75rem';

            return html`
                <span class="sppux-badge sppux-badge-${variant}" style="display: inline-flex; align-items: center; gap: 6px; padding: ${padding}; border-radius: 9999px; background: ${theme.bg}; color: ${theme.text}; font-size: ${fontSize}; font-weight: 500; line-height: 1;">
                    ${pulse ? html`
                        <span style="position: relative; display: flex; width: 6px; height: 6px;">
                            <span style="position: absolute; width: 100%; height: 100%; border-radius: 50%; background: ${theme.dot}; opacity: 0.75; animation: sppux-ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;"></span>
                            <span style="position: relative; width: 6px; height: 6px; border-radius: 50%; background: ${theme.dot};"></span>
                        </span>
                    ` : Fragment}
                    <span>${label}</span>
                </span>
            `;
        }
    };
    SPPUX.Badge.render = (label, options = {}) => {
        const comp = new SPPUX.Badge(null, document.createElement('div'), { label, ...options });
        return comp.render();
    };

    /**
     * SPPUX.EmptyState - Zero-Data Placeholder with Call-to-Action
     */
    SPPUX.EmptyState = class extends BaseComponent {
        render() {
            const icon = this.props.icon || '📭';
            const title = this.props.title || 'No data found';
            const description = this.props.description || 'Get started by creating your first entry.';
            const primaryAction = this.props.primaryAction || null;
            const secondaryAction = this.props.secondaryAction || null;

            return html`
                <div class="sppux-empty-state" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 24px; text-align: center; border: 1px dashed var(--sppux-glass-border, rgba(255,255,255,0.12)); border-radius: var(--sppux-radius-md, 10px); background: rgba(0,0,0,0.1);">
                    <div style="font-size: 3rem; margin-bottom: 12px; opacity: 0.85;">${icon}</div>
                    <h3 style="margin: 0 0 6px 0; font-size: 1.15rem; color: var(--sppux-text, #f8fafc);">${title}</h3>
                    <p style="margin: 0 0 18px 0; max-width: 400px; font-size: 0.85rem; opacity: 0.7; line-height: 1.4;">${description}</p>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        ${primaryAction ? html`
                            <button type="button" class="btn primary-btn btn-sm" @click=${primaryAction.onClick}>
                                ${primaryAction.label || 'Create New'}
                            </button>
                        ` : Fragment}
                        ${secondaryAction ? html`
                            <button type="button" class="btn secondary-btn btn-sm" @click=${secondaryAction.onClick}>
                                ${secondaryAction.label || 'Reset'}
                            </button>
                        ` : Fragment}
                    </div>
                </div>
            `;
        }
    };
    SPPUX.EmptyState.render = (options = {}) => {
        const comp = new SPPUX.EmptyState(null, document.createElement('div'), options);
        return comp.render();
    };

    /**
     * SPPUX.DiffViewer - Visual Code/Text Line-by-Line Difference Inspector
     */
    SPPUX.DiffViewer = class extends BaseComponent {
        computeDiff(oldText, newText) {
            const oldLines = (oldText || '').split('\n');
            const newLines = (newText || '').split('\n');
            const diff = [];
            let i = 0, j = 0;

            while (i < oldLines.length || j < newLines.length) {
                if (i < oldLines.length && j < newLines.length && oldLines[i] === newLines[j]) {
                    diff.push({ type: 'unchanged', oldLine: i + 1, newLine: j + 1, content: oldLines[i] });
                    i++; j++;
                } else if (j < newLines.length && (!oldLines.includes(newLines[j]) || (i < oldLines.length && newLines.indexOf(oldLines[i]) > j))) {
                    diff.push({ type: 'added', oldLine: null, newLine: j + 1, content: newLines[j] });
                    j++;
                } else if (i < oldLines.length) {
                    diff.push({ type: 'removed', oldLine: i + 1, newLine: null, content: oldLines[i] });
                    i++;
                } else {
                    diff.push({ type: 'added', oldLine: null, newLine: j + 1, content: newLines[j] });
                    j++;
                }
            }
            return diff;
        }

        render() {
            const oldText = this.props.oldText || '';
            const newText = this.props.newText || '';
            const title = this.props.title || 'Diff Comparison';
            const diff = this.computeDiff(oldText, newText);

            return html`
                <div class="sppux-diff-viewer" style="border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.12)); border-radius: var(--sppux-radius-md, 8px); background: #0b0f19; overflow: hidden; font-family: 'JetBrains Mono', Consolas, monospace; font-size: 0.85rem;">
                    <div style="padding: 8px 14px; background: rgba(255,255,255,0.03); border-bottom: 1px solid rgba(255,255,255,0.06); font-weight: 600; font-size: 0.8rem; color: var(--sppux-text, #f8fafc);">
                        ${title}
                    </div>
                    <div style="overflow-x: auto; line-height: 1.5;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tbody>
                                ${diff.map(line => {
                                    const isAdd = line.type === 'added';
                                    const isDel = line.type === 'removed';
                                    const bg = isAdd ? 'rgba(34, 197, 94, 0.15)' : isDel ? 'rgba(239, 68, 68, 0.15)' : 'transparent';
                                    const prefix = isAdd ? '+' : isDel ? '-' : ' ';
                                    const textColor = isAdd ? '#4ade80' : isDel ? '#f87171' : '#cbd5e1';

                                    return html`
                                        <tr style="background: ${bg};">
                                            <td style="width: 40px; padding: 2px 8px; text-align: right; opacity: 0.35; user-select: none; border-right: 1px solid rgba(255,255,255,0.05);">${line.oldLine || ''}</td>
                                            <td style="width: 40px; padding: 2px 8px; text-align: right; opacity: 0.35; user-select: none; border-right: 1px solid rgba(255,255,255,0.05);">${line.newLine || ''}</td>
                                            <td style="width: 20px; padding: 2px 4px; text-align: center; color: ${textColor}; font-weight: bold; user-select: none;">${prefix}</td>
                                            <td style="padding: 2px 8px; color: ${textColor}; white-space: pre-wrap; word-break: break-all;">${line.content}</td>
                                        </tr>
                                    `;
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }
    };

    /**
     * SPPUX.TransferList - Dual-Listbox for Role, Permission & Course Assignment
     */
    SPPUX.TransferList = class extends BaseComponent {
        onInit() {
            this.state = {
                selectedIds: new Set(this.props.selected || []),
                availableChecked: new Set(),
                selectedChecked: new Set(),
                searchLeft: '',
                searchRight: ''
            };
        }

        moveRight() {
            const nextSelected = new Set(this.state.selectedIds);
            this.state.availableChecked.forEach(id => nextSelected.add(id));
            this.setState({ selectedIds: nextSelected, availableChecked: new Set() });
            this._notifyChange(nextSelected);
        }

        moveAllRight() {
            const allItems = this.props.items || [];
            const nextSelected = new Set(allItems.map(i => i.id));
            this.setState({ selectedIds: nextSelected, availableChecked: new Set() });
            this._notifyChange(nextSelected);
        }

        moveLeft() {
            const nextSelected = new Set(this.state.selectedIds);
            this.state.selectedChecked.forEach(id => nextSelected.delete(id));
            this.setState({ selectedIds: nextSelected, selectedChecked: new Set() });
            this._notifyChange(nextSelected);
        }

        moveAllLeft() {
            const nextSelected = new Set();
            this.setState({ selectedIds: nextSelected, selectedChecked: new Set() });
            this._notifyChange(nextSelected);
        }

        _notifyChange(selectedSet) {
            if (typeof this.props.onChange === 'function') {
                this.props.onChange(Array.from(selectedSet));
            }
        }

        render() {
            const items = this.props.items || [];
            const titles = this.props.titles || ['Available', 'Selected'];
            const { selectedIds, availableChecked, selectedChecked, searchLeft, searchRight } = this.state;

            const availableItems = items.filter(i => !selectedIds.has(i.id) && (i.label || '').toLowerCase().includes(searchLeft.toLowerCase()));
            const selectedItems = items.filter(i => selectedIds.has(i.id) && (i.label || '').toLowerCase().includes(searchRight.toLowerCase()));

            return html`
                <div class="sppux-transfer-list" style="display: flex; gap: 12px; align-items: center; width: 100%; flex-wrap: wrap;">
                    <!-- Left List (Available) -->
                    <div style="flex: 1; min-width: 240px; border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.1)); border-radius: var(--sppux-radius-md, 8px); background: var(--sppux-panel, rgba(15,23,42,0.9)); overflow: hidden; display: flex; flex-direction: column;">
                        <div style="padding: 8px 12px; background: rgba(255,255,255,0.03); border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600;">
                            <span>${titles[0]}</span>
                            <span class="badge" style="background: rgba(255,255,255,0.1); font-size: 0.7rem; padding: 2px 6px; border-radius: 10px;">${availableItems.length}</span>
                        </div>
                        <input type="text" placeholder="Search..." value="${searchLeft}" style="margin: 8px; padding: 5px 8px; border-radius: 4px; border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.15)); background: rgba(0,0,0,0.2); color: #fff; font-size: 0.8rem;"
                            @input=${(e) => this.setState({ searchLeft: e.target.value })}>
                        <div style="height: 200px; overflow-y: auto; padding: 4px 8px; display: flex; flex-direction: column; gap: 2px;">
                            ${availableItems.map(item => html`
                                <label style="display: flex; align-items: center; gap: 8px; padding: 5px 8px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; background: ${availableChecked.has(item.id) ? 'rgba(99,102,241,0.1)' : 'transparent'};"
                                    @mouseenter=${(e) => e.currentTarget.style.background = 'rgba(255,255,255,0.04)'}
                                    @mouseleave=${(e) => e.currentTarget.style.background = availableChecked.has(item.id) ? 'rgba(99,102,241,0.1)' : 'transparent'}>
                                    <input type="checkbox" ?checked=${availableChecked.has(item.id)} @change=${(e) => {
                                        const set = new Set(availableChecked);
                                        if (e.target.checked) set.add(item.id); else set.delete(item.id);
                                        this.setState({ availableChecked: set });
                                    }}>
                                    <span>${item.label}</span>
                                </label>
                            `)}
                        </div>
                    </div>

                    <!-- Transfer Action Buttons -->
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <button type="button" class="btn secondary-btn btn-sm" ?disabled=${!availableChecked.size} @click=${() => this.moveRight()}>›</button>
                        <button type="button" class="btn secondary-btn btn-sm" ?disabled=${!availableItems.length} @click=${() => this.moveAllRight()}>»</button>
                        <button type="button" class="btn secondary-btn btn-sm" ?disabled=${!selectedChecked.size} @click=${() => this.moveLeft()}>‹</button>
                        <button type="button" class="btn secondary-btn btn-sm" ?disabled=${!selectedItems.length} @click=${() => this.moveAllLeft()}>«</button>
                    </div>

                    <!-- Right List (Selected) -->
                    <div style="flex: 1; min-width: 240px; border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.1)); border-radius: var(--sppux-radius-md, 8px); background: var(--sppux-panel, rgba(15,23,42,0.9)); overflow: hidden; display: flex; flex-direction: column;">
                        <div style="padding: 8px 12px; background: rgba(255,255,255,0.03); border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600;">
                            <span>${titles[1]}</span>
                            <span class="badge" style="background: rgba(99,102,241,0.2); color: #818cf8; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px;">${selectedItems.length}</span>
                        </div>
                        <input type="text" placeholder="Search..." value="${searchRight}" style="margin: 8px; padding: 5px 8px; border-radius: 4px; border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.15)); background: rgba(0,0,0,0.2); color: #fff; font-size: 0.8rem;"
                            @input=${(e) => this.setState({ searchRight: e.target.value })}>
                        <div style="height: 200px; overflow-y: auto; padding: 4px 8px; display: flex; flex-direction: column; gap: 2px;">
                            ${selectedItems.map(item => html`
                                <label style="display: flex; align-items: center; gap: 8px; padding: 5px 8px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; background: ${selectedChecked.has(item.id) ? 'rgba(99,102,241,0.1)' : 'transparent'};"
                                    @mouseenter=${(e) => e.currentTarget.style.background = 'rgba(255,255,255,0.04)'}
                                    @mouseleave=${(e) => e.currentTarget.style.background = selectedChecked.has(item.id) ? 'rgba(99,102,241,0.1)' : 'transparent'}>
                                    <input type="checkbox" ?checked=${selectedChecked.has(item.id)} @change=${(e) => {
                                        const set = new Set(selectedChecked);
                                        if (e.target.checked) set.add(item.id); else set.delete(item.id);
                                        this.setState({ selectedChecked: set });
                                    }}>
                                    <span>${item.label}</span>
                                </label>
                            `)}
                        </div>
                    </div>
                </div>
            `;
        }
    };

    /**
     * SPPUX.Wizard - Multi-Step Interactive Workflow Wizard
     */
    SPPUX.Wizard = class extends BaseComponent {
        onInit() {
            this.state = {
                currentStep: this.props.initialStep || 0,
                completedSteps: new Set()
            };
        }

        next() {
            const steps = this.props.steps || [];
            const curr = this.state.currentStep;
            if (curr < steps.length - 1) {
                const step = steps[curr];
                if (typeof step.validate === 'function' && !step.validate()) {
                    return;
                }
                const completed = new Set(this.state.completedSteps);
                completed.add(curr);
                const nextStep = curr + 1;
                this.setState({ currentStep: nextStep, completedSteps: completed });
                if (typeof this.props.onStepChange === 'function') this.props.onStepChange(nextStep, curr);
            }
        }

        prev() {
            const curr = this.state.currentStep;
            if (curr > 0) {
                const prevStep = curr - 1;
                this.setState({ currentStep: prevStep });
                if (typeof this.props.onStepChange === 'function') this.props.onStepChange(prevStep, curr);
            }
        }

        complete() {
            const steps = this.props.steps || [];
            const curr = this.state.currentStep;
            const step = steps[curr];
            if (typeof step.validate === 'function' && !step.validate()) return;
            const completed = new Set(this.state.completedSteps);
            completed.add(curr);
            this.setState({ completedSteps: completed });
            if (typeof this.props.onComplete === 'function') this.props.onComplete();
        }

        render() {
            const steps = this.props.steps || [];
            const { currentStep, completedSteps } = this.state;
            const isLast = currentStep === steps.length - 1;
            const activeStep = steps[currentStep];

            return html`
                <div class="sppux-wizard-box" style="display: flex; flex-direction: column; gap: 20px; width: 100%;">
                    <!-- Step Progress Header -->
                    <div style="display: flex; align-items: center; justify-content: space-between; position: relative;">
                        ${steps.map((step, idx) => {
                            const isDone = completedSteps.has(idx);
                            const isActive = idx === currentStep;
                            return html`
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 6px; z-index: 2; flex: 1;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem; background: ${isActive ? 'var(--sppux-primary, #6366f1)' : isDone ? '#22c55e' : 'rgba(255,255,255,0.08)'}; color: ${isActive || isDone ? '#fff' : 'var(--sppux-text-dim, #94a3b8)'}; box-shadow: ${isActive ? '0 0 12px var(--sppux-primary-glow, rgba(99,102,241,0.5))' : 'none'};">
                                        ${isDone ? '✓' : idx + 1}
                                    </div>
                                    <span style="font-size: 0.75rem; font-weight: 500; color: ${isActive ? 'var(--sppux-primary, #6366f1)' : 'var(--sppux-text, #f8fafc)'}; text-align: center;">
                                        ${step.title}
                                    </span>
                                </div>
                            `;
                        })}
                    </div>

                    <!-- Step Content Body -->
                    <div class="glass-panel" style="padding: 20px; border-radius: var(--sppux-radius-md, 8px); background: var(--sppux-panel, rgba(15,23,42,0.85)); min-height: 200px;">
                        ${typeof activeStep?.content === 'function' ? activeStep.content() : (activeStep?.content || Fragment)}
                    </div>

                    <!-- Navigation Footer -->
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <button type="button" class="btn secondary-btn" ?disabled=${currentStep === 0} @click=${() => this.prev()}>
                            ← Back
                        </button>
                        ${isLast ? html`
                            <button type="button" class="btn primary-btn" @click=${() => this.complete()}>
                                Complete & Submit ✓
                            </button>
                        ` : html`
                            <button type="button" class="btn primary-btn" @click=${() => this.next()}>
                                Next →
                            </button>
                        `}
                    </div>
                </div>
            `;
        }
    };

    /**
     * SPPUX.Breadcrumb - Navigation Trail Component
     */
    SPPUX.Breadcrumb = class extends BaseComponent {
        render() {
            const items = this.props.items || [];
            const separator = this.props.separator || '›';

            return html`
                <nav class="sppux-breadcrumb" aria-label="Breadcrumb" style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem;">
                    ${items.map((item, idx) => {
                        const isLast = idx === items.length - 1;
                        return html`
                            <div style="display: flex; align-items: center; gap: 8px;">
                                ${item.icon ? html`<span>${item.icon}</span>` : Fragment}
                                ${isLast || !item.href ? html`
                                    <span style="color: var(--sppux-text, #f8fafc); font-weight: 500;">${item.label}</span>
                                ` : html`
                                    <a href="${item.href}" data-spp-route="true" style="color: var(--sppux-text-dim, #94a3b8); text-decoration: none; transition: color 0.15s;"
                                        @mouseenter=${(e) => e.target.style.color = 'var(--sppux-primary, #6366f1)'}
                                        @mouseleave=${(e) => e.target.style.color = 'var(--sppux-text-dim, #94a3b8)'}>
                                        ${item.label}
                                    </a>
                                `}
                                ${!isLast ? html`<span style="opacity: 0.4; user-select: none;">${separator}</span>` : Fragment}
                            </div>
                        `;
                    })}
                </nav>
            `;
        }
    };
    SPPUX.Breadcrumb.render = (items, separator = '›') => {
        const comp = new SPPUX.Breadcrumb(null, document.createElement('div'), { items, separator });
        return comp.render();
    };

    /**
     * SPPUX.DropdownMenu - Floating Contextual Actions Dropdown
     */
    SPPUX.DropdownMenu = class extends BaseComponent {
        onInit() {
            this.state = { open: false };
        }

        render() {
            const { open } = this.state;
            const items = this.props.items || [];
            const trigger = this.props.trigger || 'Actions ▼';

            return html`
                <div class="sppux-dropdown-container" style="position: relative; display: inline-block;">
                    <div @click=${() => this.setState({ open: !open })}>
                        ${typeof trigger === 'string' ? html`
                            <button type="button" class="btn secondary-btn btn-sm" style="display: flex; align-items: center; gap: 6px;">
                                <span>${trigger}</span>
                            </button>
                        ` : trigger}
                    </div>

                    ${open ? html`
                        <div class="glass-panel sppux-dropdown-menu" style="position: absolute; top: calc(100% + 4px); right: 0; min-width: 180px; z-index: 1000; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); padding: 4px; background: var(--sppux-panel, #0f172a); display: flex; flex-direction: column; gap: 2px;">
                            ${items.map(item => {
                                if (item.divider) {
                                    return html`<div style="height: 1px; background: var(--sppux-glass-border, rgba(255,255,255,0.08)); margin: 4px 0;"></div>`;
                                }
                                return html`
                                    <button type="button" class="sppux-dropdown-item" style="display: flex; align-items: center; gap: 8px; width: 100%; padding: 7px 10px; border: none; background: transparent; color: ${item.danger ? '#ef4444' : 'var(--sppux-text, #f8fafc)'}; border-radius: 4px; font-size: 0.85rem; cursor: pointer; text-align: left; transition: background 0.15s;"
                                        @mouseenter=${(e) => e.target.style.background = item.danger ? 'rgba(239,68,68,0.15)' : 'rgba(255,255,255,0.06)'}
                                        @mouseleave=${(e) => e.target.style.background = 'transparent'}
                                        @click=${() => {
                                            this.setState({ open: false });
                                            if (typeof item.action === 'function') item.action();
                                            else if (item.href) window.location.href = item.href;
                                        }}>
                                        ${item.icon ? html`<span>${item.icon}</span>` : Fragment}
                                        <span style="flex: 1;">${item.label}</span>
                                    </button>
                                `;
                            })}
                        </div>
                    ` : Fragment}
                </div>
            `;
        }
    };

    /**
     * SPPUX.TreeView - Expandable/Collapsible Tree View
     */
    SPPUX.TreeView = class extends BaseComponent {
        onInit() {
            this.state = {
                expanded: new Set(this.props.defaultExpanded || []),
                selected: this.props.selected || null,
                filterText: ''
            };
        }

        toggleNode(id) {
            const exp = new Set(this.state.expanded);
            if (exp.has(id)) exp.delete(id);
            else exp.add(id);
            this.setState({ expanded: exp });
        }

        selectNode(node) {
            this.setState({ selected: node.id });
            if (typeof this.props.onSelect === 'function') {
                this.props.onSelect(node);
            }
        }

        renderNode(node, depth = 0) {
            const hasChildren = Array.isArray(node.children) && node.children.length > 0;
            const isExpanded = this.state.expanded.has(node.id);
            const isSelected = this.state.selected === node.id;
            const matchesFilter = !this.state.filterText || String(node.label || '').toLowerCase().includes(this.state.filterText.toLowerCase());

            if (!matchesFilter && !hasChildren) return Fragment;

            return html`
                <div class="sppux-tree-node-group" style="padding-left: ${depth * 18}px;">
                    <div class="sppux-tree-node ${isSelected ? 'selected' : ''}"
                        style="display: flex; align-items: center; gap: 6px; padding: 5px 8px; border-radius: 6px; cursor: pointer; user-select: none; font-size: 0.9rem; transition: background 0.15s; background: ${isSelected ? 'rgba(99,102,241,0.2)' : 'transparent'}; color: ${isSelected ? '#818cf8' : 'inherit'};"
                        @mouseenter=${(e) => { if (!isSelected) e.currentTarget.style.background = 'rgba(255,255,255,0.05)'; }}
                        @mouseleave=${(e) => { if (!isSelected) e.currentTarget.style.background = 'transparent'; }}
                        @click=${() => {
                            if (hasChildren) this.toggleNode(node.id);
                            this.selectNode(node);
                        }}>
                        <span style="display: inline-block; width: 14px; text-align: center; font-size: 0.75rem; color: var(--sppux-text-dim); transition: transform 0.2s; transform: ${isExpanded ? 'rotate(90deg)' : 'rotate(0deg)'};">
                            ${hasChildren ? '▶' : '•'}
                        </span>
                        <span>${node.icon || (hasChildren ? '📁' : '📄')}</span>
                        <span style="flex: 1; font-weight: ${isSelected ? '600' : 'normal'};">${node.label}</span>
                        ${node.badge ? html`<span class="sppux-badge" style="font-size: 0.7rem; padding: 1px 6px; border-radius: 9999px; background: rgba(255,255,255,0.1);">${node.badge}</span>` : Fragment}
                    </div>
                    ${hasChildren && isExpanded ? html`
                        <div class="sppux-tree-children">
                            ${node.children.map(child => this.renderNode(child, depth + 1))}
                        </div>
                    ` : Fragment}
                </div>
            `;
        }

        render() {
            const data = Array.isArray(this.props.data) ? this.props.data : [];
            const searchable = this.props.searchable !== false;

            return html`
                <div class="glass-panel sppux-tree-container" style="padding: 12px; border-radius: 10px; border: 1px solid var(--sppux-glass-border); background: var(--sppux-panel, #0f172a);">
                    ${searchable ? html`
                        <div style="margin-bottom: 8px;">
                            <input type="text" placeholder="Filter tree..." class="sppux-input"
                                style="width: 100%; padding: 6px 10px; border-radius: 6px; border: 1px solid var(--sppux-glass-border); background: rgba(0,0,0,0.2); color: inherit; font-size: 0.85rem;"
                                value="${this.state.filterText}"
                                @input=${(e) => this.setState({ filterText: e.target.value })} />
                        </div>
                    ` : Fragment}
                    <div class="sppux-tree-root" style="display: flex; flex-direction: column; gap: 2px;">
                        ${data.length ? data.map(node => this.renderNode(node, 0)) : html`<div style="padding: 10px; color: var(--sppux-text-dim); font-size: 0.85rem; text-align: center;">No items found</div>`}
                    </div>
                </div>
            `;
        }
    };

    /**
     * SPPUX.KanbanBoard - Multi-Column Task Board
     */
    SPPUX.KanbanBoard = class extends BaseComponent {
        onInit() {
            this.state = { columns: this.props.columns || [] };
        }

        moveCard(cardId, fromColIdx, direction) {
            const cols = JSON.parse(JSON.stringify(this.state.columns));
            const toColIdx = fromColIdx + direction;
            if (toColIdx < 0 || toColIdx >= cols.length) return;

            const cardIdx = cols[fromColIdx].cards.findIndex(c => c.id === cardId);
            if (cardIdx === -1) return;

            const [card] = cols[fromColIdx].cards.splice(cardIdx, 1);
            cols[toColIdx].cards.push(card);
            this.setState({ columns: cols });

            if (typeof this.props.onCardMove === 'function') {
                this.props.onCardMove(cardId, cols[fromColIdx].id, cols[toColIdx].id);
            }
        }

        render() {
            const columns = this.state.columns || [];

            return html`
                <div class="sppux-kanban-board" style="display: flex; gap: 16px; overflow-x: auto; padding: 4px 0;">
                    ${columns.map((col, colIdx) => html`
                        <div class="glass-panel sppux-kanban-col" style="flex: 0 0 280px; display: flex; flex-direction: column; border-radius: 10px; border: 1px solid var(--sppux-glass-border); background: var(--sppux-panel, #0f172a); max-height: 600px;">
                            <div style="padding: 12px 14px; border-bottom: 1px solid var(--sppux-glass-border); display: flex; align-items: center; justify-content: space-between;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="width: 8px; height: 8px; border-radius: 50%; background: ${col.color || '#6366f1'};"></span>
                                    <strong style="font-size: 0.95rem;">${col.title}</strong>
                                </div>
                                <span style="font-size: 0.75rem; background: rgba(255,255,255,0.1); padding: 2px 8px; border-radius: 9999px; font-weight: 600;">
                                    ${(col.cards || []).length}
                                </span>
                            </div>
                            <div style="flex: 1; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 10px;">
                                ${(col.cards || []).map(card => html`
                                    <div class="glass-panel sppux-kanban-card" style="padding: 10px 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.03); display: flex; flex-direction: column; gap: 6px;">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 6px;">
                                            <span style="font-weight: 600; font-size: 0.9rem;">${card.title}</span>
                                            ${card.priority ? html`
                                                <span style="font-size: 0.7rem; padding: 1px 6px; border-radius: 4px; font-weight: bold; background: ${card.priority === 'high' ? 'rgba(239,68,68,0.2)' : (card.priority === 'medium' ? 'rgba(245,158,11,0.2)' : 'rgba(59,130,246,0.2)')}; color: ${card.priority === 'high' ? '#ef4444' : (card.priority === 'medium' ? '#f59e0b' : '#3b82f6')};">
                                                    ${card.priority.toUpperCase()}
                                                </span>
                                            ` : Fragment}
                                        </div>
                                        ${card.description ? html`<div style="font-size: 0.8rem; color: var(--sppux-text-dim); line-height: 1.3;">${card.description}</div>` : Fragment}
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px; font-size: 0.75rem;">
                                            <span style="color: var(--sppux-text-dim);">${card.tag || card.assignee || ''}</span>
                                            <div style="display: flex; gap: 4px;">
                                                ${colIdx > 0 ? html`<button type="button" style="background: rgba(255,255,255,0.08); border: none; color: inherit; padding: 2px 6px; border-radius: 4px; cursor: pointer;" @click=${() => this.moveCard(card.id, colIdx, -1)}>←</button>` : Fragment}
                                                ${colIdx < columns.length - 1 ? html`<button type="button" style="background: rgba(255,255,255,0.08); border: none; color: inherit; padding: 2px 6px; border-radius: 4px; cursor: pointer;" @click=${() => this.moveCard(card.id, colIdx, 1)}>→</button>` : Fragment}
                                            </div>
                                        </div>
                                    </div>
                                `)}
                            </div>
                        </div>
                    `)}
                </div>
            `;
        }
    };

    /**
     * SPPUX.Accordion - Collapsible Multi/Single Panel Accordion
     */
    SPPUX.Accordion = class extends BaseComponent {
        onInit() {
            const initial = new Set();
            (this.props.items || []).forEach((item, idx) => {
                if (item.open) initial.add(item.id || String(idx));
            });
            this.state = { openItems: initial };
        }

        toggle(id) {
            const openItems = new Set(this.props.multiple ? this.state.openItems : []);
            if (this.state.openItems.has(id)) {
                openItems.delete(id);
            } else {
                openItems.add(id);
            }
            this.setState({ openItems });
        }

        render() {
            const items = this.props.items || [];
            return html`
                <div class="sppux-accordion" style="display: flex; flex-direction: column; gap: 8px; width: 100%;">
                    ${items.map((item, idx) => {
                        const id = item.id || String(idx);
                        const isOpen = this.state.openItems.has(id);
                        return html`
                            <div class="glass-panel sppux-accordion-item" style="border-radius: 8px; border: 1px solid var(--sppux-glass-border); background: var(--sppux-panel, #0f172a); overflow: hidden;">
                                <div class="sppux-accordion-header" style="padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; transition: background 0.15s;"
                                    @click=${() => this.toggle(id)}
                                    @mouseenter=${(e) => e.currentTarget.style.background = 'rgba(255,255,255,0.03)'}
                                    @mouseleave=${(e) => e.currentTarget.style.background = 'transparent'}>
                                    <div style="display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 0.95rem;">
                                        ${item.icon ? html`<span>${item.icon}</span>` : Fragment}
                                        <span>${item.title}</span>
                                        ${item.badge ? html`<span class="sppux-badge" style="font-size: 0.75rem; padding: 2px 8px; border-radius: 9999px; background: rgba(99,102,241,0.2); color: #818cf8;">${item.badge}</span>` : Fragment}
                                    </div>
                                    <span style="font-size: 0.8rem; color: var(--sppux-text-dim); transition: transform 0.2s; transform: ${isOpen ? 'rotate(180deg)' : 'rotate(0deg)'};">▼</span>
                                </div>
                                ${isOpen ? html`
                                    <div class="sppux-accordion-body" style="padding: 14px 16px; border-top: 1px solid var(--sppux-glass-border); font-size: 0.9rem; line-height: 1.5; color: var(--sppux-text-dim);">
                                        ${item.content}
                                    </div>
                                ` : Fragment}
                            </div>
                        `;
                    })}
                </div>
            `;
        }
    };

    /**
     * SPPUX.Card - Modern Glassmorphism Card
     */
    SPPUX.Card = class extends BaseComponent {
        render() {
            const { title, subtitle, badge, badgeVariant, image, content, text, actions } = this.props;
            const bodyContent = content || text || this.container.innerHTML;
            const actionList = Array.isArray(actions) ? actions : [];

            return html`
                <div class="glass-panel sppux-card" style="border-radius: 12px; border: 1px solid var(--sppux-glass-border); background: var(--sppux-panel, #0f172a); overflow: hidden; display: flex; flex-direction: column;">
                    ${image ? html`<img src="${image}" alt="${title || ''}" style="width: 100%; height: 160px; object-fit: cover; border-bottom: 1px solid var(--sppux-glass-border);" />` : Fragment}
                    <div style="padding: 16px; display: flex; flex-direction: column; gap: 8px; flex: 1;">
                        ${(title || badge) ? html`
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                                <div>
                                    ${title ? html`<h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">${title}</h3>` : Fragment}
                                    ${subtitle ? html`<p style="margin: 2px 0 0 0; font-size: 0.85rem; color: var(--sppux-text-dim);">${subtitle}</p>` : Fragment}
                                </div>
                                ${badge ? html`<span class="sppux-badge" style="font-size: 0.75rem; padding: 2px 8px; border-radius: 9999px; background: ${badgeVariant === 'success' ? 'rgba(16,185,129,0.2)' : 'rgba(99,102,241,0.2)'}; color: ${badgeVariant === 'success' ? '#10b981' : '#818cf8'};">${badge}</span>` : Fragment}
                            </div>
                        ` : Fragment}
                        ${bodyContent ? html`<div style="font-size: 0.9rem; line-height: 1.5; color: var(--sppux-text, #f8fafc);">${bodyContent}</div>` : Fragment}
                    </div>
                    ${actionList.length ? html`
                        <div style="padding: 10px 16px; border-top: 1px solid var(--sppux-glass-border); background: rgba(0,0,0,0.15); display: flex; justify-content: flex-end; gap: 8px;">
                            ${actionList.map(a => html`
                                <button type="button" class="sppux-btn"
                                    style="padding: 6px 14px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: 1px solid var(--sppux-glass-border); background: ${a.variant === 'primary' ? 'var(--sppux-primary, #6366f1)' : 'rgba(255,255,255,0.08)'}; color: white;"
                                    @click=${() => { if (typeof a.action === 'function') a.action(); else if (a.href) window.location.href = a.href; }}>
                                    ${a.label}
                                </button>
                            `)}
                        </div>
                    ` : Fragment}
                </div>
            `;
        }
    };

    /**
     * SPPUX.ListView - Searchable Structured Item List
     */
    SPPUX.ListView = class extends BaseComponent {
        onInit() {
            this.state = {
                filterText: '',
                page: 1,
                pageSize: this.props.pageSize || 10
            };
        }

        render() {
            const rawItems = Array.isArray(this.props.items) ? this.props.items : [];
            const term = this.state.filterText.toLowerCase();
            const filtered = term ? rawItems.filter(i => {
                return (i.title && i.title.toLowerCase().includes(term)) ||
                       (i.description && i.description.toLowerCase().includes(term)) ||
                       (i.subtitle && i.subtitle.toLowerCase().includes(term));
            }) : rawItems;

            const startIndex = (this.state.page - 1) * this.state.pageSize;
            const visible = filtered.slice(startIndex, startIndex + this.state.pageSize);
            const totalPages = Math.max(1, Math.ceil(filtered.length / this.state.pageSize));

            return html`
                <div class="glass-panel sppux-list-view" style="border-radius: 10px; border: 1px solid var(--sppux-glass-border); background: var(--sppux-panel, #0f172a); overflow: hidden;">
                    ${this.props.searchable !== false ? html`
                        <div style="padding: 12px; border-bottom: 1px solid var(--sppux-glass-border);">
                            <input type="text" placeholder="Search list..." class="sppux-input"
                                style="width: 100%; padding: 7px 12px; border-radius: 6px; border: 1px solid var(--sppux-glass-border); background: rgba(0,0,0,0.2); color: inherit; font-size: 0.85rem;"
                                value="${this.state.filterText}"
                                @input=${(e) => this.setState({ filterText: e.target.value, page: 1 })} />
                        </div>
                    ` : Fragment}
                    <div style="display: flex; flex-direction: column;">
                        ${visible.length ? visible.map(item => html`
                            <div class="sppux-list-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.05); transition: background 0.15s;"
                                @mouseenter=${(e) => e.currentTarget.style.background = 'rgba(255,255,255,0.03)'}
                                @mouseleave=${(e) => e.currentTarget.style.background = 'transparent'}>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    ${item.avatar ? html`<img src="${item.avatar}" alt="" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover;" />` : Fragment}
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.95rem;">${item.title}</div>
                                        ${item.subtitle ? html`<div style="font-size: 0.8rem; color: var(--sppux-text-dim);">${item.subtitle}</div>` : Fragment}
                                        ${item.description ? html`<div style="font-size: 0.85rem; color: var(--sppux-text); margin-top: 2px;">${item.description}</div>` : Fragment}
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    ${item.badge ? html`<span class="sppux-badge" style="font-size: 0.75rem; padding: 2px 8px; border-radius: 9999px; background: rgba(99,102,241,0.2); color: #818cf8;">${item.badge}</span>` : Fragment}
                                    ${item.timestamp ? html`<span style="font-size: 0.8rem; color: var(--sppux-text-dim);">${item.timestamp}</span>` : Fragment}
                                </div>
                            </div>
                        `) : html`<div style="padding: 24px; text-align: center; color: var(--sppux-text-dim); font-size: 0.9rem;">No items matching criteria</div>`}
                    </div>
                    ${totalPages > 1 ? html`
                        <div style="padding: 10px 16px; display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.15); border-top: 1px solid var(--sppux-glass-border); font-size: 0.85rem;">
                            <span>Page ${this.state.page} of ${totalPages}</span>
                            <div style="display: flex; gap: 6px;">
                                <button type="button" style="padding: 4px 10px; border-radius: 4px; border: 1px solid var(--sppux-glass-border); background: transparent; color: inherit; cursor: pointer;"
                                    disabled=${this.state.page <= 1}
                                    @click=${() => this.setState({ page: this.state.page - 1 })}>Prev</button>
                                <button type="button" style="padding: 4px 10px; border-radius: 4px; border: 1px solid var(--sppux-glass-border); background: transparent; color: inherit; cursor: pointer;"
                                    disabled=${this.state.page >= totalPages}
                                    @click=${() => this.setState({ page: this.state.page + 1 })}>Next</button>
                            </div>
                        </div>
                    ` : Fragment}
                </div>
            `;
        }
    };

    /**
     * SPPUX.DescriptionList - Record Detail / Key-Value Inspector
     */
    SPPUX.DescriptionList = class extends BaseComponent {
        copyValue(val) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(String(val));
                SPPUX.notify('Copied to clipboard', 'info');
            }
        }

        render() {
            const items = Array.isArray(this.props.items) ? this.props.items : [];
            const cols = this.props.columns || 2;

            return html`
                <div class="glass-panel sppux-description-list"
                    style="display: grid; grid-template-columns: repeat(${cols}, 1fr); gap: 16px; padding: 16px; border-radius: 10px; border: 1px solid var(--sppux-glass-border); background: var(--sppux-panel, #0f172a);">
                    ${items.map(item => html`
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--sppux-text-dim); font-weight: 600;">
                                ${item.label}
                            </span>
                            <div style="display: flex; align-items: center; gap: 6px; font-size: 0.95rem; font-weight: 500;">
                                <span>${item.value !== undefined ? item.value : '—'}</span>
                                ${item.badge ? html`<span class="sppux-badge" style="font-size: 0.7rem; padding: 1px 6px; border-radius: 9999px; background: rgba(99,102,241,0.2); color: #818cf8;">${item.badge}</span>` : Fragment}
                                ${item.copyable ? html`
                                    <button type="button" title="Copy value" style="background: transparent; border: none; cursor: pointer; color: var(--sppux-text-dim); font-size: 0.85rem; padding: 0 4px;"
                                        @click=${() => this.copyValue(item.value)}>📋</button>
                                ` : Fragment}
                            </div>
                        </div>
                    `)}
                </div>
            `;
        }
    };

    /**
     * SPPUX.Avatar - User Avatar & Stacked Avatar Group
     */
    SPPUX.Avatar = class extends BaseComponent {
        static getInitials(name = '') {
            return name.split(' ').map(n => n[0]).filter(Boolean).slice(0, 2).join('').toUpperCase() || '?';
        }

        static renderAvatar(item, size = 36) {
            const name = item.name || item.title || '';
            const initials = SPPUX.Avatar.getInitials(name);
            const statusColor = { online: '#10b981', busy: '#ef4444', away: '#f59e0b', offline: '#64748b' }[item.status];

            return html`
                <div class="sppux-avatar-item" style="position: relative; width: ${size}px; height: ${size}px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: ${item.src ? 'transparent' : 'linear-gradient(135deg, #6366f1, #a855f7)'}; color: white; font-weight: bold; font-size: ${size * 0.4}px; border: 2px solid var(--sppux-panel, #0f172a); overflow: visible; user-select: none;">
                    ${item.src ? html`<img src="${item.src}" alt="${name}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;" />` : initials}
                    ${statusColor ? html`
                        <span style="position: absolute; bottom: 0; right: 0; width: ${Math.max(8, size * 0.25)}px; height: ${Math.max(8, size * 0.25)}px; border-radius: 50%; background: ${statusColor}; border: 1.5px solid var(--sppux-panel, #0f172a);"></span>
                    ` : Fragment}
                </div>
            `;
        }

        render() {
            const sizeMap = { sm: 28, md: 36, lg: 48, xl: 64 };
            const size = typeof this.props.size === 'number' ? this.props.size : (sizeMap[this.props.size] || 36);

            if (Array.isArray(this.props.group)) {
                const max = this.props.max || 4;
                const visible = this.props.group.slice(0, max);
                const extra = this.props.group.length - max;

                return html`
                    <div class="sppux-avatar-group" style="display: inline-flex; align-items: center; margin-left: ${size * 0.2}px;">
                        ${visible.map(u => html`
                            <div style="margin-left: -${size * 0.25}px;">
                                ${SPPUX.Avatar.renderAvatar(u, size)}
                            </div>
                        `)}
                        ${extra > 0 ? html`
                            <div style="margin-left: -${size * 0.25}px; width: ${size}px; height: ${size}px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: #334155; color: white; font-size: ${size * 0.35}px; font-weight: 600; border: 2px solid var(--sppux-panel, #0f172a);">
                                +${extra}
                            </div>
                        ` : Fragment}
                    </div>
                `;
            }

            return SPPUX.Avatar.renderAvatar(this.props, size);
        }
    };

    /**
     * SPPUX.Kbd - Keyboard Shortcut Cap Badge
     */
    SPPUX.Kbd = class extends BaseComponent {
        render() {
            const rawKeys = this.props.keys || this.props.text || this.container.innerHTML || '';
            const keyList = String(rawKeys).split('+').map(k => k.trim());

            return html`
                <span class="sppux-kbd-wrap" style="display: inline-flex; align-items: center; gap: 4px;">
                    ${keyList.map(k => html`
                        <kbd style="display: inline-block; padding: 2px 6px; font-family: ui-monospace, monospace; font-size: 0.78rem; font-weight: 600; color: var(--sppux-text, #f8fafc); background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); border-bottom-width: 2px; border-radius: 5px; box-shadow: 0 1px 2px rgba(0,0,0,0.4); text-transform: uppercase;">${k}</kbd>
                    `)}
                </span>
            `;
        }
    };

    /**
     * SPPUX.Rating - Interactive Star/Emoji Rating
     */
    SPPUX.Rating = class extends BaseComponent {
        onInit() {
            this.state = {
                val: this.props.value || 0,
                hover: 0
            };
        }

        setRating(val) {
            if (this.props.readOnly) return;
            this.setState({ val });
            if (typeof this.props.onChange === 'function') {
                this.props.onChange(val);
            }
        }

        render() {
            const max = this.props.max || 5;
            const current = this.state.hover || this.state.val;
            const isReadOnly = !!this.props.readOnly;
            const stars = [];

            for (let i = 1; i <= max; i++) {
                const filled = i <= current;
                stars.push(html`
                    <span style="cursor: ${isReadOnly ? 'default' : 'pointer'}; font-size: ${this.props.size || '1.3rem'}; color: ${filled ? '#fbbf24' : 'rgba(255,255,255,0.2)'}; transition: transform 0.1s;"
                        @mouseenter=${() => { if (!isReadOnly) this.setState({ hover: i }); }}
                        @mouseleave=${() => { if (!isReadOnly) this.setState({ hover: 0 }); }}
                        @click=${() => this.setRating(i)}>
                        ★
                    </span>
                `);
            }

            return html`
                <div class="sppux-rating-widget" style="display: inline-flex; align-items: center; gap: 4px;">
                    ${stars}
                    ${this.props.showScore !== false ? html`
                        <span style="font-size: 0.85rem; color: var(--sppux-text-dim); margin-left: 6px; font-weight: 600;">
                            ${this.state.val} / ${max}
                        </span>
                    ` : Fragment}
                </div>
            `;
        }
    };

    /**
     * SPPUX.Slider - Numeric Range Slider
     */
    SPPUX.Slider = class extends BaseComponent {
        onInit() {
            this.state = {
                val: this.props.value !== undefined ? this.props.value : (this.props.min || 0)
            };
        }

        updateValue(v) {
            const num = Number(v);
            this.setState({ val: num });
            if (typeof this.props.onChange === 'function') {
                this.props.onChange(num);
            }
        }

        render() {
            const min = this.props.min || 0;
            const max = this.props.max || 100;
            const step = this.props.step || 1;
            const unit = this.props.unit || '';

            return html`
                <div class="sppux-slider-container" style="display: flex; flex-direction: column; gap: 6px; width: 100%;">
                    ${this.props.label ? html`
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                            <span style="font-weight: 500;">${this.props.label}</span>
                            <span style="font-weight: 600; color: #818cf8; background: rgba(99,102,241,0.15); padding: 1px 8px; border-radius: 9999px;">${this.state.val}${unit}</span>
                        </div>
                    ` : Fragment}
                    <input type="range" min="${min}" max="${max}" step="${step}" value="${this.state.val}"
                        style="width: 100%; accent-color: var(--sppux-primary, #6366f1); cursor: pointer;"
                        @input=${(e) => this.updateValue(e.target.value)} />
                    <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--sppux-text-dim);">
                        <span>${min}${unit}</span>
                        <span>${max}${unit}</span>
                    </div>
                </div>
            `;
        }
    };

    /**
     * SPPUX.ColorPicker - Swatch Palette & Hex Color Picker
     */
    SPPUX.ColorPicker = class extends BaseComponent {
        onInit() {
            this.state = { color: this.props.value || '#6366f1' };
        }

        setColor(color) {
            this.setState({ color });
            if (typeof this.props.onChange === 'function') {
                this.props.onChange(color);
            }
        }

        render() {
            const presets = this.props.presets || ['#ef4444', '#f97316', '#f59e0b', '#10b981', '#06b6d4', '#3b82f6', '#6366f1', '#a855f7', '#ec4899', '#64748b'];

            return html`
                <div class="sppux-color-picker" style="display: inline-flex; align-items: center; gap: 8px; background: rgba(0,0,0,0.2); padding: 6px 10px; border-radius: 8px; border: 1px solid var(--sppux-glass-border);">
                    <input type="color" value="${this.state.color}"
                        style="width: 28px; height: 28px; border: none; border-radius: 6px; cursor: pointer; background: transparent; padding: 0;"
                        @input=${(e) => this.setColor(e.target.value)} />
                    <div style="display: flex; gap: 4px;">
                        ${presets.map(c => html`
                            <span style="width: 18px; height: 18px; border-radius: 4px; background: ${c}; cursor: pointer; border: 1px solid rgba(255,255,255,0.2); transition: transform 0.1s;"
                                @click=${() => this.setColor(c)}
                                @mouseenter=${(e) => e.target.style.transform = 'scale(1.2)'}
                                @mouseleave=${(e) => e.target.style.transform = 'scale(1)'}></span>
                        `)}
                    </div>
                    <span style="font-family: monospace; font-size: 0.8rem; font-weight: 600; color: var(--sppux-text-dim); margin-left: 4px;">${this.state.color}</span>
                </div>
            `;
        }
    };

    /**
     * SPPUX.Switch - Animated Toggle Switch
     */
    SPPUX.Switch = class extends BaseComponent {
        onInit() {
            this.state = { checked: !!this.props.checked };
        }

        toggle() {
            if (this.props.disabled) return;
            const next = !this.state.checked;
            this.setState({ checked: next });
            if (typeof this.props.onChange === 'function') {
                this.props.onChange(next);
            }
        }

        render() {
            const { checked } = this.state;
            const { label, description, disabled } = this.props;

            return html`
                <div class="sppux-switch-wrapper" style="display: inline-flex; align-items: center; gap: 10px; cursor: ${disabled ? 'not-allowed' : 'pointer'}; opacity: ${disabled ? 0.6 : 1};"
                    @click=${() => this.toggle()}>
                    <div style="position: relative; width: 42px; height: 24px; border-radius: 9999px; background: ${checked ? 'var(--sppux-primary, #6366f1)' : 'rgba(255,255,255,0.15)'}; transition: background 0.2s;">
                        <span style="position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; border-radius: 50%; background: white; transition: transform 0.2s; transform: ${checked ? 'translateX(18px)' : 'translateX(0)'}; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></span>
                    </div>
                    ${label ? html`
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-size: 0.9rem; font-weight: 500;">${label}</span>
                            ${description ? html`<span style="font-size: 0.75rem; color: var(--sppux-text-dim);">${description}</span>` : Fragment}
                        </div>
                    ` : Fragment}
                </div>
            `;
        }
    };

    /**
     * SPPUX.MarkdownEditor - Live Markdown Editor with Toolbar & Preview
     */
    SPPUX.MarkdownEditor = class extends BaseComponent {
        onInit() {
            this.state = {
                content: this.props.value || this.props.code || '',
                mode: 'write' // 'write' | 'preview' | 'split'
            };
        }

        insertTag(before, after = '') {
            const textarea = this.container.querySelector('textarea');
            if (!textarea) return;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            const sel = text.substring(start, end);
            const replacement = before + sel + after;
            const updated = text.substring(0, start) + replacement + text.substring(end);
            this.setState({ content: updated });
            if (typeof this.props.onChange === 'function') this.props.onChange(updated);
        }

        static renderSimpleMarkdown(text = '') {
            let htmlStr = text
                .replace(/^### (.*$)/gim, '<h3 style="margin: 6px 0;">$1</h3>')
                .replace(/^## (.*$)/gim, '<h2 style="margin: 8px 0;">$1</h2>')
                .replace(/^# (.*$)/gim, '<h1 style="margin: 10px 0;">$1</h1>')
                .replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/gim, '<em>$1</em>')
                .replace(/`([^`]+)`/gim, '<code style="background: rgba(255,255,255,0.1); padding: 2px 4px; border-radius: 4px;">$1</code>')
                .replace(/\n$/gim, '<br />');
            return htmlStr;
        }

        render() {
            const { content, mode } = this.state;
            const title = this.props.title || 'Markdown Editor';

            return html`
                <div class="glass-panel sppux-markdown-editor" style="border-radius: 10px; border: 1px solid var(--sppux-glass-border); background: var(--sppux-panel, #0f172a); overflow: hidden; display: flex; flex-direction: column;">
                    <div style="padding: 8px 12px; border-bottom: 1px solid var(--sppux-glass-border); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.15);">
                        <span style="font-weight: 600; font-size: 0.85rem;">${title}</span>
                        <div style="display: flex; gap: 4px;">
                            <button type="button" style="background: rgba(255,255,255,0.06); border: none; color: inherit; padding: 3px 8px; border-radius: 4px; font-weight: bold; cursor: pointer;" @click=${() => this.insertTag('**', '**')}>B</button>
                            <button type="button" style="background: rgba(255,255,255,0.06); border: none; color: inherit; padding: 3px 8px; border-radius: 4px; font-style: italic; cursor: pointer;" @click=${() => this.insertTag('*', '*')}>I</button>
                            <button type="button" style="background: rgba(255,255,255,0.06); border: none; color: inherit; padding: 3px 8px; border-radius: 4px; cursor: pointer;" @click=${() => this.insertTag('### ')}>H</button>
                            <button type="button" style="background: rgba(255,255,255,0.06); border: none; color: inherit; padding: 3px 8px; border-radius: 4px; cursor: pointer;" @click=${() => this.insertTag('`', '`')}>&lt;/&gt;</button>
                            <span style="width: 1px; height: 16px; background: var(--sppux-glass-border); margin: 0 4px;"></span>
                            <button type="button" style="background: ${mode === 'write' ? 'rgba(99,102,241,0.3)' : 'transparent'}; border: none; color: inherit; padding: 3px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem;" @click=${() => this.setState({ mode: 'write' })}>Write</button>
                            <button type="button" style="background: ${mode === 'preview' ? 'rgba(99,102,241,0.3)' : 'transparent'}; border: none; color: inherit; padding: 3px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem;" @click=${() => this.setState({ mode: 'preview' })}>Preview</button>
                        </div>
                    </div>
                    <div style="padding: 10px;">
                        ${mode === 'write' ? html`
                            <textarea style="width: 100%; min-height: 140px; background: transparent; border: none; color: inherit; font-family: monospace; font-size: 0.9rem; resize: vertical; outline: none;"
                                @input=${(e) => {
                                    this.state.content = e.target.value;
                                    if (typeof this.props.onChange === 'function') this.props.onChange(e.target.value);
                                }}>${content}</textarea>
                        ` : html`
                            <div style="min-height: 140px; font-size: 0.9rem; line-height: 1.5;">
                                ${new SPPUX.TrustedHTML(SPPUX.MarkdownEditor.renderSimpleMarkdown(content))}
                            </div>
                        `}
                    </div>
                </div>
            `;
        }
    };

    /**
     * SPPUX.SignaturePad - Touch and Mouse Electronic Signature Canvas
     */
    SPPUX.SignaturePad = class extends BaseComponent {
        onInit() {
            this.state = { hasSignature: false };
        }

        onMount() {
            const canvas = this.container.querySelector('canvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.strokeStyle = this.props.penColor || '#6366f1';

            let drawing = false;

            const start = (e) => {
                drawing = true;
                ctx.beginPath();
                const rect = canvas.getBoundingClientRect();
                const x = (e.clientX || e.touches?.[0]?.clientX) - rect.left;
                const y = (e.clientY || e.touches?.[0]?.clientY) - rect.top;
                ctx.moveTo(x, y);
            };

            const draw = (e) => {
                if (!drawing) return;
                const rect = canvas.getBoundingClientRect();
                const x = (e.clientX || e.touches?.[0]?.clientX) - rect.left;
                const y = (e.clientY || e.touches?.[0]?.clientY) - rect.top;
                ctx.lineTo(x, y);
                ctx.stroke();
                if (!this.state.hasSignature) this.setState({ hasSignature: true });
            };

            const stop = () => { drawing = false; };

            canvas.addEventListener('mousedown', start);
            canvas.addEventListener('mousemove', draw);
            window.addEventListener('mouseup', stop);
            canvas.addEventListener('touchstart', start);
            canvas.addEventListener('touchmove', draw);
            window.addEventListener('touchend', stop);
        }

        clear() {
            const canvas = this.container.querySelector('canvas');
            if (canvas) {
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                this.setState({ hasSignature: false });
            }
        }

        exportPng() {
            const canvas = this.container.querySelector('canvas');
            if (!canvas) return null;
            const dataUrl = canvas.toDataURL('image/png');
            if (typeof this.props.onSave === 'function') {
                this.props.onSave(dataUrl);
            }
            return dataUrl;
        }

        render() {
            const w = this.props.width || 400;
            const h = this.props.height || 160;

            return html`
                <div class="glass-panel sppux-signature-pad" style="display: inline-flex; flex-direction: column; gap: 8px; padding: 12px; border-radius: 10px; border: 1px solid var(--sppux-glass-border); background: var(--sppux-panel, #0f172a);">
                    <div style="position: relative; width: ${w}px; height: ${h}px; border: 1.5px dashed var(--sppux-glass-border); border-radius: 8px; background: rgba(0,0,0,0.2); overflow: hidden;">
                        <canvas width="${w}" height="${h}" style="width: 100%; height: 100%; cursor: crosshair; touch-action: none;"></canvas>
                        <div style="position: absolute; bottom: 25px; left: 20px; right: 20px; height: 1px; background: rgba(255,255,255,0.1); pointer-events: none;"></div>
                        <span style="position: absolute; bottom: 8px; left: 20px; font-size: 0.75rem; color: var(--sppux-text-dim); pointer-events: none;">Sign on the line above</span>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 8px;">
                        <button type="button" class="sppux-btn" style="padding: 4px 10px; font-size: 0.8rem; border-radius: 4px; border: 1px solid var(--sppux-glass-border); background: transparent; color: inherit; cursor: pointer;"
                            @click=${() => this.clear()}>Clear</button>
                        <button type="button" class="sppux-btn" style="padding: 4px 12px; font-size: 0.8rem; font-weight: 600; border-radius: 4px; border: none; background: var(--sppux-primary, #6366f1); color: white; cursor: pointer;"
                            @click=${() => this.exportPng()}>Save Signature</button>
                    </div>
                </div>
            `;
        }
    };

    /**
     * SPPUX.Pagination - Standalone Numbered Pagination Controller
     */
    SPPUX.Pagination = class extends BaseComponent {
        setPage(p) {
            if (p === this.props.page) return;
            if (typeof this.props.onPageChange === 'function') {
                this.props.onPageChange(p);
            }
        }

        render() {
            const page = Number(this.props.page) || 1;
            const total = Number(this.props.totalPages) || Math.max(1, Math.ceil((this.props.totalItems || 0) / (this.props.pageSize || 10)));
            const pages = [];

            for (let i = 1; i <= total; i++) {
                if (i === 1 || i === total || (i >= page - 2 && i <= page + 2)) {
                    pages.push(i);
                } else if (pages[pages.length - 1] !== '...') {
                    pages.push('...');
                }
            }

            return html`
                <div class="sppux-pagination" style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.85rem;">
                    <button type="button" style="padding: 5px 10px; border-radius: 6px; border: 1px solid var(--sppux-glass-border); background: rgba(255,255,255,0.04); color: inherit; cursor: pointer;"
                        disabled=${page <= 1}
                        @click=${() => this.setPage(page - 1)}>« Prev</button>
                    ${pages.map(p => {
                        if (p === '...') return html`<span style="padding: 0 4px; color: var(--sppux-text-dim);">…</span>`;
                        const active = p === page;
                        return html`
                            <button type="button" style="min-width: 32px; height: 32px; border-radius: 6px; border: 1px solid ${active ? 'var(--sppux-primary, #6366f1)' : 'var(--sppux-glass-border)'}; background: ${active ? 'var(--sppux-primary, #6366f1)' : 'rgba(255,255,255,0.04)'}; color: ${active ? 'white' : 'inherit'}; font-weight: ${active ? 'bold' : 'normal'}; cursor: pointer;"
                                @click=${() => this.setPage(p)}>${p}</button>
                        `;
                    })}
                    <button type="button" style="padding: 5px 10px; border-radius: 6px; border: 1px solid var(--sppux-glass-border); background: rgba(255,255,255,0.04); color: inherit; cursor: pointer;"
                        disabled=${page >= total}
                        @click=${() => this.setPage(page + 1)}>Next »</button>
                </div>
            `;
        }
    };

    /**
     * SPPUX.Stepper - Visual Process Progression Stepper
     */
    SPPUX.Stepper = class extends BaseComponent {
        render() {
            const steps = Array.isArray(this.props.steps) ? this.props.steps : [];
            const current = Number(this.props.current) || 1;

            return html`
                <div class="sppux-stepper" style="display: flex; align-items: center; width: 100%; gap: 12px; padding: 8px 0;">
                    ${steps.map((step, idx) => {
                        const stepNum = idx + 1;
                        const isCompleted = stepNum < current;
                        const isActive = stepNum === current;

                        return html`
                            <div style="display: flex; align-items: center; flex: ${idx < steps.length - 1 ? '1' : '0'};">
                                <div style="display: flex; align-items: center; gap: 8px; cursor: pointer;"
                                    @click=${() => { if (typeof this.props.onStepClick === 'function') this.props.onStepClick(stepNum); }}>
                                    <span style="width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: bold; border: 2px solid ${isCompleted ? '#10b981' : (isActive ? 'var(--sppux-primary, #6366f1)' : 'var(--sppux-glass-border)')}; background: ${isCompleted ? '#10b981' : (isActive ? 'rgba(99,102,241,0.2)' : 'rgba(255,255,255,0.04)')}; color: white;">
                                        ${isCompleted ? '✓' : stepNum}
                                    </span>
                                    <div style="display: flex; flex-direction: column;">
                                        <span style="font-size: 0.85rem; font-weight: ${isActive ? '600' : '500'}; color: ${isActive ? 'white' : 'var(--sppux-text-dim)'};">${step.title || `Step ${stepNum}`}</span>
                                        ${step.description ? html`<span style="font-size: 0.72rem; color: var(--sppux-text-dim);">${step.description}</span>` : Fragment}
                                    </div>
                                </div>
                                ${idx < steps.length - 1 ? html`
                                    <div style="flex: 1; height: 2px; margin: 0 12px; background: ${isCompleted ? '#10b981' : 'var(--sppux-glass-border)'};"></div>
                                ` : Fragment}
                            </div>
                        `;
                    })}
                </div>
            `;
        }
    };

    /**
     * SPPUX.ModalComponent - Accessible Dialog Overlay Web Component
     */
    SPPUX.ModalComponent = class extends BaseComponent {
        static open(title, content, actions = [], context = {}) {
            return SPPUX.Modal.open(title, content, actions, context);
        }

        static close() {
            return SPPUX.Modal.close();
        }

        onInit() {
            this.state = { open: this.props.open !== undefined ? !!this.props.open : true };
        }

        close() {
            this.setState({ open: false });
            if (typeof this.props.onClose === 'function') {
                this.props.onClose();
            }
        }

        confirm() {
            if (typeof this.props.onConfirm === 'function') {
                this.props.onConfirm();
            }
            this.close();
        }

        render() {
            if (!this.state.open) return Fragment;
            const { title, content, confirmText = 'Confirm', cancelText = 'Cancel' } = this.props;

            return html`
                <div class="sppux-modal-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 16px;"
                    @click=${(e) => { if (e.target === e.currentTarget) this.close(); }}>
                    <div class="glass-panel sppux-modal-dialog" style="width: 100%; max-width: 500px; border-radius: 12px; border: 1px solid var(--sppux-glass-border); background: var(--sppux-panel, #0f172a); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.7); overflow: hidden; display: flex; flex-direction: column;">
                        <div style="padding: 14px 18px; border-bottom: 1px solid var(--sppux-glass-border); display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">${title || 'Dialog'}</h3>
                            <button type="button" style="background: transparent; border: none; color: var(--sppux-text-dim); font-size: 1.1rem; cursor: pointer;" @click=${() => this.close()}>✕</button>
                        </div>
                        <div style="padding: 18px; font-size: 0.95rem; line-height: 1.5; color: var(--sppux-text, #f8fafc);">
                            ${content || this.container.innerHTML}
                        </div>
                        <div style="padding: 12px 18px; border-top: 1px solid var(--sppux-glass-border); background: rgba(0,0,0,0.15); display: flex; justify-content: flex-end; gap: 8px;">
                            <button type="button" style="padding: 7px 14px; border-radius: 6px; border: 1px solid var(--sppux-glass-border); background: transparent; color: inherit; cursor: pointer; font-size: 0.85rem;" @click=${() => this.close()}>${cancelText}</button>
                            <button type="button" style="padding: 7px 16px; border-radius: 6px; border: none; background: var(--sppux-primary, #6366f1); color: white; font-weight: 600; cursor: pointer; font-size: 0.85rem;" @click=${() => this.confirm()}>${confirmText}</button>
                        </div>
                    </div>
                </div>
            `;
        }
    };
    if (typeof SPPUX.Modal !== 'undefined') {
        SPPUX.Modal.Component = SPPUX.ModalComponent;
    }

    /**
     * SPPUX.DrawerComponent - Slide-Out Offcanvas Panel Web Component
     */
    SPPUX.DrawerComponent = class extends BaseComponent {
        static open(t, c, s = 'right') {
            return SPPUX.Drawer.open(t, c, s);
        }

        onInit() {
            this.state = { open: this.props.open !== undefined ? !!this.props.open : true };
        }

        close() {
            this.setState({ open: false });
            if (typeof this.props.onClose === 'function') {
                this.props.onClose();
            }
        }

        render() {
            if (!this.state.open) return Fragment;
            const { title, content, width = 380, position = 'right' } = this.props;
            const isRight = position !== 'left';

            return html`
                <div class="sppux-drawer-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 9999;"
                    @click=${(e) => { if (e.target === e.currentTarget) this.close(); }}>
                    <div class="glass-panel sppux-drawer-panel" style="position: absolute; top: 0; bottom: 0; ${isRight ? 'right: 0;' : 'left: 0;'} width: ${width}px; border-${isRight ? 'left' : 'right'}: 1px solid var(--sppux-glass-border); background: var(--sppux-panel, #0f172a); box-shadow: 0 25px 50px rgba(0,0,0,0.8); display: flex; flex-direction: column;">
                        <div style="padding: 16px 20px; border-bottom: 1px solid var(--sppux-glass-border); display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">${title || 'Panel'}</h3>
                            <button type="button" style="background: transparent; border: none; color: var(--sppux-text-dim); font-size: 1.1rem; cursor: pointer;" @click=${() => this.close()}>✕</button>
                        </div>
                        <div style="flex: 1; overflow-y: auto; padding: 20px; font-size: 0.95rem; line-height: 1.5;">
                            ${content || this.container.innerHTML}
                        </div>
                    </div>
                </div>
            `;
        }
    };
    if (typeof SPPUX.Drawer !== 'undefined') {
        SPPUX.Drawer.Component = SPPUX.DrawerComponent;
    }

    /**
     * SPPUX.AlertComponent - Prominent Callout / Notification Banner Web Component
     */
    SPPUX.AlertComponent = class extends BaseComponent {
        onInit() {
            this.state = { dismissed: false };
        }

        static render(message, options = {}) {
            if (typeof SPPUX.Alert !== 'undefined' && typeof SPPUX.Alert.render === 'function') {
                return SPPUX.Alert.render(message, options);
            }
            const variant = options.variant || 'info';
            const colors = {
                info: { bg: 'rgba(59,130,246,0.1)', border: 'rgba(59,130,246,0.3)', text: '#60a5fa', icon: 'ℹ️' },
                success: { bg: 'rgba(16,185,129,0.1)', border: 'rgba(16,185,129,0.3)', text: '#34d399', icon: '✅' },
                warning: { bg: 'rgba(245,158,11,0.1)', border: 'rgba(245,158,11,0.3)', text: '#fbbf24', icon: '⚠️' },
                danger: { bg: 'rgba(239,68,68,0.1)', border: 'rgba(239,68,68,0.3)', text: '#f87171', icon: '🚨' }
            }[variant] || { bg: 'rgba(59,130,246,0.1)', border: 'rgba(59,130,246,0.3)', text: '#60a5fa', icon: 'ℹ️' };

            return html`
                <div class="sppux-alert" style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px; border-radius: 8px; background: ${colors.bg}; border: 1px solid ${colors.border}; color: ${colors.text}; font-size: 0.9rem; line-height: 1.4;">
                    <span style="font-size: 1.1rem;">${options.icon || colors.icon}</span>
                    <div style="flex: 1;">
                        ${options.title ? html`<strong style="display: block; margin-bottom: 2px; font-size: 0.95rem;">${options.title}</strong>` : Fragment}
                        <span>${message}</span>
                    </div>
                </div>
            `;
        }

        render() {
            if (this.state.dismissed) return Fragment;
            const { message, text, title, variant = 'info', icon, dismissible = true } = this.props;
            const content = message || text || this.container.innerHTML;
            const colors = {
                info: { bg: 'rgba(59,130,246,0.1)', border: 'rgba(59,130,246,0.3)', text: '#60a5fa', icon: 'ℹ️' },
                success: { bg: 'rgba(16,185,129,0.1)', border: 'rgba(16,185,129,0.3)', text: '#34d399', icon: '✅' },
                warning: { bg: 'rgba(245,158,11,0.1)', border: 'rgba(245,158,11,0.3)', text: '#fbbf24', icon: '⚠️' },
                danger: { bg: 'rgba(239,68,68,0.1)', border: 'rgba(239,68,68,0.3)', text: '#f87171', icon: '🚨' }
            }[variant] || { bg: 'rgba(59,130,246,0.1)', border: 'rgba(59,130,246,0.3)', text: '#60a5fa', icon: 'ℹ️' };

            return html`
                <div class="sppux-alert" style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px; border-radius: 8px; background: ${colors.bg}; border: 1px solid ${colors.border}; color: ${colors.text}; font-size: 0.9rem; line-height: 1.4;">
                    <span style="font-size: 1.1rem;">${icon || colors.icon}</span>
                    <div style="flex: 1;">
                        ${title ? html`<strong style="display: block; margin-bottom: 2px; font-size: 0.95rem;">${title}</strong>` : Fragment}
                        <div>${content}</div>
                    </div>
                    ${dismissible ? html`
                        <button type="button" style="background: transparent; border: none; color: inherit; cursor: pointer; font-size: 1rem; opacity: 0.7;"
                            @click=${() => this.setState({ dismissed: true })}>✕</button>
                    ` : Fragment}
                </div>
            `;
        }
    };

    /**
     * SPPUX.ProgressBar - Linear Percentage Indicator
     */
    SPPUX.ProgressBar = class extends BaseComponent {
        static render(val, options = {}) {
            const pct = Math.min(100, Math.max(0, Number(val) || 0));
            const h = options.height || 8;
            return html`
                <div class="sppux-progress-track" style="width: 100%; height: ${h}px; border-radius: 9999px; background: rgba(255,255,255,0.1); overflow: hidden;">
                    <div style="width: ${pct}%; height: 100%; border-radius: 9999px; background: ${options.color || 'var(--sppux-primary, #6366f1)'}; transition: width 0.3s ease;"></div>
                </div>
            `;
        }

        render() {
            const pct = Math.min(100, Math.max(0, Number(this.props.value || this.props.percentage || 0)));
            const h = this.props.height || 10;
            const label = this.props.label;

            return html`
                <div class="sppux-progress-container" style="display: flex; flex-direction: column; gap: 6px; width: 100%;">
                    ${label ? html`
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem;">
                            <span>${label}</span>
                            <span style="font-weight: 600;">${pct}%</span>
                        </div>
                    ` : Fragment}
                    <div class="sppux-progress-track" style="width: 100%; height: ${h}px; border-radius: 9999px; background: rgba(255,255,255,0.1); overflow: hidden;">
                        <div style="width: ${pct}%; height: 100%; border-radius: 9999px; background: ${this.props.color || 'linear-gradient(90deg, #6366f1, #818cf8)'}; transition: width 0.3s ease;"></div>
                    </div>
                </div>
            `;
        }
    };

    /**
     * SPPUX.Skeleton - Shimmering Placeholder Loader
     */
    SPPUX.Skeleton = class extends BaseComponent {
        render() {
            const variant = this.props.variant || 'text';
            const count = Number(this.props.count) || 1;
            const shimmerStyle = "background: linear-gradient(90deg, rgba(255,255,255,0.04) 25%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.04) 75%); background-size: 200% 100%; animation: sppux-shimmer 1.5s infinite; border-radius: 6px;";

            if (variant === 'circle') {
                const s = this.props.size || 40;
                return html`<div style="width: ${s}px; height: ${s}px; border-radius: 50%; ${shimmerStyle}"></div>`;
            }

            if (variant === 'card') {
                return html`
                    <div style="padding: 16px; border-radius: 10px; border: 1px solid var(--sppux-glass-border); display: flex; flex-direction: column; gap: 10px; width: 100%;">
                        <div style="height: 120px; ${shimmerStyle}"></div>
                        <div style="height: 18px; width: 60%; ${shimmerStyle}"></div>
                        <div style="height: 14px; width: 85%; ${shimmerStyle}"></div>
                    </div>
                `;
            }

            const items = [];
            for (let i = 0; i < count; i++) {
                items.push(html`
                    <div style="height: ${this.props.height || 16}px; width: ${i === count - 1 && count > 1 ? '65%' : (this.props.width || '100%')}; margin-bottom: 8px; ${shimmerStyle}"></div>
                `);
            }
            return html`<div class="sppux-skeleton-group" style="width: 100%;">${items}</div>`;
        }
    };

    /**
     * SPPUX.QRCode - Declarative Client-Side QR Matrix Generator
     */
    SPPUX.QRCode = class extends BaseComponent {
        render() {
            const data = String(this.props.data || this.props.text || this.props.value || 'https://sppframework.org');
            const size = Number(this.props.size) || 140;

            // Simple deterministic matrix generation for clean zero-dependency SVG visualization
            const cells = 21;
            const cellSize = size / cells;
            const rects = [];

            // Deterministic hash to populate interior data modules
            let hash = 0;
            for (let i = 0; i < data.length; i++) hash = ((hash << 5) - hash) + data.charCodeAt(i);

            for (let r = 0; r < cells; r++) {
                for (let c = 0; c < cells; c++) {
                    // Position Detection Patterns (Top-Left, Top-Right, Bottom-Left)
                    const isFinderTL = (r < 7 && c < 7) && (r === 0 || r === 6 || c === 0 || c === 6 || (r >= 2 && r <= 4 && c >= 2 && c <= 4));
                    const isFinderTR = (r < 7 && c >= cells - 7) && (r === 0 || r === 6 || c === cells - 7 || c === cells - 1 || (r >= 2 && r <= 4 && c >= cells - 5 && c <= cells - 3));
                    const isFinderBL = (r >= cells - 7 && c < 7) && (r === cells - 7 || r === cells - 1 || c === 0 || c === 6 || (r >= cells - 5 && r <= cells - 3 && c >= 2 && c <= 4));
                    
                    const isData = ((hash ^ (r * 31 + c * 17)) & 1) === 1 && !((r < 8 && c < 8) || (r < 8 && c >= cells - 8) || (r >= cells - 8 && c < 8));

                    if (isFinderTL || isFinderTR || isFinderBL || isData) {
                        rects.push(html`<rect x="${c * cellSize}" y="${r * cellSize}" width="${cellSize}" height="${cellSize}" fill="#0f172a" />`);
                    }
                }
            }

            return html`
                <div class="sppux-qrcode-container" style="display: inline-flex; flex-direction: column; align-items: center; gap: 8px; padding: 12px; border-radius: 10px; background: white; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                    <svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
                        ${rects}
                    </svg>
                    ${this.props.title ? html`<span style="color: #334155; font-size: 0.8rem; font-weight: 600;">${this.props.title}</span>` : Fragment}
                </div>
            `;
        }
    };

    // Web Component Registrations
    if (SPPUX.defineElement) {
        class RouterLinkComponent extends BaseComponent {
            render() {
                const path = this.props.to || this.props.href || '#';
                return html`<a href="${path}" data-spp-route="true" class="${this.props.class || ''}">${this.props.text || this.container.innerHTML}</a>`;
            }
        }
        SPPUX.defineElement('spp-router-link', RouterLinkComponent);
        SPPUX.defineElement('spp-table', SPPUX.DataTable);
        SPPUX.defineElement('spp-tabs', SPPUX.Tabs);
        SPPUX.defineElement('spp-daterange', SPPUX.DateRangePicker);
        SPPUX.defineElement('spp-filter-builder', SPPUX.FilterBuilder);
        SPPUX.defineElement('spp-file-upload', SPPUX.FileUpload);
        SPPUX.defineElement('spp-timeline', SPPUX.Timeline);
        SPPUX.defineElement('spp-code-block', SPPUX.CodeBlock);
        SPPUX.defineElement('spp-metric-grid', SPPUX.MetricGrid);
        SPPUX.defineElement('spp-badge', SPPUX.Badge);
        SPPUX.defineElement('spp-empty-state', SPPUX.EmptyState);
        SPPUX.defineElement('spp-diff', SPPUX.DiffViewer);
        SPPUX.defineElement('spp-transfer', SPPUX.TransferList);
        SPPUX.defineElement('spp-wizard', SPPUX.Wizard);
        SPPUX.defineElement('spp-breadcrumb', SPPUX.Breadcrumb);
        SPPUX.defineElement('spp-dropdown', SPPUX.DropdownMenu);

        // 22 New Enterprise Web Components
        SPPUX.defineElement('spp-tree', SPPUX.TreeView);
        SPPUX.defineElement('spp-kanban', SPPUX.KanbanBoard);
        SPPUX.defineElement('spp-accordion', SPPUX.Accordion);
        SPPUX.defineElement('spp-card', SPPUX.Card);
        SPPUX.defineElement('spp-list', SPPUX.ListView);
        SPPUX.defineElement('spp-description-list', SPPUX.DescriptionList);
        SPPUX.defineElement('spp-avatar', SPPUX.Avatar);
        SPPUX.defineElement('spp-kbd', SPPUX.Kbd);
        SPPUX.defineElement('spp-rating', SPPUX.Rating);
        SPPUX.defineElement('spp-slider', SPPUX.Slider);
        SPPUX.defineElement('spp-color-picker', SPPUX.ColorPicker);
        SPPUX.defineElement('spp-switch', SPPUX.Switch);
        SPPUX.defineElement('spp-markdown-editor', SPPUX.MarkdownEditor);
        SPPUX.defineElement('spp-signature', SPPUX.SignaturePad);
        SPPUX.defineElement('spp-pagination', SPPUX.Pagination);
        SPPUX.defineElement('spp-stepper', SPPUX.Stepper);
        SPPUX.defineElement('spp-modal', SPPUX.ModalComponent || SPPUX.Modal);
        SPPUX.defineElement('spp-drawer', SPPUX.DrawerComponent || SPPUX.Drawer);
        SPPUX.defineElement('spp-alert', SPPUX.AlertComponent || SPPUX.Alert);
        SPPUX.defineElement('spp-progress', SPPUX.ProgressBar);
        SPPUX.defineElement('spp-skeleton', SPPUX.Skeleton);
        SPPUX.defineElement('spp-qrcode', SPPUX.QRCode);
    }

})(window.SPPUX);

