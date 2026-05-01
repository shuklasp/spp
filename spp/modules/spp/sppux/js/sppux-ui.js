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
     * Theme Manager (Legendary)
     */
    SPPUX.Theme = {
        current: 'midnight',
        schemes: {
            night: { primary: '#6366f1', panel: 'rgba(15, 23, 42, 0.98)', glow: 'rgba(99, 102, 241, 0.4)', text: '#f3f4f6' },
            day: { primary: '#2563eb', panel: '#ffffff', glow: 'rgba(37, 99, 235, 0.15)', text: '#0f172a' },
            emerald: { primary: '#10b981', panel: 'rgba(6, 78, 59, 0.98)', glow: 'rgba(16, 185, 129, 0.4)', text: '#f3f4f6' },
            royal: { primary: '#8b5cf6', panel: 'rgba(46, 16, 101, 0.98)', glow: 'rgba(139, 92, 246, 0.4)', text: '#f3f4f6' },
            cyberpunk: { primary: '#ff00ff', panel: 'rgba(20, 0, 40, 0.98)', glow: 'rgba(255, 0, 255, 0.4)', text: '#ffffff' },
            ocean: { primary: '#0ea5e9', panel: 'rgba(7, 89, 133, 0.98)', glow: 'rgba(14, 165, 233, 0.4)', text: '#f3f4f6' },
            saffron: { primary: '#ff9933', panel: 'rgba(255, 247, 237, 0.98)', glow: 'rgba(255, 153, 51, 0.4)', text: '#431407' }
        },
        set(name) {
            const theme = this.schemes[name];
            if (!theme) return;
            this.current = name;
            const root = document.documentElement;
            root.style.setProperty('--sppux-primary', theme.primary);
            root.style.setProperty('--sppux-panel', theme.panel);
            root.style.setProperty('--sppux-primary-glow', theme.glow);
            root.style.setProperty('--sppux-text', theme.text || '#f3f4f6');
            document.body.classList.add('sppux-theme-transitioning');
            setTimeout(() => document.body.classList.remove('sppux-theme-transitioning'), 600);
            localStorage.setItem('sppux_theme', name);
        },
        init() {
            const saved = localStorage.getItem('sppux_theme');
            if (saved) this.set(saved);
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
        open(title, content, actions = []) {
            let container = document.getElementById('sppux-modal-root');
            if (container) container.remove();
            
            container = document.createElement('div');
            container.id = 'sppux-modal-root';
            document.body.appendChild(container);

            const modal = new (class extends BaseComponent {
                render() {
                    return html`
                        <div class="glass-overlay active">
                            <div class="glass-panel modal-box sppux-modal-animate">
                                <div class="modal-header">
                                    <h3>${this.props.title}</h3>
                                    <button class="close-icon" @click=${this.close}>✕</button>
                                </div>
                                <div class="modal-body">
                                    ${this.props.content}
                                </div>
                                <div class="modal-footer">
                                    ${this.props.actions.map(act => html`
                                        <button class="btn ${act.type || 'secondary'}-btn" @click=${() => act.fn(this)}>
                                            ${act.label}
                                        </button>
                                    `)}
                                </div>
                            </div>
                        </div>
                    `;
                }

                close() {
                    this.container.classList.add('sppux-modal-closing');
                    setTimeout(() => {
                        this.dispose();
                        this.container.remove();
                    }, 300);
                }
            })(window.admin, container, { title, content, actions });

            modal.update();
            return modal;
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
            const close = (e) => { if (!pop.contains(e.target) && e.target !== triggerEl) { pop.remove(); document.removeEventListener('mousedown', close); } };
            document.addEventListener('mousedown', close); return pop;
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
                onMount() { this.container.querySelector('input')?.focus(); }
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
                close() { this.container.classList.remove('active'); setTimeout(() => this.dispose(), 300); }
            })(window.admin, container, { items, onSelect });
            spotlight.update(); return spotlight;
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
    SPPUX.Drawer = { open(t, c, s = 'right') { let container = document.getElementById('sppux-drawer-root') || (document.body.appendChild(Object.assign(document.createElement('div'), {id:'sppux-drawer-root'}))); const drawer = new (class extends BaseComponent { render() { return html`<div class="sppux-drawer-overlay active"><div class="sppux-drawer sppux-drawer-${this.props.side}"><div class="modal-header"><h3>${this.props.title}</h3><button class="close-icon" @click=${this.close}>✕</button></div><div class="modal-body">${this.props.content}</div></div></div>`; } close() { this.container.classList.remove('active'); setTimeout(() => this.dispose(), 400); } })(window.admin, container, { title, content, side: s }); drawer.update(); return drawer; } };

    // Legendary Inits
    SPPUX.Theme.init();
    SPPUX.Tooltip.init();
    window.admin_notify = SPPUX.Notify.show;

})(window.SPPUX);
