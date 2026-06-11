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

    /**
     * Theme Manager (Legendary) - Upgraded to SPPUX Global Store
     */
    const _themeSchemes = {
        night: { primary: '#6366f1', panel: 'rgba(15, 23, 42, 0.98)', glow: 'rgba(99, 102, 241, 0.4)', text: '#f3f4f6' },
        day: { primary: '#2563eb', panel: '#ffffff', glow: 'rgba(37, 99, 235, 0.15)', text: '#0f172a' },
        emerald: { primary: '#10b981', panel: 'rgba(6, 78, 59, 0.98)', glow: 'rgba(16, 185, 129, 0.4)', text: '#f3f4f6' },
        royal: { primary: '#8b5cf6', panel: 'rgba(46, 16, 101, 0.98)', glow: 'rgba(139, 92, 246, 0.4)', text: '#f3f4f6' },
        cyberpunk: { primary: '#ff00ff', panel: 'rgba(20, 0, 40, 0.98)', glow: 'rgba(255, 0, 255, 0.4)', text: '#ffffff' },
        ocean: { primary: '#0ea5e9', panel: 'rgba(7, 89, 133, 0.98)', glow: 'rgba(14, 165, 233, 0.4)', text: '#f3f4f6' },
        saffron: { primary: '#ff9933', panel: 'rgba(255, 247, 237, 0.98)', glow: 'rgba(255, 153, 51, 0.4)', text: '#431407' }
    };

    SPPUX.Theme = (SPPUX.createStore ? SPPUX.createStore({ current: 'midnight' }) : { current: 'midnight' });
    
    SPPUX.Theme.schemes = _themeSchemes;
    SPPUX.Theme.set = function(name, manualOverride = false) {
        const theme = _themeSchemes[name];
        if (!theme) return;
        this.current = name; // If it's a proxy store, this triggers reactivity globally!
        const root = document.documentElement;
        if (name === 'day') {
            root.setAttribute('data-theme', 'day');
        } else {
            root.removeAttribute('data-theme');
            root.style.setProperty('--sppux-primary', theme.primary);
            root.style.setProperty('--sppux-panel', theme.panel);
            root.style.setProperty('--sppux-primary-glow', theme.glow);
            root.style.setProperty('--sppux-text', theme.text || '#f3f4f6');
        }
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
                                    <button class="close-icon" @click=${this.close}>✕</button>
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
                    }
                    if (this._tabListener) {
                        this.container.removeEventListener('keydown', this._tabListener);
                    }
                    this.container.classList.add('sppux-modal-closing');
                    setTimeout(() => {
                        try { this.dispose(); } catch(e) { console.warn('Modal dispose error', e); }
                        if (this.container) this.container.remove();
                    }, 50);
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
        subModal.innerHTML = `
            <div class="modal-content glass-panel sppux-sub-editor-animate" style="width: 80vw; max-width: 1000px; height: 80vh; background: var(--sppux-panel); display: flex; flex-direction: column;">
                <div class="modal-header">
                    <h3>${title}</h3>
                    <button class="close-btn" onclick="this.closest('.sub-modal').remove()">✕</button>
                </div>
                <div class="modal-body" id="sub-editor-body" style="flex: 1; overflow-y: auto; padding: 1.5rem;"></div>
                <div class="modal-footer">
                    <button class="btn secondary-btn" onclick="this.closest('.sub-modal').remove()">Cancel</button>
                    <button class="btn primary-btn" id="sub-modal-save">Apply Changes</button>
                </div>
            </div>`;
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
            closeBtn.onclick = () => {
                document.removeEventListener('keydown', escListener);
                subModal.remove();
            };
        }
        
        const cancelBtn = subModal.querySelector('.secondary-btn');
        if (cancelBtn) {
            cancelBtn.onclick = () => {
                document.removeEventListener('keydown', escListener);
                subModal.remove();
            };
        }

        const body = subModal.querySelector('#sub-editor-body');
        if (typeof content === 'string') body.innerHTML = content;
        else SPPUX.render(content, body);
        
        // Initialize reactive dependencies if SPPForm is available
        if (window.SPPForm) SPPForm.autoInit(body);

        const saveBtn = subModal.querySelector('#sub-modal-save');
        if (saveBtn) {
            saveBtn.onclick = async () => {
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
            };
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
            SPPUX.render(content, body);
            if (window.SPPForm) SPPForm.autoInit(body);
        }
    };

    /**
     * Popover Helper
     */
    SPPUX.Popover = {
        open(triggerEl, title, content) {
            const pop = document.createElement('div'); pop.className = 'sppux-popover';
            pop.innerHTML = `<div class="sppux-popover-header"><b>${title}</b></div><div class="sppux-popover-body">${content}</div>`;
            document.body.appendChild(pop);
            const rect = triggerEl.getBoundingClientRect();
            pop.style.left = rect.left + 'px'; pop.style.top = rect.bottom + 8 + 'px';
            const close = (e) => { 
                if (e.type === 'keydown' && e.key === 'Escape') { pop.remove(); cleanup(); } 
                else if (e.type === 'mousedown' && !pop.contains(e.target) && e.target !== triggerEl) { pop.remove(); cleanup(); } 
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
                    this.container.querySelector('input')?.focus(); 
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
                    this.container.classList.remove('active'); 
                    setTimeout(() => this.dispose(), 100); 
                }
            })(null, container, { items, onSelect });
            spotlight.update();
            spotlight.onMount();
            return spotlight;
        }
    };

    /**
     * Notify
     */
    SPPUX.Notify = { 
        show(m, t = 'info', d = 4000) { 
            if(t === 'success') this.elegantSuccess(); 
            let container = document.getElementById('sppux-toast-root') || (document.body.appendChild(Object.assign(document.createElement('div'), {id:'sppux-toast-root'}))); 
            const toast = document.createElement('div'); 
            toast.className = `sppux-toast sppux-toast-${t}`; 
            toast.innerHTML = `<span>${m}</span>`; 
            container.appendChild(toast); 
            setTimeout(() => { 
                toast.classList.add('sppux-toast-removing'); 
                setTimeout(() => toast.remove(), 400); 
            }, d); 
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
    SPPUX.StatsCard = { render(t, v, o = {}) { const { trend = null, trendType = 'success', icon = '📈' } = o; return html`<div class="sppux-stats-card"><div class="sppux-stats-header"><span>${t}</span><span>${icon}</span></div><div class="sppux-stats-value">${v}</div>${trend ? html`<div class="sppux-trend-${trendType}">${trend}</div>` : Fragment}</div>`; } };
    SPPUX.Button = { render(l, o = {}) { const { variant = 'primary', size = 'md', icon = null, loading = false, onClick = null } = o; const btnClass = `sppux-btn sppux-btn-${variant} sppux-btn-${size} ${loading ? 'loading' : ''}`; return html`<button class="${btnClass}" ?disabled="${loading}" @click=${onClick}>${loading ? html`<span class="sppux-spinner"></span>` : icon ? html`<span>${icon}</span>` : Fragment}<span>${l}</span></button>`; } };
    SPPUX.Tooltip = { init() { document.addEventListener('mouseover', (e) => { const target = e.target.closest('[data-spp-tooltip]'); if (target && !target.dataset.tooltipActive) { target.dataset.tooltipActive = "true"; const tip = document.createElement('div'); tip.className = 'sppux-tooltip'; tip.textContent = target.dataset.sppTooltip; document.body.appendChild(tip); const rect = target.getBoundingClientRect(); tip.style.left = rect.left + (rect.width / 2) - (tip.offsetWidth / 2) + 'px'; tip.style.top = rect.top - tip.offsetHeight - 8 + 'px'; target.addEventListener('mouseleave', () => { tip.remove(); delete target.dataset.tooltipActive; }, { once: true }); } }); } };
    SPPUX.Avatar = { render(n, s = null, z = 'md') { const initials = n ? n.split(' ').map(x => x[0]).join('').substring(0, 2).toUpperCase() : '?'; return html`<div class="sppux-avatar sppux-avatar-${z}">${s ? html`<img src="${s}">` : html`<span>${initials}</span>`}</div>`; } };
    SPPUX.Tabs = { render(tabs, activeId) { return html`<div class="sppux-tabs-container"><div class="sppux-tabs-header">${tabs.map(t => html`<button class="${t.id === activeId ? 'active' : ''}" @click=${t.onClick}>${t.label}</button>`)}</div><div class="sppux-tabs-content">${tabs.find(t => t.id === activeId)?.content || Fragment}</div></div>`; } };
    SPPUX.Drawer = { open(t, c, s = 'right') { 
        if (typeof c === 'string' && c.includes('<')) c = new SPPUX.TrustedHTML(c);
        let container = document.getElementById('sppux-drawer-root') || (document.body.appendChild(Object.assign(document.createElement('div'), {id:'sppux-drawer-root'}))); 
        const drawer = new (class extends BaseComponent { 
            onMount() {
                this._escListener = (e) => { if (e.key === 'Escape') this.close(); };
                document.addEventListener('keydown', this._escListener);
            }
            render() { return html`<div class="sppux-drawer-overlay active"><div class="sppux-drawer sppux-drawer-${this.props.side}"><div class="modal-header"><h3>${this.props.title}</h3><button class="close-icon" @click=${this.close}>✕</button></div><div class="modal-body">${this.props.content}</div></div></div>`; } 
            close() { 
                if (this._escListener) document.removeEventListener('keydown', this._escListener);
                this.container.classList.remove('active'); 
                setTimeout(() => this.dispose(), 150); 
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
                <div class="sppux-chip-container" @click=${(e) => e.currentTarget.querySelector('input').focus()}>
                    ${items.map((item, idx) => html`
                        <span class="sppux-chip">
                            ${item} <span class="sppux-chip-close" @click=${(e) => { e.stopPropagation(); onRemove(idx); }}>✕</span>
                        </span>
                    `)}
                    <input type="text" class="sppux-chip-input" placeholder="Add tag..." @keydown=${(e) => {
                        if (e.key === 'Enter' && e.target.value.trim()) {
                            onAdd(e.target.value.trim());
                            e.target.value = '';
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
        render(onDrop, text = "Drag & Drop files here or click to browse") {
            const handleDragOver = (e) => {
                e.preventDefault();
                e.currentTarget.classList.add('drag-over');
            };
            const handleDragLeave = (e) => {
                e.currentTarget.classList.remove('drag-over');
            };
            const handleDrop = (e) => {
                e.preventDefault();
                e.currentTarget.classList.remove('drag-over');
                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    onDrop(Array.from(e.dataTransfer.files));
                }
            };
            const handleClick = (e) => {
                const input = document.createElement('input');
                input.type = 'file';
                input.multiple = true;
                input.onchange = () => {
                    if (input.files && input.files.length > 0) onDrop(Array.from(input.files));
                };
                input.click();
            };
            return html`
                <div class="sppux-dropzone" 
                    @dragover=${handleDragOver} 
                    @dragleave=${handleDragLeave} 
                    @drop=${handleDrop}
                    @click=${handleClick}>
                    <div>☁️</div>
                    <div style="margin-top: 10px;">${text}</div>
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
                el.innerHTML = `<span>${item.icon || ''}</span> <span>${item.label}</span>`;
                el.onclick = () => {
                    if (item.action) item.action();
                    menu.remove();
                };
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
            return html`
                <div class="sppux-alert sppux-alert-${type}">
                    <span style="font-size: 1.2rem; line-height: 1;">
                        ${type === 'success' ? '✓' : type === 'warning' ? '⚠️' : type === 'error' ? '✕' : 'ℹ️'}
                    </span>
                    <div style="flex: 1;">${message}</div>
                    ${onClose ? html`<span class="sppux-alert-close" @click=${onClose}>✕</span>` : Fragment}
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
                                @click=${() => onChange(opt.value)}
                                onmouseover="this.style.background='var(--sppux-primary-subtle)'"
                                onmouseout="this.style.background='transparent'">
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
                    <div style="cursor: pointer; padding: 4px 0;" @click=${(e) => {
                        const childContainer = e.currentTarget.nextElementSibling;
                        if (childContainer) {
                            const isHidden = childContainer.style.display === 'none';
                            childContainer.style.display = isHidden ? 'block' : 'none';
                            e.currentTarget.querySelector('.sppux-tree-icon').textContent = isHidden ? '▼' : '▶';
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
                        onmouseover="this.style.background='var(--sppux-primary)'"
                        onmouseout="this.style.background='var(--sppux-glass-border)'"
                        @pointerdown=${(e) => {
                            e.preventDefault();
                            const resizer = e.target;
                            const leftPane = resizer.previousElementSibling;
                            const container = resizer.parentElement;
                            const startX = e.clientX;
                            const startWidth = leftPane.getBoundingClientRect().width;
                            const onMove = (evt) => {
                                const newWidth = startWidth + (evt.clientX - startX);
                                leftPane.style.flex = `0 0 ${newWidth}px`;
                            };
                            const onUp = () => {
                                document.removeEventListener('pointermove', onMove);
                                document.removeEventListener('pointerup', onUp);
                            };
                            document.addEventListener('pointermove', onMove);
                            document.addEventListener('pointerup', onUp);
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
                                @dragover=${(e) => { e.preventDefault(); e.currentTarget.style.background = 'var(--sppux-glass-bg)'; }}
                                @dragleave=${(e) => { e.currentTarget.style.background = 'transparent'; }}
                                @drop=${(e) => {
                                    e.preventDefault();
                                    e.currentTarget.style.background = 'transparent';
                                    const cardId = e.dataTransfer.getData('text/plain');
                                    onDragEnd(cardId, col.id);
                                }}>
                                ${col.cards.map(card => html`
                                    <div class="sppux-kanban-card" draggable="true" data-card-id="${card.id}" style="background:var(--sppux-card-bg);padding:12px;border:1px solid var(--sppux-glass-border);border-radius:var(--sppux-radius-sm);cursor:grab;box-shadow:0 2px 4px rgba(0,0,0,0.1);"
                                        @dragstart=${(e) => {
                                            e.dataTransfer.setData('text/plain', card.id);
                                            e.currentTarget.style.opacity = '0.5';
                                        }}
                                        @dragend=${(e) => { e.currentTarget.style.opacity = '1'; }}>
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
            overlay.innerHTML = `<img src="${src}" alt="${alt}" style="max-width:90vw;max-height:90vh;border-radius:var(--sppux-radius-md);box-shadow:0 10px 40px rgba(0,0,0,0.5);transform:scale(0.9);animation:sppux-zoom-in 0.3s forwards cubic-bezier(0.34, 1.56, 0.64, 1);">`;
            
            const close = () => {
                overlay.style.opacity = '0';
                setTimeout(() => overlay.remove(), 300);
            };
            overlay.onclick = close;
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

    // Web Component Registrations
    if (SPPUX.defineElement) {
        class RouterLinkComponent extends BaseComponent {
            render() {
                const path = this.props.to || this.props.href || '#';
                return html`<a href="${path}" data-spp-route="true" class="${this.props.class || ''}">${this.props.text || this.container.innerHTML}</a>`;
            }
        }
        SPPUX.defineElement('spp-router-link', RouterLinkComponent);
    }

})(window.SPPUX);
