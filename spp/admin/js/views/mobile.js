/**
 * Mobile Studio View
 * 
 * PWA configuration, responsive preview tools, push notification management,
 * and service worker status monitoring for the SPP Admin panel.
 */

export default class MobileView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: true,
            activeTab: 'manifest',

            // PWA Manifest
            manifest: {
                name: '',
                short_name: '',
                description: '',
                theme_color: '#38bdf8',
                background_color: '#0f111a',
                display: 'standalone',
                start_url: '/',
                icon_path: ''
            },
            manifestSaving: false,

            // Responsive Preview
            previewDevice: 'phone',
            previewUrl: '/',
            devices: {
                phone:   { label: '📱 Phone',   width: 375,  height: 667  },
                tablet:  { label: '📋 Tablet',  width: 768,  height: 1024 },
                desktop: { label: '🖥️ Desktop', width: 1280, height: 800  }
            },

            // Push Notifications
            vapidKey: '',
            pushTesting: false,

            // Service Worker
            swRegistrations: [],
            swChecking: false
        };

        await this.loadManifest();
        await this.checkServiceWorker();
    }

    // ── Data Loading ────────────────────────────────────────

    async loadManifest() {
        try {
            const res = await this.api('get_pwa_manifest');
            if (res.success && res.data) {
                const m = res.data;
                this.setState({
                    loading: false,
                    manifest: {
                        name:             m.name             || '',
                        short_name:       m.short_name       || '',
                        description:      m.description      || '',
                        theme_color:      m.theme_color      || '#38bdf8',
                        background_color: m.background_color || '#0f111a',
                        display:          m.display          || 'standalone',
                        start_url:        m.start_url        || '/',
                        icon_path:        m.icon_path        || ''
                    },
                    vapidKey: m.vapid_public_key || ''
                });
            } else {
                // API not configured yet – keep defaults
                this.setState({ loading: false });
            }
        } catch (e) {
            console.warn('PWA manifest load failed, using defaults:', e);
            this.setState({ loading: false });
        }
    }

    async saveManifest() {
        this.setState({ manifestSaving: true });
        try {
            const form = this.state.manifest;
            const res = await this.apiPost('save_pwa_manifest', form);
            if (res.success) {
                this.notify('PWA manifest saved successfully!', 'success');
            } else {
                this.notify('Failed to save manifest: ' + (res.message || 'Unknown error'), 'error');
            }
        } catch (e) {
            this.notify('Error saving manifest: ' + e.message, 'error');
        }
        this.setState({ manifestSaving: false });
    }

    async testPush() {
        this.setState({ pushTesting: true });
        try {
            const res = await this.apiPost('test_push_notification');
            if (res.success) {
                this.notify('Test push notification sent!', 'success');
            } else {
                this.notify(res.message || 'Push notifications are not configured on this server.', 'info');
            }
        } catch (e) {
            this.notify('Push service unavailable: ' + e.message, 'info');
        }
        this.setState({ pushTesting: false });
    }

    async checkServiceWorker() {
        this.setState({ swChecking: true });
        try {
            if ('serviceWorker' in navigator) {
                const registrations = await navigator.serviceWorker.getRegistrations();
                this.setState({
                    swRegistrations: registrations.map(r => ({
                        scope:    r.scope,
                        active:   r.active ? r.active.state : 'none',
                        waiting:  !!r.waiting,
                        installing: !!r.installing
                    })),
                    swChecking: false
                });
            } else {
                this.setState({ swRegistrations: [], swChecking: false });
            }
        } catch (e) {
            console.warn('Service worker check failed:', e);
            this.setState({ swRegistrations: [], swChecking: false });
        }
    }

    async updateServiceWorker() {
        try {
            if ('serviceWorker' in navigator) {
                const registrations = await navigator.serviceWorker.getRegistrations();
                for (const reg of registrations) {
                    await reg.update();
                }
                this.notify('Service workers updated!', 'success');
                await this.checkServiceWorker();
            }
        } catch (e) {
            this.notify('Update failed: ' + e.message, 'error');
        }
    }

    // ── Helpers ──────────────────────────────────────────────

    updateManifestField(field, value) {
        this.setState({
            manifest: { ...this.state.manifest, [field]: value }
        });
    }

    // ── Render ───────────────────────────────────────────────

    render() {
        if (this.state.loading) {
            return html`<div class="loading-state"><div class="sppux-spinner"></div> Loading Mobile Studio...</div>`;
        }

        const { activeTab } = this.state;

        return html`
            <style>
                .mobile-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--glass-border); margin-bottom: 2rem; }
                .mobile-tab { padding: 12px 24px; cursor: pointer; color: var(--text-dim); border-bottom: 2px solid transparent; transition: all 0.2s; font-size: 0.95rem; }
                .mobile-tab:hover { color: var(--text); background: rgba(255,255,255,0.03); }
                .mobile-tab.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
                .manifest-form { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
                .manifest-form .full-width { grid-column: 1 / -1; }
                .color-field { display: flex; align-items: center; gap: 0.75rem; }
                .color-field input[type="color"] { width: 44px; height: 36px; border: 1px solid var(--glass-border); border-radius: 6px; background: transparent; cursor: pointer; padding: 2px; }
                .color-field .color-hex { flex: 1; }
                .device-switcher { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; }
                .device-btn { padding: 8px 18px; border-radius: 8px; border: 1px solid var(--glass-border); background: transparent; color: var(--text-dim); cursor: pointer; transition: all 0.2s; font-size: 0.9rem; }
                .device-btn:hover { border-color: var(--primary-color); color: var(--text); }
                .device-btn.active { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
                .preview-frame { border: 2px solid var(--glass-border); border-radius: 12px; margin: 0 auto; transition: all 0.4s ease; display: flex; flex-direction: column; align-items: center; justify-content: center; background: rgba(0,0,0,0.2); position: relative; overflow: hidden; }
                .preview-frame .device-chrome { width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border-bottom: 1px solid var(--glass-border); display: flex; align-items: center; gap: 8px; }
                .preview-frame .url-bar { flex: 1; padding: 6px 12px; border-radius: 20px; background: rgba(0,0,0,0.3); color: var(--text-dim); font-size: 0.8rem; border: 1px solid var(--glass-border); }
                .preview-body { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; color: var(--text-dim); text-align: center; }
                .sw-status-row { display: flex; align-items: center; gap: 1rem; padding: 1rem; border-radius: 8px; background: rgba(255,255,255,0.03); margin-bottom: 0.75rem; }
                .sw-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
                .sw-dot.active { background: var(--success); box-shadow: 0 0 6px var(--success); }
                .sw-dot.inactive { background: var(--text-dim); }
                @media (max-width: 768px) {
                    .manifest-form { grid-template-columns: 1fr; }
                }
            </style>

            <div class="view-content-wrapper fade-in" style="max-width: 1100px; margin: 0 auto;">
                <!-- Header -->
                <div class="glass-panel" style="padding: 2rem 2rem 0 2rem; margin-bottom: 1.5rem; background: linear-gradient(145deg, rgba(30,32,40,0.8) 0%, rgba(15,17,26,0.9) 100%); position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -50%; right: -10%; width: 40%; height: 200%; background: radial-gradient(circle, rgba(56,189,248,0.08) 0%, transparent 70%); pointer-events: none;"></div>
                    <h2 class="gradient-text" style="margin: 0 0 0.5rem 0; font-size: 1.8rem;">📱 Mobile Studio</h2>
                    <p style="color: var(--text-dim); margin: 0 0 1.5rem 0;">Configure PWA settings, preview responsive layouts, and manage push notifications.</p>

                    <div class="mobile-tabs">
                        <div class="mobile-tab ${activeTab === 'manifest' ? 'active' : ''}" @click=${() => this.setState({ activeTab: 'manifest' })}>📦 PWA Manifest</div>
                        <div class="mobile-tab ${activeTab === 'preview'  ? 'active' : ''}" @click=${() => this.setState({ activeTab: 'preview'  })}>🖼️ Preview</div>
                        <div class="mobile-tab ${activeTab === 'push'     ? 'active' : ''}" @click=${() => this.setState({ activeTab: 'push'     })}>🔔 Push</div>
                        <div class="mobile-tab ${activeTab === 'sw'       ? 'active' : ''}" @click=${() => this.setState({ activeTab: 'sw'       })}>⚙️ Service Worker</div>
                    </div>
                </div>

                <!-- Tab Content -->
                ${activeTab === 'manifest' ? this.renderManifestTab()  : ''}
                ${activeTab === 'preview'  ? this.renderPreviewTab()   : ''}
                ${activeTab === 'push'     ? this.renderPushTab()      : ''}
                ${activeTab === 'sw'       ? this.renderSWTab()        : ''}
            </div>
        `;
    }

    // ── Tab: PWA Manifest ────────────────────────────────────

    renderManifestTab() {
        const m = this.state.manifest;

        return html`
            <div class="glass-panel" style="padding: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <div>
                        <h3 style="margin: 0; font-size: 1.25rem;">Web App Manifest</h3>
                        <p style="color: var(--text-dim); margin: 0.25rem 0 0 0; font-size: 0.9rem;">Configure how your app appears when installed on a device.</p>
                    </div>
                    <button class="btn primary-btn" ?disabled=${this.state.manifestSaving} @click=${() => this.saveManifest()}>
                        ${this.state.manifestSaving ? html`<span class="sppux-spinner" style="width:16px;height:16px;"></span> Saving...` : '💾 Save Manifest'}
                    </button>
                </div>

                <div class="manifest-form">
                    <!-- App Name -->
                    <div class="input-group">
                        <label>App Name</label>
                        <input type="text" class="spp-element" placeholder="My Awesome App"
                            .value=${m.name}
                            @input=${(e) => this.updateManifestField('name', e.target.value)} />
                    </div>

                    <!-- Short Name -->
                    <div class="input-group">
                        <label>Short Name</label>
                        <input type="text" class="spp-element" placeholder="MyApp" maxlength="12"
                            .value=${m.short_name}
                            @input=${(e) => this.updateManifestField('short_name', e.target.value)} />
                        <small style="color: var(--text-dim);">Max 12 characters. Shown on home screen.</small>
                    </div>

                    <!-- Description -->
                    <div class="input-group full-width">
                        <label>Description</label>
                        <textarea class="spp-element" rows="3" placeholder="A brief description of your application..."
                            .value=${m.description}
                            @input=${(e) => this.updateManifestField('description', e.target.value)}></textarea>
                    </div>

                    <!-- Theme Color -->
                    <div class="input-group">
                        <label>Theme Color</label>
                        <div class="color-field">
                            <input type="color" .value=${m.theme_color}
                                @input=${(e) => this.updateManifestField('theme_color', e.target.value)} />
                            <input type="text" class="spp-element color-hex" .value=${m.theme_color}
                                @input=${(e) => this.updateManifestField('theme_color', e.target.value)} />
                        </div>
                    </div>

                    <!-- Background Color -->
                    <div class="input-group">
                        <label>Background Color</label>
                        <div class="color-field">
                            <input type="color" .value=${m.background_color}
                                @input=${(e) => this.updateManifestField('background_color', e.target.value)} />
                            <input type="text" class="spp-element color-hex" .value=${m.background_color}
                                @input=${(e) => this.updateManifestField('background_color', e.target.value)} />
                        </div>
                    </div>

                    <!-- Display Mode -->
                    <div class="input-group">
                        <label>Display Mode</label>
                        <select class="spp-element" .value=${m.display}
                            @change=${(e) => this.updateManifestField('display', e.target.value)}>
                            <option value="standalone"  ?selected=${m.display === 'standalone'}>Standalone</option>
                            <option value="fullscreen"  ?selected=${m.display === 'fullscreen'}>Fullscreen</option>
                            <option value="minimal-ui"  ?selected=${m.display === 'minimal-ui'}>Minimal UI</option>
                            <option value="browser"     ?selected=${m.display === 'browser'}>Browser</option>
                        </select>
                        <small style="color: var(--text-dim);">Standalone removes the browser UI chrome.</small>
                    </div>

                    <!-- Start URL -->
                    <div class="input-group">
                        <label>Start URL</label>
                        <input type="text" class="spp-element" placeholder="/"
                            .value=${m.start_url}
                            @input=${(e) => this.updateManifestField('start_url', e.target.value)} />
                    </div>

                    <!-- Icon -->
                    <div class="input-group full-width">
                        <label>App Icon</label>
                        <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; border-radius: 8px; background: rgba(255,255,255,0.03); border: 1px dashed var(--glass-border);">
                            <div style="width: 64px; height: 64px; border-radius: 12px; background: ${m.theme_color}20; display: flex; align-items: center; justify-content: center; font-size: 2rem; flex-shrink: 0;">
                                ${m.icon_path ? html`<img src="${m.icon_path}" style="width: 48px; height: 48px; border-radius: 8px; object-fit: cover;" alt="App Icon" />` : '📱'}
                            </div>
                            <div style="flex: 1;">
                                <p style="margin: 0; color: var(--text); font-size: 0.9rem;">${m.icon_path || 'No icon set'}</p>
                                <p style="margin: 0.25rem 0 0 0; color: var(--text-dim); font-size: 0.8rem;">Recommended: 512×512px PNG. Upload via your app's asset manager.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // ── Tab: Responsive Preview ──────────────────────────────

    renderPreviewTab() {
        const { previewDevice, previewUrl, devices } = this.state;
        const device = devices[previewDevice];
        const maxW = Math.min(device.width, 900);
        const scale = maxW / device.width;
        const displayH = Math.round(device.height * scale);

        return html`
            <div class="glass-panel" style="padding: 2rem;">
                <h3 style="margin: 0 0 0.5rem 0; font-size: 1.25rem;">Responsive Preview</h3>
                <p style="color: var(--text-dim); margin: 0 0 1.5rem 0; font-size: 0.9rem;">Preview how your app looks across device sizes.</p>

                <!-- Device Switcher -->
                <div class="device-switcher">
                    ${Object.entries(devices).map(([key, dev]) => html`
                        <button class="device-btn ${previewDevice === key ? 'active' : ''}"
                            @click=${() => this.setState({ previewDevice: key })}>
                            ${dev.label}
                            <span style="display: block; font-size: 0.75rem; opacity: 0.7;">${dev.width}×${dev.height}</span>
                        </button>
                    `)}
                </div>

                <!-- URL Input -->
                <div style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem;">
                    <input type="text" class="spp-element" placeholder="Enter route to preview, e.g. /dashboard"
                        style="flex: 1;"
                        .value=${previewUrl}
                        @input=${(e) => this.setState({ previewUrl: e.target.value })} />
                    <a class="btn ghost-btn" href="${previewUrl}" target="_blank" rel="noopener" style="text-decoration: none; display: flex; align-items: center; gap: 4px;">
                        🔗 Open in New Tab
                    </a>
                </div>

                <!-- Preview Frame -->
                <div class="preview-frame" style="width: ${maxW}px; height: ${displayH}px;">
                    <div class="device-chrome">
                        <div style="display: flex; gap: 4px;">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--error);"></span>
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--warning);"></span>
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--success);"></span>
                        </div>
                        <div class="url-bar">${previewUrl}</div>
                        <span style="font-size: 0.75rem; color: var(--text-dim);">${device.width}×${device.height}</span>
                    </div>
                    <div class="preview-body">
                        <div>
                            <div style="font-size: 3rem; margin-bottom: 1rem;">${previewDevice === 'phone' ? '📱' : previewDevice === 'tablet' ? '📋' : '🖥️'}</div>
                            <p style="font-size: 1.1rem; margin: 0; color: var(--text);">${device.label} Preview</p>
                            <p style="font-size: 0.85rem; margin: 0.5rem 0 0 0;">${device.width} × ${device.height}px</p>
                            <p style="font-size: 0.8rem; margin: 1rem 0 0 0; max-width: 300px;">
                                Actual iframe preview is restricted by CORS policies. 
                                Use <strong>"Open in New Tab"</strong> and resize your browser, or use DevTools device emulation for a live preview.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // ── Tab: Push Notifications ──────────────────────────────

    renderPushTab() {
        const { vapidKey, pushTesting } = this.state;

        return html`
            <div class="card-grid" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <!-- Info Panel -->
                <div class="glass-panel" style="padding: 2rem;">
                    <h3 style="margin: 0 0 1rem 0; font-size: 1.25rem;">🔔 Push Notification Support</h3>
                    <p style="color: var(--text-dim); font-size: 0.9rem; line-height: 1.6;">
                        Push notifications allow your PWA to re-engage users with timely, relevant information even when the app is not in the foreground.
                    </p>
                    <div style="margin-top: 1.5rem; padding: 1rem; border-radius: 8px; background: rgba(56,189,248,0.08); border: 1px solid rgba(56,189,248,0.2);">
                        <p style="margin: 0; font-size: 0.85rem; color: var(--text);">
                            <strong>Requirements:</strong>
                        </p>
                        <ul style="margin: 0.5rem 0 0 0; padding-left: 1.25rem; color: var(--text-dim); font-size: 0.85rem; line-height: 1.8;">
                            <li>HTTPS enabled on your domain</li>
                            <li>Service Worker registered and active</li>
                            <li>VAPID keys generated on the server</li>
                            <li>User grants notification permission</li>
                        </ul>
                    </div>
                </div>

                <!-- Config & Test Panel -->
                <div class="glass-panel" style="padding: 2rem;">
                    <h3 style="margin: 0 0 1rem 0; font-size: 1.25rem;">⚙️ Configuration</h3>

                    <div class="input-group" style="margin-bottom: 1.5rem;">
                        <label>VAPID Public Key</label>
                        <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); font-family: monospace; font-size: 0.8rem; word-break: break-all; color: ${vapidKey ? 'var(--text)' : 'var(--text-dim)'};">
                            ${vapidKey || 'Not configured — generate VAPID keys on your server to enable push support.'}
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.75rem; align-items: center;">
                        <button class="btn primary-btn" ?disabled=${pushTesting || !vapidKey}
                            @click=${() => this.testPush()}>
                            ${pushTesting ? html`<span class="sppux-spinner" style="width:16px;height:16px;"></span> Sending...` : '🚀 Send Test Notification'}
                        </button>
                        ${!vapidKey ? html`<span class="tag" style="background: rgba(244,63,94,0.15); color: var(--error); font-size: 0.8rem;">Not Configured</span>` : html`<span class="tag success-tag" style="font-size: 0.8rem;">✓ Ready</span>`}
                    </div>

                    ${!vapidKey ? html`
                        <p style="margin: 1.25rem 0 0 0; color: var(--text-dim); font-size: 0.8rem;">
                            💡 Run <code style="background: rgba(255,255,255,0.08); padding: 2px 6px; border-radius: 4px;">php spp.php push:generate-vapid</code> to create your VAPID key pair.
                        </p>
                    ` : ''}
                </div>
            </div>
        `;
    }

    // ── Tab: Service Worker ──────────────────────────────────

    renderSWTab() {
        const { swRegistrations, swChecking } = this.state;
        const swSupported = 'serviceWorker' in navigator;

        return html`
            <div class="glass-panel" style="padding: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <div>
                        <h3 style="margin: 0; font-size: 1.25rem;">Service Worker Status</h3>
                        <p style="color: var(--text-dim); margin: 0.25rem 0 0 0; font-size: 0.9rem;">Monitor and manage registered service workers for this origin.</p>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button class="btn ghost-btn" ?disabled=${swChecking} @click=${() => this.checkServiceWorker()}>
                            ${swChecking ? html`<span class="sppux-spinner" style="width:14px;height:14px;"></span>` : '🔄'} Refresh
                        </button>
                        <button class="btn primary-btn" @click=${() => this.updateServiceWorker()}>
                            ⬆️ Update All
                        </button>
                    </div>
                </div>

                ${!swSupported ? html`
                    <div style="padding: 2rem; text-align: center; color: var(--text-dim);">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🚫</div>
                        <p style="font-size: 1.1rem; color: var(--error);">Service Workers are not supported in this browser.</p>
                        <p style="font-size: 0.9rem;">Try accessing the admin panel over HTTPS with a modern browser.</p>
                    </div>
                ` : swRegistrations.length === 0 ? html`
                    <div style="padding: 2rem; text-align: center; color: var(--text-dim);">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                        <p style="font-size: 1.1rem; color: var(--text);">No Service Workers Registered</p>
                        <p style="font-size: 0.9rem;">Register a service worker in your app to enable offline support, caching, and push notifications.</p>
                    </div>
                ` : html`
                    ${swRegistrations.map((reg, idx) => html`
                        <div class="sw-status-row">
                            <div class="sw-dot ${reg.active === 'activated' ? 'active' : 'inactive'}"></div>
                            <div style="flex: 1; min-width: 0;">
                                <p style="margin: 0; font-size: 0.95rem; color: var(--text);">
                                    Registration #${idx + 1}
                                </p>
                                <p style="margin: 0.25rem 0 0 0; font-size: 0.8rem; color: var(--text-dim); word-break: break-all;">
                                    Scope: ${reg.scope}
                                </p>
                            </div>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <span class="tag ${reg.active === 'activated' ? 'success-tag' : 'info-tag'}" style="font-size: 0.75rem;">
                                    ${reg.active === 'activated' ? '✓ Active' : reg.active === 'activating' ? '⏳ Activating' : '○ ' + reg.active}
                                </span>
                                ${reg.waiting ? html`<span class="tag" style="background: rgba(251,191,36,0.15); color: var(--warning); font-size: 0.75rem;">⏳ Waiting</span>` : ''}
                                ${reg.installing ? html`<span class="tag info-tag" style="font-size: 0.75rem;">📥 Installing</span>` : ''}
                            </div>
                        </div>
                    `)}
                `}

                <!-- Quick Reference -->
                <div style="margin-top: 2rem; padding: 1.25rem; border-radius: 8px; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border);">
                    <p style="margin: 0 0 0.5rem 0; font-size: 0.85rem; font-weight: 600; color: var(--text);">Quick Reference</p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem; color: var(--text-dim);">
                        <div><span style="color: var(--success);">●</span> <strong>Activated</strong> — Worker is controlling pages</div>
                        <div><span style="color: var(--warning);">●</span> <strong>Waiting</strong> — New version ready, pending activation</div>
                        <div><span style="color: var(--primary-color);">●</span> <strong>Installing</strong> — Worker is being installed</div>
                        <div><span style="color: var(--text-dim);">●</span> <strong>Redundant</strong> — Worker has been replaced</div>
                    </div>
                </div>
            </div>
        `;
    }
}
