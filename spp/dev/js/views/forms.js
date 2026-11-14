/**
 * FormsView Component
 * 
 * Manages form manifests and features a drag-and-drop Visual Form Builder
 * for SPP's Declarative Zero-Code YAML Form Engine.
 */

// Comprehensive Catalog of All 25+ SPP Form Elements
const ELEMENT_TYPES = [
    // Standard Inputs
    { type: 'text', label: 'Text Input', icon: '🔤', category: 'standard', desc: 'Single-line plain text input', default: { col: 12, placeholder: 'Enter text...' } },
    { type: 'email', label: 'Email', icon: '📧', category: 'standard', desc: 'Validated email address input', default: { col: 6, placeholder: 'user@example.com', validation: 'email' } },
    { type: 'password', label: 'Password', icon: '🔒', category: 'standard', desc: 'Secure masked password input', default: { col: 6, placeholder: '••••••••' } },
    { type: 'number', label: 'Number', icon: '🔢', category: 'standard', desc: 'Numeric quantity or decimal value', default: { col: 6, placeholder: '0' } },
    { type: 'phone', label: 'Phone', icon: '📞', category: 'standard', desc: 'Telephone number with format mask', default: { col: 6, placeholder: '+1 234 567 8900' } },
    { type: 'url', label: 'Website URL', icon: '🌐', category: 'standard', desc: 'Web URL with protocol validator', default: { col: 6, placeholder: 'https://example.com', validation: 'url' } },
    { type: 'hidden', label: 'Hidden Field', icon: '👁️‍🗨️', category: 'standard', desc: 'Non-visual form payload value', default: { col: 12 } },
    { type: 'textarea', label: 'Textarea', icon: '📄', category: 'standard', desc: 'Multi-line expanded text area', default: { col: 12, placeholder: 'Enter notes or description...' } },

    // Selection & Choices
    { type: 'select', label: 'Dropdown (Select)', icon: '▾', category: 'selection', desc: 'Single-choice dropdown menu', default: { col: 6, options: { option1: 'Option 1', option2: 'Option 2' } } },
    { type: 'multiselect', label: 'Multi-Select', icon: '📑', category: 'selection', desc: 'Multi-choice taggable dropdown', default: { col: 6, options: { tag1: 'Tag 1', tag2: 'Tag 2' } } },
    { type: 'checkbox', label: 'Checkbox', icon: '☑️', category: 'selection', desc: 'Single boolean agreement or check', default: { col: 12 } },
    { type: 'radio', label: 'Radio Group', icon: '🔘', category: 'selection', desc: 'Mutually exclusive radio options', default: { col: 6, options: { choice1: 'Choice 1', choice2: 'Choice 2' } } },
    { type: 'toggle', label: 'Switch / Toggle', icon: '🎚️', category: 'selection', desc: 'Modern reactive on/off slider switch', default: { col: 6 } },

    // Advanced & Interactive
    { type: 'date', label: 'Date & Time', icon: '📅', category: 'advanced', desc: 'Calendar date/time picker', default: { col: 6 } },
    { type: 'file', label: 'File Upload', icon: '📎', category: 'advanced', desc: 'Single or multi-file attachment upload', default: { col: 12, accept: '*/*' } },
    { type: 'range', label: 'Range Slider', icon: '🎛️', category: 'advanced', desc: 'Interactive visual slider track', default: { col: 6, min: 0, max: 100, step: 1 } },
    { type: 'color', label: 'Color Picker', icon: '🎨', category: 'advanced', desc: 'Hex color picker with swatch palette', default: { col: 6, value: '#3b82f6' } },
    { type: 'signature', label: 'Digital Signature', icon: '✍️', category: 'advanced', desc: 'Canvas-based digital signature pad', default: { col: 12 } },
    { type: 'otp', label: 'OTP / PIN Code', icon: '🔑', category: 'advanced', desc: 'Segmented verification passcode cells', default: { col: 6, length: 6 } },
    { type: 'tags', label: 'Tags Input', icon: '🏷️', category: 'advanced', desc: 'Tokenized chips / keyword tags input', default: { col: 12, placeholder: 'Add tag...' } },
    { type: 'rating', label: 'Star Rating', icon: '⭐', category: 'advanced', desc: 'Interactive 5 or 10 star rating bar', default: { col: 6, max: 5 } },

    // Rich Content & Dynamic
    { type: 'code', label: 'Code Editor', icon: '💻', category: 'rich', desc: 'Syntax-highlighted code editor', default: { col: 12, language: 'php' } },
    { type: 'editor', label: 'Rich Text (WYSIWYG)', icon: '📝', category: 'rich', desc: 'Formatted HTML rich content editor', default: { col: 12 } },
    { type: 'repeater', label: 'Dynamic Repeater', icon: '🔁', category: 'rich', desc: 'Repeatable dynamic row collection', default: { col: 12 } },

    // Enterprise & Regional Identifiers
    { type: 'pan', label: 'PAN Card', icon: '🪪', category: 'identity', desc: '10-character Indian PAN card', default: { col: 6, placeholder: 'ABCDE1234F', validation: 'pan' } },
    { type: 'aadhaar', label: 'Aadhaar Card', icon: '🆔', category: 'identity', desc: '12-digit Indian Aadhaar number', default: { col: 6, placeholder: '123456789012', validation: 'aadhaar' } },
    { type: 'gstin', label: 'GSTIN Tax ID', icon: '🧾', category: 'identity', desc: '15-character GSTIN business ID', default: { col: 6, placeholder: '22AAAAA0000A1Z5', validation: 'gstin' } },
    { type: 'ifsc', label: 'IFSC Code', icon: '🏦', category: 'identity', desc: '11-character Indian Bank IFSC code', default: { col: 6, placeholder: 'SBIN0001234', validation: 'ifsc' } },
    { type: 'pincode', label: 'PIN Code', icon: '📍', category: 'identity', desc: '6-digit Indian Postal PIN Code', default: { col: 4, placeholder: '560001', validation: 'pincode' } }
];

// Quick Starter Presets for Zero-Code Forms
const FORM_PRESETS = {
    blank: {
        name: 'new_form',
        public_name: 'New Form',
        method: 'post',
        fields: []
    },
    contact: {
        name: 'contact_form',
        public_name: 'Contact & Feedback',
        method: 'post',
        honeypot: true,
        submit_label: 'Send Message',
        fields: [
            { name: 'full_name', type: 'text', label: 'Full Name', placeholder: 'John Doe', col: 6, required: true, validation: 'min:2' },
            { name: 'email', type: 'email', label: 'Email Address', placeholder: 'john@example.com', col: 6, required: true, validation: 'email' },
            { name: 'subject', type: 'text', label: 'Subject', placeholder: 'How can we help?', col: 12, required: true },
            { name: 'message', type: 'textarea', label: 'Message', placeholder: 'Type your message...', col: 12, required: true, validation: 'min:10' }
        ]
    },
    registration: {
        name: 'user_registration',
        public_name: 'User Registration',
        method: 'post',
        honeypot: true,
        submit_label: 'Register Account',
        fields: [
            { name: 'username', type: 'text', label: 'Username', placeholder: 'johndoe', col: 6, required: true, validation: 'alphanumeric|min:3' },
            { name: 'email', type: 'email', label: 'Email Address', placeholder: 'user@example.com', col: 6, required: true, validation: 'email' },
            { name: 'password', type: 'password', label: 'Password', placeholder: '••••••••', col: 6, required: true, validation: 'min:8' },
            { name: 'phone', type: 'phone', label: 'Phone Number', placeholder: '+1 234 567 8900', col: 6 },
            { name: 'role', type: 'select', label: 'Account Role', col: 12, options: { student: 'Student', faculty: 'Faculty Member', staff: 'Staff' } },
            { name: 'terms', type: 'checkbox', label: 'I accept terms and conditions', col: 12, required: true }
        ]
    },
    calculation: {
        name: 'order_calculation',
        public_name: 'Order Calculator',
        method: 'post',
        submit_label: 'Confirm Order',
        fields: [
            { name: 'item_name', type: 'text', label: 'Item Description', col: 6, required: true },
            { name: 'unit_price', type: 'number', label: 'Unit Price ($)', placeholder: '100', col: 6, required: true },
            { name: 'quantity', type: 'number', label: 'Quantity', placeholder: '1', col: 4, required: true },
            { name: 'discount_pct', type: 'range', label: 'Discount (%)', col: 4, min: 0, max: 50, step: 5, value: 10 },
            { name: 'total_amount', type: 'text', label: 'Total Payable ($)', col: 4, computed: 'unit_price * quantity * (1 - discount_pct / 100)', help: 'Calculated in real-time' },
            { name: 'signature', type: 'signature', label: 'Authorized Signature', col: 12 }
        ]
    },
    workflow: {
        name: 'service_request',
        public_name: 'Workflow Service Request',
        method: 'post',
        workflow_actions: true,
        submit_label: 'Submit for Review',
        fields: [
            { name: 'title', type: 'text', label: 'Request Title', col: 12, required: true },
            { name: 'category', type: 'select', label: 'Category', col: 6, options: { urgent: 'Urgent Issue', general: 'General Request' } },
            { name: 'priority', type: 'radio', label: 'Priority', col: 6, options: { low: 'Low', normal: 'Normal', high: 'High' } },
            { name: 'attachment', type: 'file', label: 'Supporting Documents', col: 12, accept: '.pdf,.png,.jpg' },
            { name: 'notes', type: 'editor', label: 'Detailed Description', col: 12 }
        ]
    }
};

export default class FormsView extends BaseComponent {
    async onInit() {
        const hash = location.hash || '';
        let initialTab = 'forms';
        if (this.props?.tab) {
            initialTab = this.props.tab;
        } else if (hash.includes('tab=')) {
            const match = hash.match(/tab=([a-zA-Z0-9_-]+)/);
            if (match && match[1]) initialTab = match[1];
        } else if (window.location.search && window.location.search.includes('tab=')) {
            const match = window.location.search.match(/tab=([a-zA-Z0-9_-]+)/);
            if (match && match[1]) initialTab = match[1];
        } else if (hash.includes('#spplang') || hash.includes('/spplang')) {
            initialTab = 'spplang';
        } else if (hash.includes('#mobile') || hash.includes('/mobile')) {
            initialTab = 'mobile';
        } else if (hash.includes('#validators') || hash.includes('/validators')) {
            initialTab = 'validators';
        }

        this.state = {
            activeMainTab: initialTab,
            loading: (initialTab === 'forms'),
            forms: [],
            searchTerm: '',
            validators: { built_in: [], app_rules: [], app_classes: [] },
            validatorsLoading: false,
            validatorSearch: '',
            validatorCategory: 'all',
            paletteCategory: 'all',
            paletteSearch: '',
            activeFormTab: 'builder', // 'builder' | 'source' | 'preview'
            previewDevice: 'desktop', // 'desktop' | 'tablet' | 'mobile'
            previewLoading: false,
            previewHtml: '',
            previewError: '',
            currentFormName: '',
            currentFormType: 'yml',
            currentFormSource: '',
            currentFormConfig: {
                id: '',
                name: '',
                public_name: '',
                method: 'post',
                action: '',
                entity: '',
                submit_label: 'Submit',
                honeypot: false,
                workflow_actions: false,
                offline: false,
                telemetry: false,
                autosave: false,
                fields: []
            }
        };

        this._contextListener = () => {
            if (this.state.activeMainTab === 'forms') {
                this.fetchData().then(() => { if (typeof this.update === 'function') this.update(); });
            } else if (this.state.activeMainTab === 'validators') {
                this.fetchValidators().then(() => { if (typeof this.update === 'function') this.update(); });
            }
        };
        if (typeof window !== 'undefined') {
            window.addEventListener('spp:context-change', this._contextListener);
        }

        if (initialTab === 'forms') {
            await this.fetchData();
        } else if (initialTab === 'validators') {
            await this.fetchValidators();
        }
    }

    async onActivate(params = {}) {
        const tab = params?.tab || (this.props && this.props.tab);
        if (tab && tab !== this.state.activeMainTab) {
            await this.switchMainTab(tab);
        } else if (this.state.activeMainTab === 'forms') {
            await this.fetchData();
            if (typeof this.update === 'function') this.update();
        }
    }

    onDestroy() {
        if (this._contextListener && typeof window !== 'undefined') {
            window.removeEventListener('spp:context-change', this._contextListener);
            this._contextListener = null;
        }
    }

    async switchMainTab(tab) {
        if (this.state.activeMainTab === tab) return;
        this.setState({ activeMainTab: tab });
        if (tab === 'forms' && this.state.forms.length === 0) {
            await this.fetchData();
        } else if (tab === 'validators') {
            await this.fetchValidators();
        }
        if (typeof this.update === 'function') this.update();
    }

    async fetchValidators() {
        this.setState({ validatorsLoading: true });
        try {
            const app = this.selectedApp;
            const res = await this.api('list_validators', { appname: app, context: app });
            if (res.success && res.data) {
                this.setState({
                    validators: res.data,
                    validatorsLoading: false
                });
            } else {
                this.setState({ validatorsLoading: false });
            }
        } catch (err) {
            console.error('Fetch validators failed:', err);
            this.setState({ validatorsLoading: false });
        }
        if (typeof this.update === 'function') this.update();
    }

    async fetchData() {
        try {
            const app = this.selectedApp;
            const res = await this.api('list_forms', { appname: app, context: app });
            if (res.success) {
                const forms = (res.data.forms || []).filter(f => !f.name?.startsWith('__'));
                this.setState({
                    forms: forms,
                    loading: false
                });
                this.existingFormNames = forms.map(f => f.name);
            } else {
                throw new Error(res.message);
            }
        } catch (err) {
            this.setState({ loading: false, error: err.message });
        }
    }

    render() {
        const { loading, forms, error, activeMainTab } = this.state;

        // Update Header
        const headerActions = document.getElementById('header-actions');
        if (headerActions) {
            if (activeMainTab === 'forms') {
                const headerHtml = html`
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="button" class="btn secondary-btn btn-sm" @click=${() => this.openGenerateFromEntityModal()} title="Synthesize Form YAML directly from Model or Database Table">⚡ From Entity</button>
                        <button type="button" class="btn primary-btn btn-sm" @click=${() => this.openEditor('', 'yml', '', 'builder')}>+ New Form</button>
                    </div>
                `;
                headerHtml.render(headerActions);
            } else if (activeMainTab === 'validators') {
                const headerHtml = html`
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="button" class="btn primary-btn btn-sm" @click=${() => this.openNewRuleModal()}>⚡ + New Custom Rule</button>
                    </div>
                `;
                headerHtml.render(headerActions);
            } else {
                headerActions.innerHTML = '';
            }
        }

        return html`
            <div class="forms-workspace" style="display: flex; flex-direction: column; height: 100%;">
                <!-- Sub-Navigation Toolbar -->
                <div class="suite-tabs" style="margin-bottom: 1.25rem;">
                    <button type="button" class="suite-tab-btn ${activeMainTab === 'forms' ? 'active' : ''}" 
                            @click=${() => this.switchMainTab('forms')}>
                        <span>📝</span> Form Designer & Manifests
                    </button>
                    <button type="button" class="suite-tab-btn ${activeMainTab === 'spplang' ? 'active' : ''}" 
                            @click=${() => this.switchMainTab('spplang')}>
                        <span>💬</span> Translations & Locales (SPPLang)
                    </button>
                    <button type="button" class="suite-tab-btn ${activeMainTab === 'mobile' ? 'active' : ''}" 
                            @click=${() => this.switchMainTab('mobile')}>
                        <span>📱</span> Mobile & Responsive Preview
                    </button>
                    <button type="button" class="suite-tab-btn ${activeMainTab === 'validators' ? 'active' : ''}" 
                            @click=${() => this.switchMainTab('validators')}>
                        <span>🛡️</span> Custom Validators & Rule Studio
                    </button>
                </div>

                <div class="forms-content" style="flex: 1;">
                    ${activeMainTab === 'spplang' ? this.renderSpplang() : ''}
                    ${activeMainTab === 'mobile' ? this.renderMobile() : ''}
                    ${activeMainTab === 'validators' ? this.renderValidatorsStudio() : ''}
                    ${activeMainTab === 'forms' ? this.renderFormsMain() : ''}
                </div>
            </div>
        `;
    }

    renderSpplang() {
        setTimeout(async () => {
            const mount = document.getElementById('forms-subview-mount');
            if (mount) {
                if (!this.spplangInstance) {
                    try {
                        const mod = await import('./spplang.js');
                        const SpplangView = mod.default;
                        const appObj = this.app || this.admin;
                        this.spplangInstance = new SpplangView(appObj, mount, { app: appObj?.selectedApp });
                        if (this.spplangInstance.onInit) await this.spplangInstance.onInit();
                        if (typeof this.spplangInstance.update === 'function') await this.spplangInstance.update();
                        else if (typeof this.spplangInstance.render === 'function') {
                            const r = this.spplangInstance.render();
                            if (r) mount.appendChild(r instanceof Node ? r : r);
                        }
                    } catch (e) {
                        console.error('Failed to load spplang:', e);
                        mount.innerHTML = `<div class="alert error" style="margin: 1.5rem;">Failed to load Translations: ${e.message}</div>`;
                    }
                } else {
                    this.spplangInstance.container = mount;
                    if (typeof this.spplangInstance.update === 'function') await this.spplangInstance.update();
                }
            }
        }, 10);
        return html`
            <div id="forms-subview-mount" class="fade-in" style="min-height: 500px;">
                <div class="loading-state" style="padding: 2rem; text-align: center;"><div class="sppux-spinner"></div> Loading Translations...</div>
            </div>
        `;
    }

    renderMobile() {
        setTimeout(async () => {
            const mount = document.getElementById('forms-subview-mount');
            if (mount) {
                if (!this.mobileInstance) {
                    try {
                        const mod = await import('./mobile.js');
                        const MobileView = mod.default;
                        const appObj = this.app || this.admin;
                        this.mobileInstance = new MobileView(appObj, mount, { app: appObj?.selectedApp });
                        if (this.mobileInstance.onInit) await this.mobileInstance.onInit();
                        if (typeof this.mobileInstance.update === 'function') await this.mobileInstance.update();
                        else if (typeof this.mobileInstance.render === 'function') {
                            const r = this.mobileInstance.render();
                            if (r) mount.appendChild(r instanceof Node ? r : r);
                        }
                    } catch (e) {
                        console.error('Failed to load mobile studio:', e);
                        mount.innerHTML = `<div class="alert error" style="margin: 1.5rem;">Failed to load Mobile Studio: ${e.message}</div>`;
                    }
                } else {
                    this.mobileInstance.container = mount;
                    if (typeof this.mobileInstance.update === 'function') await this.mobileInstance.update();
                }
            }
        }, 10);
        return html`
            <div id="forms-subview-mount" class="fade-in" style="min-height: 500px;">
                <div class="loading-state" style="padding: 2rem; text-align: center;"><div class="sppux-spinner"></div> Loading Mobile Studio...</div>
            </div>
        `;
    }

    renderValidatorsStudio() {
        const { validatorsLoading, validators, validatorSearch, validatorCategory } = this.state;

        if (validatorsLoading) {
            return html`
                <div class="loading-state" style="padding: 3rem; text-align: center;">
                    <div class="sppux-spinner"></div>
                    <p style="margin-top: 1rem; color: var(--text-dim);">Loading validator catalog and app rules...</p>
                </div>
            `;
        }

        const builtInList = validators?.built_in || [];
        const appRules = validators?.app_rules || [];
        const appClasses = validators?.app_classes || [];
        const totalCount = builtInList.length + appRules.length;

        // Filter
        const term = (validatorSearch || '').toLowerCase();
        const filteredBuiltIn = builtInList.filter(r => {
            if (validatorCategory && validatorCategory !== 'all' && r.category !== validatorCategory) return false;
            if (!term) return true;
            return (r.alias || '').toLowerCase().includes(term) || (r.label || '').toLowerCase().includes(term) || (r.description || '').toLowerCase().includes(term);
        });

        const filteredAppRules = appRules.filter(r => {
            if (validatorCategory && validatorCategory !== 'all' && validatorCategory !== 'app') return false;
            if (!term) return true;
            return (r.alias || '').toLowerCase().includes(term) || (r.message || '').toLowerCase().includes(term) || (r.pattern || '').toLowerCase().includes(term);
        });

        return html`
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <!-- Header Banner -->
                <div class="glass-panel" style="padding: 1.25rem 1.5rem; border-radius: 10px; border: 1px solid var(--glass-border); background: rgba(0,0,0,0.2); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h3 style="margin: 0 0 4px 0; display: flex; align-items: center; gap: 8px; font-size: 1.25rem; color: var(--text-bright);">
                            <span>🛡️</span> Custom Validators & Rule Studio
                        </h3>
                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-dim);">
                            Zero-divergence validation engine synchronized across Server (PHP), Browser HTML5, SPP-UX synthetic events, and LiveComponents.
                        </p>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.fetchValidators()} title="Reload validator catalog">🔄 Refresh</button>
                        <button type="button" class="btn primary-btn btn-sm" @click=${() => this.openNewRuleModal()} title="Scaffold new custom rule or regex pattern">⚡ + New Custom Rule</button>
                    </div>
                </div>

                <!-- Metrics Strip -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                    <div class="metric-card" style="padding: 12px 16px; background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border); border-radius: 8px;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-dim);">Total Registered</span>
                        <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary, #3b82f6);">${totalCount}</div>
                    </div>
                    <div class="metric-card" style="padding: 12px 16px; background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border); border-radius: 8px;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-dim);">Built-in IDs & Rules</span>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;">${builtInList.length}</div>
                    </div>
                    <div class="metric-card" style="padding: 12px 16px; background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border); border-radius: 8px;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-dim);">App Rules (validators.yml)</span>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;">${appRules.length}</div>
                    </div>
                    <div class="metric-card" style="padding: 12px 16px; background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border); border-radius: 8px;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-dim);">PHP Rule Classes</span>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #8b5cf6;">${appClasses.length}</div>
                    </div>
                </div>

                <!-- Filter & Search Toolbar -->
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div style="display: flex; gap: 8px; flex: 1; max-width: 450px;">
                        <input type="text" 
                               placeholder="Search rules by alias, name, or pattern..." 
                               value="${validatorSearch || ''}"
                               @input=${(e) => this.setState({ validatorSearch: e.target.value })}
                               style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--glass-border); background: var(--input-bg); color: var(--text-bright);">
                    </div>
                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-sm ${(!validatorCategory || validatorCategory === 'all') ? 'primary-btn' : 'ghost-btn'}" @click=${() => this.setState({ validatorCategory: 'all' })}>All</button>
                        <button type="button" class="btn btn-sm ${validatorCategory === 'identity' ? 'primary-btn' : 'ghost-btn'}" @click=${() => this.setState({ validatorCategory: 'identity' })}>🇮🇳 Identity</button>
                        <button type="button" class="btn btn-sm ${validatorCategory === 'financial' ? 'primary-btn' : 'ghost-btn'}" @click=${() => this.setState({ validatorCategory: 'financial' })}>🏦 Financial</button>
                        <button type="button" class="btn btn-sm ${validatorCategory === 'security' ? 'primary-btn' : 'ghost-btn'}" @click=${() => this.setState({ validatorCategory: 'security' })}>🔐 Security</button>
                        <button type="button" class="btn btn-sm ${validatorCategory === 'app' ? 'primary-btn' : 'ghost-btn'}" @click=${() => this.setState({ validatorCategory: 'app' })}>✨ App Rules</button>
                    </div>
                </div>

                <!-- SECTION 1: Application Custom Rules (if any) -->
                ${filteredAppRules.length > 0 ? html`
                    <div>
                        <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 6px; color: #f59e0b; font-size: 1rem;">
                            <span>✨</span> Application Custom Rules (<code>etc/apps/${this.selectedApp}/validators.yml</code>)
                        </h4>
                        <div class="card-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));">
                            ${filteredAppRules.map(r => this.renderRuleCard(r, true))}
                        </div>
                    </div>
                ` : ''}

                <!-- SECTION 2: Built-in Enterprise & Regional Rules -->
                <div>
                    <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 6px; color: var(--primary, #3b82f6); font-size: 1rem;">
                        <span>🛡️</span> Built-in Enterprise & Regional Validators (Ready-to-use)
                    </h4>
                    <div class="card-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));">
                        ${filteredBuiltIn.map(r => this.renderRuleCard(r, false))}
                    </div>
                </div>
            </div>
        `;
    }

    renderRuleCard(r, isAppRule = false) {
        const alias = r.alias;
        const testId = `rule-test-${alias}`;
        const feedbackId = `rule-feedback-${alias}`;

        return html`
            <div class="resource-card" style="padding: 16px; border: 1px solid var(--glass-border); border-radius: 8px; background: var(--panel-bg); display: flex; flex-direction: column; justify-content: space-between; gap: 12px;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <div>
                            <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-bright); display: flex; align-items: center; gap: 6px;">
                                <span>${r.badge || '🏷️'}</span>
                                <span>${r.label || r.alias}</span>
                            </div>
                            <code style="font-size: 0.75rem; color: var(--primary); background: rgba(59, 130, 246, 0.1); padding: 1px 6px; border-radius: 4px;">${r.alias}</code>
                        </div>
                        <span class="badge tiny ${isAppRule ? 'warning' : 'success'}">${isAppRule ? 'App Custom' : 'Built-in'}</span>
                    </div>

                    <p style="font-size: 0.8rem; color: var(--text-dim); margin: 0 0 10px 0; line-height: 1.4;">
                        ${r.description || r.message || 'Validation rule'}
                    </p>

                    ${r.pattern ? html`
                        <div style="font-size: 0.75rem; margin-bottom: 6px;">
                            <span style="color: var(--text-dim);">Regex:</span>
                            <code style="display: block; overflow-x: auto; font-size: 0.72rem; padding: 4px 6px; background: rgba(0,0,0,0.3); border-radius: 4px; margin-top: 2px;">${r.pattern}</code>
                        </div>
                    ` : ''}

                    ${r.class ? html`
                        <div style="font-size: 0.75rem; margin-bottom: 6px;">
                            <span style="color: var(--text-dim);">PHP Class:</span>
                            <code style="display: block; overflow-x: auto; font-size: 0.72rem; padding: 4px 6px; background: rgba(0,0,0,0.3); border-radius: 4px; margin-top: 2px;">${r.class}</code>
                        </div>
                    ` : ''}

                    <!-- Live Regex Interactive Tester -->
                    <div style="margin-top: 10px; background: rgba(0,0,0,0.15); border: 1px solid var(--glass-border); border-radius: 6px; padding: 8px;">
                        <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--text-dim); margin-bottom: 4px;">
                            ⚡ Live Rule Tester
                        </div>
                        <input type="text" 
                               id="${testId}"
                               placeholder="Type sample input to test..." 
                               @input=${(e) => this.runInteractiveTest(r, e.target.value, feedbackId)}
                               style="width: 100%; font-size: 0.8rem; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--glass-border); background: var(--input-bg); color: var(--text-bright);">
                        <div id="${feedbackId}" style="margin-top: 4px; font-size: 0.72rem; min-height: 18px;">
                            <span style="color: var(--text-dim);">Enter value to test format</span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--glass-border); padding-top: 8px; margin-top: 4px;">
                    <span style="font-size: 0.7rem; color: var(--text-dim);">
                        ${r.minlength || r.maxlength ? `Length: ${r.minlength || 0} - ${r.maxlength || '∞'}` : 'Sync: PHP & HTML5'}
                    </span>
                    <div style="display: flex; gap: 6px;">
                        ${(r.class_file || r.class) ? html`
                            <button type="button" class="btn ghost-btn btn-sm" style="padding: 2px 8px; font-size: 0.75rem; color: #3b82f6;" @click=${() => this.openRuleInCoder(r.class_file || r.class)} title="Open in Code Studio">💻 Code</button>
                        ` : ''}
                        ${isAppRule ? html`
                            <button type="button" class="btn ghost-btn btn-sm" style="padding: 2px 8px; font-size: 0.75rem;" @click=${() => this.openNewRuleModal(r)}>Edit</button>
                            <button type="button" class="btn ghost-btn btn-sm" style="padding: 2px 8px; font-size: 0.75rem; color: #ef4444;" @click=${() => this.deleteRule(r.alias)}>Delete</button>
                        ` : html`
                            <span style="font-size: 0.72rem; color: #10b981;">✔ Fully Built-in</span>
                        `}
                    </div>
                </div>
            </div>
        `;
    }

    openRuleInCoder(classFileOrName) {
        if (!classFileOrName) return;
        let file = classFileOrName;
        if (file.includes('\\') || file.startsWith('App')) {
            const parts = file.replace(/^\\+/, '').split(/\\|\//);
            if (parts.length >= 3 && parts[0] === 'App') {
                file = `src/${parts[1]}/${parts.slice(2).join('/')}.php`;
            }
        }
        location.hash = `coder?file=${encodeURIComponent(file)}`;
    }

    runInteractiveTest(rule, value, feedbackId) {
        const el = document.getElementById(feedbackId);
        if (!el) return;
        if (!value || value.trim() === '') {
            el.innerHTML = '<span style="color: var(--text-dim);">Enter value to test format</span>';
            return;
        }

        const patternStr = rule.pattern;
        if (patternStr) {
            try {
                const clean = patternStr.startsWith('/') && patternStr.endsWith('/') ? patternStr.slice(1, -1) : patternStr;
                const reg = new RegExp(clean);
                const passes = reg.test(value);
                if (passes) {
                    el.innerHTML = '<span style="color: #10b981; font-weight: 600;">✔ Valid format matches rule</span>';
                } else {
                    const msg = (rule.message || 'Invalid format').replace(':attribute', rule.label || rule.alias);
                    el.innerHTML = `<span style="color: #ef4444; font-weight: 500;">❌ ${msg}</span>`;
                }
            } catch (err) {
                el.innerHTML = `<span style="color: #f59e0b;">Regex parse error: ${err.message}</span>`;
            }
        } else {
            el.innerHTML = '<span style="color: var(--text-dim);">PHP class validated on submit</span>';
        }
    }

    openSubEditor(title, content, data = {}, onSave = null) {
        if (typeof SPPUX !== 'undefined' && typeof SPPUX.openSubEditor === 'function') {
            return SPPUX.openSubEditor(title, content, data, onSave);
        }
        if (this.admin && typeof this.admin.openSubEditor === 'function') {
            return this.admin.openSubEditor(title, content, data, onSave);
        }
        if (typeof window !== 'undefined' && window.admin && typeof window.admin.openSubEditor === 'function') {
            return window.admin.openSubEditor(title, content, data, onSave);
        }
        console.warn('SPPUX.openSubEditor not available');
    }

    updateSubEditor(content) {
        if (typeof SPPUX !== 'undefined' && typeof SPPUX.updateSubEditor === 'function') {
            return SPPUX.updateSubEditor(content);
        }
        console.warn('SPPUX.updateSubEditor not available');
    }

    openNewRuleModal(existingRule = null) {
        const isEdit = !!existingRule;
        const currentAlias = existingRule?.alias || '';
        const currentPattern = existingRule?.pattern || '';
        const currentMsg = existingRule?.message || '';
        const currentMin = existingRule?.minlength || '';
        const currentMax = existingRule?.maxlength || '';
        const currentClass = existingRule?.class || '';

        const modalContent = html`
            <div style="display: flex; flex-direction: column; gap: 12px; min-width: 440px;">
                <div class="input-group">
                    <label style="font-weight: 600; font-size: 0.85rem;">Rule Alias (Identifier)</label>
                    <input type="text" name="rule_alias" value="${currentAlias}" ?disabled="${isEdit}" placeholder="e.g. employee_id, us_ssn, passport" style="width: 100%;">
                    <small style="color: var(--text-dim);">Lower-case identifier used in forms, e.g. <code>validation: "required|${currentAlias || 'employee_id'}"</code></small>
                </div>

                <div class="input-group">
                    <label style="font-weight: 600; font-size: 0.85rem;">Regular Expression Pattern</label>
                    <input type="text" name="rule_pattern" value="${currentPattern}" placeholder="e.g. ^[A-Z]{3}-[0-9]{4}$" style="width: 100%; font-family: monospace;">
                    <small style="color: var(--text-dim);">Universal Regex pattern evaluated identically in PHP preg_match and browser HTML5 pattern.</small>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="input-group">
                        <label style="font-size: 0.8rem;">Min Length</label>
                        <input type="number" name="rule_minlength" value="${currentMin}" placeholder="Optional">
                    </div>
                    <div class="input-group">
                        <label style="font-size: 0.8rem;">Max Length</label>
                        <input type="number" name="rule_maxlength" value="${currentMax}" placeholder="Optional">
                    </div>
                </div>

                <div class="input-group">
                    <label style="font-weight: 600; font-size: 0.85rem;">Custom Error Message</label>
                    <input type="text" name="rule_message" value="${currentMsg}" placeholder="e.g. The :attribute must be a valid 3-letter, 4-digit code." style="width: 100%;">
                    <small style="color: var(--text-dim);">Use <code>:attribute</code> placeholder for the field name.</small>
                </div>

                <div class="input-group">
                    <label style="font-weight: 600; font-size: 0.85rem;">Custom PHP Class Name (Optional)</label>
                    <input type="text" name="rule_class" value="${currentClass}" placeholder="e.g. App\\${this.selectedApp}\\Rules\\EmployeeIdRule" style="width: 100%;">
                </div>

                ${!isEdit ? html`
                    <div style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 6px; padding: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; cursor: pointer;">
                            <input type="checkbox" name="rule_scaffold_class" checked>
                            <span>⚡ Automatically scaffold PHP Rule class in <code>src/${this.selectedApp}/Rules/</code></span>
                        </label>
                        <small style="display: block; margin-top: 4px; color: var(--text-dim);">Equivalent to running <code>php spp.php make:rule</code> via CLI.</small>
                    </div>
                ` : ''}
            </div>
        `;

        this.openSubEditor(isEdit ? `Edit Rule: ${currentAlias}` : 'Scaffold Custom Validator Rule', modalContent, {}, async (result) => {
            const alias = (result.rule_alias || currentAlias || '').trim().toLowerCase();
            const pattern = (result.rule_pattern || '').trim();
            const message = (result.rule_message || '').trim();
            const ruleClass = (result.rule_class || '').trim();
            const minlength = result.rule_minlength;
            const maxlength = result.rule_maxlength;
            const createClass = !!result.rule_scaffold_class;

            if (!alias) {
                this.notify('Rule alias is required.', 'error');
                return;
            }
            if (!pattern && !ruleClass) {
                this.notify('Either a pattern or class name is required.', 'error');
                return;
            }

            try {
                const fd = new FormData();
                fd.append('action', 'save_validator');
                fd.append('appname', this.selectedApp);
                fd.append('alias', alias);
                if (pattern) fd.append('pattern', pattern);
                if (message) fd.append('message', message);
                if (ruleClass) fd.append('rule_class', ruleClass);
                if (minlength) fd.append('minlength', minlength);
                if (maxlength) fd.append('maxlength', maxlength);
                if (createClass) fd.append('create_class', '1');

                const res = await this.apiPost(fd);
                if (res.success) {
                    this.notify(`Rule '${alias}' saved successfully!`, 'success');
                    await this.fetchValidators();
                    if (typeof this.update === 'function') this.update();

                    const classFile = res.class_file || (res.data && res.data.class_file);
                    if (classFile) {
                        let targetFile = classFile;
                        if (targetFile.includes('\\') || targetFile.startsWith('App')) {
                            const parts = targetFile.replace(/^\\+/, '').split(/\\|\//);
                            if (parts.length >= 3 && parts[0] === 'App') {
                                targetFile = `src/${parts[1]}/${parts.slice(2).join('/')}.php`;
                            }
                        }
                        this.notify(`Opening Rule class '${targetFile}' in Code Studio...`, 'info');
                        setTimeout(() => {
                            location.hash = `coder?file=${encodeURIComponent(targetFile)}`;
                        }, 400);
                    }
                } else {
                    this.notify(res.message || 'Failed to save validator.', 'error');
                }
            } catch (err) {
                this.notify('Error saving validator: ' + err.message, 'error');
            }
        });
    }

    async deleteRule(alias) {
        if (!confirm(`Are you sure you want to delete validator rule '${alias}' from validators.yml?`)) {
            return;
        }
        try {
            const fd = new FormData();
            fd.append('action', 'delete_validator');
            fd.append('appname', this.selectedApp);
            fd.append('alias', alias);
            const res = await this.apiPost(fd);
            if (res.success) {
                this.notify(`Validator '${alias}' deleted.`, 'success');
                await this.fetchValidators();
                if (typeof this.update === 'function') this.update();
            } else {
                this.notify(res.message || 'Failed to delete validator.', 'error');
            }
        } catch (err) {
            this.notify('Error deleting validator: ' + err.message, 'error');
        }
    }

    async openGenerateFromEntityModal() {
        let entityOptions = [];
        try {
            const app = this.selectedApp;
            const res = await this.api('list_entities', { appname: app, context: app });
            if (res && res.success && res.data && res.data.entities) {
                entityOptions = res.data.entities.map(e => typeof e === 'string' ? e : (e.name || e.id)).filter(Boolean);
            }
        } catch (e) {
            console.warn('Could not load entity options for form generator', e);
        }

        const modalContent = html`
            <div style="display: flex; flex-direction: column; gap: 14px; min-width: 460px;">
                <div style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 6px; padding: 12px;">
                    <div style="display: flex; align-items: center; gap: 8px; font-weight: 600; color: var(--primary);">
                        <span>⚡ 1-Click Form Synthesis</span>
                    </div>
                    <p style="margin: 4px 0 0; font-size: 0.82rem; color: var(--text-dim); line-height: 1.4;">
                        Synthesize a production-ready declarative YAML Form manifest directly from an <code>SPPEntity</code> model or database table schema. Automatically maps columns, data types, lengths, required fields, and intelligent validation rules (including <code>unique</code> and <code>exists</code>).
                    </p>
                </div>

                <div class="input-group">
                    <label style="font-weight: 600; font-size: 0.85rem;">Entity Model or Database Table</label>
                    ${entityOptions.length > 0 ? html`
                        <div style="display: flex; gap: 8px;">
                            <input type="text" name="gen_entity" list="available-entities-list" placeholder="e.g. User, Student, or table name" style="flex: 1;">
                            <datalist id="available-entities-list">
                                ${entityOptions.map(ent => html`<option value="${ent}"></option>`)}
                            </datalist>
                        </div>
                    ` : html`
                        <input type="text" name="gen_entity" placeholder="e.g. User, Student, or table name" style="width: 100%;">
                    `}
                    <small style="color: var(--text-dim);">Enter the entity class name (e.g. <code>Student</code>) or raw database table name.</small>
                </div>

                <div class="input-group">
                    <label style="font-weight: 600; font-size: 0.85rem;">Form Name / Manifest Identifier (Optional)</label>
                    <input type="text" name="gen_form_name" placeholder="e.g. student_profile (defaults to entity name)" style="width: 100%;">
                    <small style="color: var(--text-dim);">The YAML manifest will be saved to <code>etc/apps/${this.selectedApp}/forms/{name}.yml</code>.</small>
                </div>
            </div>
        `;

        this.openSubEditor('⚡ Synthesize Form from Entity', modalContent, {}, async (result) => {
            const entity = (result.gen_entity || '').trim();
            const formName = (result.gen_form_name || '').trim();

            if (!entity) {
                this.notify('Please specify an entity or database table name.', 'error');
                return;
            }

            try {
                this.notify(`Synthesizing Form YAML from '${entity}'...`, 'info');
                const fd = new FormData();
                fd.append('action', 'generate_from_entity');
                fd.append('appname', this.selectedApp);
                fd.append('entity', entity);
                if (formName) fd.append('form_name', formName);

                const res = await this.apiPost(fd);
                if (res && res.success) {
                    this.notify(res.message || `Form successfully synthesized!`, 'success');
                    await this.fetchData();
                    const createdName = res.data?.form_name || res.form_name || formName || entity.toLowerCase();
                    const formItem = this.state.forms.find(f => f.name.toLowerCase() === createdName.toLowerCase());
                    if (formItem) {
                        this.openEditor(formItem.name, formItem.type, formItem.content, 'builder');
                    }
                } else {
                    this.notify(res?.message || 'Failed to synthesize form.', 'error');
                }
            } catch (err) {
                this.notify('Error synthesizing form: ' + err.message, 'error');
            }
        });
    }

    renderFormsMain() {
        const { loading, forms, error, searchTerm } = this.state;

        if (loading) return html`<div class="loading-state">Syncing form manifests...</div>`;
        if (error) return html`<div class="empty-state"><h3>Error</h3><p>${error}</p></div>`;

        const filteredForms = forms.filter(f => {
            if (!searchTerm) return true;
            return (f.name || '').toLowerCase().includes(searchTerm.toLowerCase());
        });

        if (forms.length === 0) {
            return html`
                <div class="empty-state" style="padding: 3rem 2rem; max-width: 700px; margin: 0 auto; text-align: center;">
                    <div class="empty-icon" style="font-size: 3.5rem; margin-bottom: 1rem;">📝</div>
                    <h3 style="font-size: 1.8rem; margin-bottom: 0.5rem; color: var(--primary);">Declarative Zero-Code Forms</h3>
                    <p style="color: var(--text-dim); margin-bottom: 1.5rem; line-height: 1.6;">
                        Visually build, configure, and manage forms with complete zero-code YAML manifests. 
                        Drag and drop from over 25+ rich element types, attach reactive modifiers (<code>depends_on</code>, <code>computed</code>, <code>col</code>), and configure enterprise workflow action bars.
                    </p>
                    <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-bottom: 1.5rem;">
                        <button type="button" class="btn primary-btn" @click=${() => this.openEditorWithPreset('blank')}>+ Blank Form</button>
                        <button type="button" class="btn secondary-btn" @click=${() => this.openGenerateFromEntityModal()} title="Synthesize Form YAML directly from Model or Database Table">⚡ From Model/Entity</button>
                        <button type="button" class="btn ghost-btn" @click=${() => this.openEditorWithPreset('contact')}>📧 Contact Form</button>
                        <button type="button" class="btn ghost-btn" @click=${() => this.openEditorWithPreset('registration')}>👤 User Registration</button>
                        <button type="button" class="btn ghost-btn" @click=${() => this.openEditorWithPreset('calculation')}>🧮 Calculator Form</button>
                        <button type="button" class="btn ghost-btn" @click=${() => this.openEditorWithPreset('workflow')}>🔄 Workflow Form</button>
                    </div>
                </div>
            `;
        }

        return html`
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <!-- Filter Bar & Quick Actions -->
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 8px; flex: 1; max-width: 400px;">
                        <input type="text" 
                               placeholder="Search forms by name..." 
                               value="${searchTerm || ''}"
                               @input=${(e) => this.setState({ searchTerm: e.target.value })}
                               style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--glass-border); background: var(--input-bg); color: var(--text-bright);">
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <span style="font-size: 0.8rem; color: var(--text-dim);">${filteredForms.length} ${filteredForms.length === 1 ? 'manifest' : 'manifests'}</span>
                        <div class="dropdown" style="display: inline-flex; gap: 8px;">
                            <button type="button" class="btn secondary-btn btn-sm" @click=${() => this.openGenerateFromEntityModal()} title="Synthesize Form YAML directly from Model or Database Table">⚡ From Entity</button>
                            <button type="button" class="btn primary-btn btn-sm" @click=${() => this.openEditor('', 'yml', '', 'builder')}>+ New Form</button>
                        </div>
                    </div>
                </div>

                <!-- Cards Grid -->
                <div class="card-grid">
                    ${filteredForms.map((form, i) => {
                        const formName = form.name || 'unnamed_form';
                        const rawType = form.type || 'yml';
                        const formType = String(rawType).toUpperCase();
                        const typeBadgeClass = String(rawType).toLowerCase();
                        const lineCount = (String(form.content || '').match(/\n/g) || []).length + 1;
                        const modifiedStr = form.modified || 'Just now';
                        const sizeStr = form.size ? Math.round(form.size / 1024 * 100) / 100 + ' KB' : '';
                        
                        // Detect features from content
                        const content = String(form.content || '');
                        const hasHoneypot = content.includes('honeypot: true') || content.includes('honeypot:');
                        const hasWorkflow = content.includes('workflow_actions: true');
                        const hasComputed = content.includes('computed:');
                        const hasDepends = content.includes('depends_on:');

                        return html`
                            <div class="item-card" style="animation-delay: ${i * 0.04}s">
                                <div class="card-header">
                                    <div>
                                        <h3 style="margin-bottom: 4px;">${formName}</h3>
                                        <div class="card-meta">${lineCount} lines · ${modifiedStr}</div>
                                    </div>
                                    <span class="type-badge ${typeBadgeClass}">${formType}</span>
                                </div>

                                <div style="display: flex; gap: 4px; flex-wrap: wrap; margin: 8px 0;">
                                    ${hasHoneypot ? html`<span class="badge tiny success" title="Honeypot Anti-Spam Active">🛡️ Honeypot</span>` : ''}
                                    ${hasWorkflow ? html`<span class="badge tiny info" title="Workflow Action Bar Active">🔄 Workflow</span>` : ''}
                                    ${hasComputed ? html`<span class="badge tiny warning" title="Computed Reactive Fields">🧮 Computed</span>` : ''}
                                    ${hasDepends ? html`<span class="badge tiny info" title="Conditional Field Rules">🔗 Reactive</span>` : ''}
                                </div>

                                <div class="card-footer">
                                    <small>${sizeStr}</small>
                                    <div class="card-actions" style="display: flex; gap: 6px;">
                                        <button type="button" class="btn primary-btn btn-sm" @click=${() => this.openEditor(formName, rawType, form.content || '', 'builder')} title="Open Visual Drag & Drop Builder">🎨 Builder</button>
                                        <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.openEditor(formName, rawType, form.content || '', 'source')} title="Edit YAML Manifest Source">YAML</button>
                                        <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.openEditor(formName, rawType, form.content || '', 'preview')} title="Live Framework Preview">👁️ Preview</button>
                                        <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.cloneForm(formName, form.content || '')} title="Duplicate Form">📋</button>
                                        <button type="button" class="btn danger-btn btn-sm" @click=${() => this.confirmDelete('form', formName)} title="Delete Form">✕</button>
                                    </div>
                                </div>
                            </div>
                        `;
                    })}
                </div>
            </div>
        `;
    }

    // =============================================
    // FORM BUILDER & EDITOR ENGINE
    // =============================================

    async openEditor(name, type, content, initialTab = 'builder') {
        this.state.activeFormTab = initialTab || 'builder';
        this.state.currentFormName = name || '';
        this.state.currentFormType = type || 'yml';
        this.state.previewDevice = 'desktop';
        this.state.previewLoading = false;
        this.state.previewHtml = '';
        this.state.previewError = '';

        if (!content && name) {
            const found = (this.state.forms || []).find(f => f.name === name);
            if (found && found.content) {
                content = found.content;
            }
        }
        this.state.currentFormSource = content || '';
        
        const isNew = !name;
        const defaultName = isNew ? this._getNextAvailableName('my_form', this.existingFormNames || []) : name;
        let config = { 
            id: defaultName,
            name: defaultName, 
            public_name: '',
            method: 'post', 
            action: '',
            submit_label: 'Submit',
            honeypot: false,
            workflow_actions: false,
            offline: false,
            telemetry: false,
            autosave: false,
            fields: []
        };

        if (content) {
            const fd = new FormData();
            fd.append('action', 'parse_form_yaml');
            fd.append('yaml', content);
            try {
                const res = await this.apiPost(fd, {}, { lock: false });
                if (res.success && res.data?.config) {
                    config = Object.assign(config, this._normalizeConfig(res.data.config));
                }
            } catch (e) {
                console.error("YAML parse failure:", e);
            }
        }

        this.state.currentFormConfig = this._normalizeConfig(config);
        if (!this.state.currentFormSource) {
            this.state.currentFormSource = this._serializeToYaml(this.state.currentFormConfig);
        }

        const safeType = String(type || 'yml').toLowerCase();
        this.openModal(name ? `Form: ${name}.${safeType}` : 'Create New Form', this.getModalHtml(), [
            { label: 'Save Form', type: 'primary', fn: () => this.save() }
        ]);

        if (initialTab === 'preview') {
            await this.loadPreview();
        }
    }

    openEditorWithPreset(presetKey) {
        const preset = FORM_PRESETS[presetKey] || FORM_PRESETS.blank;
        const name = this._getNextAvailableName(preset.name, this.existingFormNames || []);
        const config = JSON.parse(JSON.stringify(preset));
        config.name = name;
        config.id = name;
        const yaml = this._serializeToYaml(config);
        this.openEditor('', 'yml', yaml, 'builder');
    }

    getModalHtml() {
        const { activeFormTab } = this.state;
        return html`
            <div style="display: flex; flex-direction: column; height: 100%; min-height: 560px;">
                <!-- Main Modal Toolbar -->
                <div class="tab-bar" style="margin-bottom: 1rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem; display: flex; gap: 8px;">
                    <button type="button" class="tab-btn ${activeFormTab === 'builder' ? 'active' : ''}" @click=${() => this.switchTab('builder')}>
                        🎨 Visual Builder
                    </button>
                    <button type="button" class="tab-btn ${activeFormTab === 'source' ? 'active' : ''}" @click=${() => this.switchTab('source')}>
                        📝 Source (YAML)
                    </button>
                    <button type="button" class="tab-btn ${activeFormTab === 'preview' ? 'active' : ''}" @click=${() => this.switchTab('preview')}>
                        👁️ Live Preview
                    </button>
                </div>

                <!-- Tab Content Area -->
                <div id="form-editor-content" class="tab-content active" style="flex: 1; display: flex; flex-direction: column;">
                    ${this.getTabContent(activeFormTab)}
                </div>
            </div>
        `;
    }

    getTabContent(tab) {
        if (tab === 'builder') return this.getBuilderHtml();
        if (tab === 'source') return this.getSourceHtml();
        if (tab === 'preview') return this.getPreviewHtml();
        return '';
    }

    async switchTab(tab) {
        const prevTab = this.state.activeFormTab;
        if (prevTab === tab) return;
        this.state.activeFormTab = tab;

        if (prevTab === 'source') {
            const sourceEl = document.getElementById('editor-content');
            const source = sourceEl?.value || this.state.currentFormSource;
            this.state.currentFormSource = source;
            if (tab === 'builder') {
                await this.syncSourceToBuilder(source);
            }
        } else if (prevTab === 'builder') {
            this.state.currentFormSource = this._serializeToYaml(this.state.currentFormConfig);
        }

        if (tab === 'source' && !this.state.currentFormSource) {
            this.state.currentFormSource = this._serializeToYaml(this.state.currentFormConfig);
        }

        this.refreshModal();

        if (tab === 'preview') {
            await this.loadPreview();
        }
    }

    refreshModal() {
        const curType = String(this.state.currentFormType || 'yml').toLowerCase();
        const title = this.state.currentFormName ? `Form: ${this.state.currentFormName}.${curType}` : 'Create New Form';
        this.updateModal(title, this.getModalHtml(), [
            { label: 'Save Form', type: 'primary', fn: () => this.save() }
        ]);
    }

    // =============================================
    // VISUAL BUILDER CANVAS & PALETTE
    // =============================================

    getBuilderHtml() {
        const c = this.state.currentFormConfig;
        const { paletteCategory, paletteSearch } = this.state;

        // Filter palette elements
        const filteredElements = ELEMENT_TYPES.filter(el => {
            if (paletteCategory !== 'all' && el.category !== paletteCategory) return false;
            if (paletteSearch) {
                const q = paletteSearch.toLowerCase();
                return el.label.toLowerCase().includes(q) || el.type.toLowerCase().includes(q) || el.desc.toLowerCase().includes(q);
            }
            return true;
        });

        return html`
            <div class="builder-layout" style="display: flex; gap: 1rem; height: 560px; overflow: hidden;">
                <!-- 1. LEFT PANE: Form Settings & Security -->
                <div class="builder-sidebar glass-panel" style="width: 250px; flex-shrink: 0; padding: 1rem; overflow-y: auto; display: flex; flex-direction: column; gap: 0.9rem;">
                    <h4 style="margin: 0; font-size: 0.8rem; color: var(--primary); text-transform: uppercase; letter-spacing: 0.05rem;">Form Settings</h4>
                    
                    <div class="input-group">
                        <label title="Unique identifier for this form in code and templates (e.g. contact_form)">Form ID / Name ℹ️</label>
                        <input type="text" 
                               value="${c.name || ''}" 
                               @input=${(e) => { c.name = e.target.value; c.id = e.target.value; }}
                               placeholder="e.g. contact_form"
                               style="width: 100%;">
                    </div>

                    <div class="input-group">
                        <label title="Public title displayed in form headers">Public Title</label>
                        <input type="text" 
                               value="${c.public_name || ''}" 
                               @input=${(e) => { c.public_name = e.target.value; }}
                               placeholder="e.g. Contact & Inquiries"
                               style="width: 100%;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <div class="input-group">
                            <label>Method</label>
                            <select @change=${(e) => { c.method = e.target.value; }} style="width: 100%;">
                                <option value="post" ?selected="${c.method === 'post'}">POST</option>
                                <option value="get" ?selected="${c.method === 'get'}">GET</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Submit Label</label>
                            <input type="text" 
                                   value="${c.submit_label || 'Submit'}" 
                                   @input=${(e) => { c.submit_label = e.target.value; }}
                                   style="width: 100%;">
                        </div>
                    </div>

                    <div class="input-group">
                        <label title="Submission endpoint URL or route (defaults to current endpoint)">Action URL</label>
                        <input type="text" 
                               value="${c.action || ''}" 
                               @input=${(e) => { c.action = e.target.value; }}
                               placeholder="e.g. /contact/submit"
                               style="width: 100%;">
                    </div>

                    <hr style="margin: 6px 0; border: none; border-top: 1px solid var(--glass-border);">

                    <h4 style="margin: 0; font-size: 0.8rem; color: var(--primary); text-transform: uppercase; letter-spacing: 0.05rem;">Security & Enterprise</h4>
                    
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label title="Invisible honeypot anti-spam protection that traps automated bots without annoying captchas." style="display: flex; align-items: center; gap: 8px; font-size: 0.75rem; cursor: pointer;">
                            <input type="checkbox" ?checked="${!!c.honeypot}" @change=${(e) => { c.honeypot = e.target.checked; }}>
                            <span>🛡️ Honeypot Protection</span>
                        </label>

                        <label title="Renders an automatic lifecycle transition button bar for entities governed by SPP State Machine workflows." style="display: flex; align-items: center; gap: 8px; font-size: 0.75rem; cursor: pointer;">
                            <input type="checkbox" ?checked="${!!c.workflow_actions}" @change=${(e) => { c.workflow_actions = e.target.checked; }}>
                            <span>🔄 Workflow Action Bar</span>
                        </label>

                        <label title="Submissions are queued locally during internet loss and automatically synched on reconnection." style="display: flex; align-items: center; gap: 8px; font-size: 0.75rem; cursor: pointer;">
                            <input type="checkbox" ?checked="${!!c.offline}" @change=${(e) => { c.offline = e.target.checked; }}>
                            <span>📡 Offline Sync</span>
                        </label>

                        <label title="Tracks engagement duration, field drop-offs, and completion telemetry." style="display: flex; align-items: center; gap: 8px; font-size: 0.75rem; cursor: pointer;">
                            <input type="checkbox" ?checked="${!!c.telemetry}" @change=${(e) => { c.telemetry = e.target.checked; }}>
                            <span>📊 Telemetry Tracking</span>
                        </label>

                        <label title="Saves form drafts automatically to local storage as users type." style="display: flex; align-items: center; gap: 8px; font-size: 0.75rem; cursor: pointer;">
                            <input type="checkbox" ?checked="${!!c.autosave}" @change=${(e) => { c.autosave = e.target.checked; }}>
                            <span>💾 Local Auto-save</span>
                        </label>
                    </div>
                </div>

                <!-- 2. MIDDLE PANE: Form Drag & Drop Canvas -->
                <div class="builder-main glass-panel" 
                     style="flex: 1; padding: 1rem; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; background: rgba(0,0,0,0.15);"
                     @dragover=${(e) => e.preventDefault()}
                     @drop=${(e) => this.onCanvasDrop(e)}>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--glass-border); padding-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <h4 style="margin: 0; font-size: 0.95rem;">Form Canvas</h4>
                            <span class="badge tiny info">${(c.fields || []).length} ${(c.fields || []).length === 1 ? 'element' : 'elements'}</span>
                        </div>
                        <div style="display: flex; gap: 6px;">
                            <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.clearCanvas()} title="Remove all fields">Clear Canvas</button>
                        </div>
                    </div>

                    ${(c.fields || []).length === 0 ? html`
                        <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 2px dashed var(--glass-border); border-radius: 8px; padding: 2rem; color: var(--text-dim); text-align: center;">
                            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📥</div>
                            <h4 style="margin-bottom: 0.25rem;">Empty Form Canvas</h4>
                            <p style="font-size: 0.85rem; max-width: 320px; margin-bottom: 1rem;">Click any element in the palette on the right, or drag elements here to build your form.</p>
                            <button type="button" class="btn primary-btn btn-sm" @click=${() => this.addField('text')}>+ Add First Field</button>
                        </div>
                    ` : html`
                        <div class="field-list" style="display: flex; flex-direction: column; gap: 8px;">
                            ${(c.fields || []).map((f, i) => {
                                const elDef = ELEMENT_TYPES.find(e => e.type === f.type) || { icon: '🔤', label: f.type };
                                return html`
                                    <div class="field-item draggable" 
                                         draggable="true" 
                                         data-index="${i}"
                                         @dragstart=${(e) => this.onFieldDragStart(e, i)}
                                         @dragover=${(e) => this.onFieldDragOver(e)}
                                         @dragleave=${(e) => this.onFieldDragLeave(e)}
                                         @drop=${(e) => this.onFieldDrop(e, i)}
                                         style="background: var(--input-bg); border: 1px solid var(--glass-border); border-radius: 8px; padding: 10px 12px; display: flex; align-items: center; justify-content: space-between; gap: 10px; cursor: grab; min-height: auto;">
                                        
                                        <div style="display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0;">
                                            <div style="color: var(--text-dim); cursor: grab; font-size: 1.1rem; user-select: none;">⋮⋮</div>
                                            <div style="font-size: 1.3rem;">${elDef.icon}</div>
                                            <div style="min-width: 0; flex: 1;">
                                                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                                    <strong style="font-size: 0.9rem; color: var(--text-bright);">${f.name}</strong>
                                                    <span class="badge tiny" style="background: rgba(255,255,255,0.08);">${f.type}</span>
                                                    ${f.col ? html`<span class="badge tiny" title="Grid column width">col: ${f.col}</span>` : ''}
                                                    ${f.required ? html`<span class="badge tiny danger">* Required</span>` : ''}
                                                    ${f.depends_on ? html`<span class="badge tiny info" title="Conditional: depends on another field">🔗 depends_on</span>` : ''}
                                                    ${f.computed ? html`<span class="badge tiny success" title="Computed formula">🧮 computed</span>` : ''}
                                                    ${f.validation ? html`<span class="badge tiny warning" title="Validation: ${f.validation}">✓ rules</span>` : ''}
                                                </div>
                                                <div style="font-size: 0.75rem; color: var(--text-dim); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px;">
                                                    ${f.label ? `Label: "${f.label}"` : '(No label)'} 
                                                    ${f.placeholder ? `· Placeholder: "${f.placeholder}"` : ''}
                                                </div>
                                            </div>
                                        </div>

                                        <div style="display: flex; gap: 4px; align-items: center; flex-shrink: 0;">
                                            <!-- Column Width Quick Switcher -->
                                            <div style="display: flex; border: 1px solid var(--glass-border); border-radius: 4px; overflow: hidden; margin-right: 4px;">
                                                <button type="button" class="btn btn-sm" style="padding: 2px 6px; font-size: 0.65rem; background: ${f.col === 6 ? 'var(--primary)' : 'transparent'};" @click=${() => this.setFieldCol(i, 6)} title="50% width (col: 6)">½</button>
                                                <button type="button" class="btn btn-sm" style="padding: 2px 6px; font-size: 0.65rem; background: ${f.col === 12 || !f.col ? 'var(--primary)' : 'transparent'};" @click=${() => this.setFieldCol(i, 12)} title="100% width (col: 12)">1/1</button>
                                            </div>
                                            <button type="button" class="btn btn-icon" @click=${() => this.openFieldInspector(i)} title="Configure field properties">⚙️</button>
                                            <button type="button" class="btn btn-icon" @click=${() => this.duplicateField(i)} title="Duplicate field">📋</button>
                                            <button type="button" class="btn btn-icon danger" @click=${() => this.removeField(i)} title="Remove field">✕</button>
                                        </div>
                                    </div>
                                `;
                            })}
                        </div>
                    `}
                </div>

                <!-- 3. RIGHT PANE: 25+ Element Types Palette -->
                <div class="builder-sidebar glass-panel" style="width: 260px; flex-shrink: 0; padding: 1rem; overflow-y: auto; display: flex; flex-direction: column; gap: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h4 style="margin: 0; font-size: 0.8rem; color: var(--primary); text-transform: uppercase; letter-spacing: 0.05rem;">Element Palette</h4>
                        <span style="font-size: 0.7rem; color: var(--text-dim);">${filteredElements.length} elements</span>
                    </div>

                    <!-- Palette Search -->
                    <input type="text" 
                           placeholder="Filter palette..." 
                           value="${paletteSearch || ''}"
                           @input=${(e) => this.setState({ paletteSearch: e.target.value })}
                           style="width: 100%; padding: 6px 10px; font-size: 0.75rem; border-radius: 6px; border: 1px solid var(--glass-border); background: var(--input-bg); color: var(--text-bright);">

                    <!-- Category Pills -->
                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                        ${['all', 'standard', 'identity', 'selection', 'advanced', 'rich'].map(cat => html`
                            <button type="button" 
                                    class="btn btn-sm"
                                    style="padding: 2px 6px; font-size: 0.65rem; text-transform: capitalize; border-radius: 4px; background: ${paletteCategory === cat ? 'var(--primary)' : 'transparent'}; border: 1px solid ${paletteCategory === cat ? 'var(--primary)' : 'var(--glass-border)'};"
                                    @click=${() => this.setState({ paletteCategory: cat })}>
                                ${cat}
                            </button>
                        `)}
                    </div>

                    <!-- Element Buttons -->
                    <div style="display: flex; flex-direction: column; gap: 4px; margin-top: 4px;">
                        ${filteredElements.map(el => html`
                            <div class="palette-item"
                                 draggable="true"
                                 @dragstart=${(e) => this.onPaletteDragStart(e, el.type)}
                                 @click=${() => this.addField(el.type)}
                                 style="display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 6px; border: 1px solid var(--glass-border); background: var(--input-bg); cursor: pointer; transition: all 0.15s ease;"
                                 onmouseover="this.style.borderColor='var(--primary)'; this.style.transform='translateX(2px)';"
                                 onmouseout="this.style.borderColor='var(--glass-border)'; this.style.transform='none';">
                                <span style="font-size: 1.1rem;">${el.icon}</span>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-bright);">${el.label}</div>
                                    <div style="font-size: 0.65rem; color: var(--text-dim); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${el.desc}</div>
                                </div>
                                <span style="font-size: 0.75rem; color: var(--primary); font-weight: bold;">+</span>
                            </div>
                        `)}
                    </div>
                </div>
            </div>
        `;
    }

    // =============================================
    // FIELD INSPECTOR SUB-EDITOR MODAL
    // =============================================

    async openFieldInspector(idx) {
        const c = this.state.currentFormConfig;
        const field = c.fields[idx];
        if (!field) return;

        if (!this.state.validators || !this.state.validators.built_in || this.state.validators.built_in.length === 0) {
            await this.fetchValidators();
        }

        // Clone current field for isolated editing
        const f = JSON.parse(JSON.stringify(field));
        let activeTab = 'general';

        const renderInspector = () => {
            const hasOptions = ['select', 'multiselect', 'radio', 'checkbox'].includes(f.type);
            const isFile = f.type === 'file';
            const isNumeric = ['number', 'range'].includes(f.type);
            const isCode = f.type === 'code';
            const isRating = f.type === 'rating';
            const isOtp = f.type === 'otp';

            // Options string representation
            let optionsText = '';
            if (f.options) {
                if (typeof f.options === 'object' && !Array.isArray(f.options)) {
                    optionsText = Object.entries(f.options).map(([k, v]) => `${k}: ${v}`).join('\n');
                } else if (Array.isArray(f.options)) {
                    optionsText = f.options.map(opt => typeof opt === 'object' ? `${Object.keys(opt)[0]}: ${Object.values(opt)[0]}` : String(opt)).join('\n');
                }
            }

            return html`
                <div style="display: flex; flex-direction: column; height: 100%; gap: 12px;">
                    <!-- Inspector Tabs -->
                    <div style="display: flex; gap: 8px; border-bottom: 1px solid var(--glass-border); padding-bottom: 8px;">
                        <button type="button" class="btn btn-sm ${activeTab === 'general' ? 'primary-btn' : 'ghost-btn'}" @click=${() => { activeTab = 'general'; SPPUX.updateSubEditor(renderInspector()); }}>
                            🏷️ Properties
                        </button>
                        <button type="button" class="btn btn-sm ${activeTab === 'options' ? 'primary-btn' : 'ghost-btn'}" @click=${() => { activeTab = 'options'; SPPUX.updateSubEditor(renderInspector()); }}>
                            ⚙️ Element Options
                        </button>
                        <button type="button" class="btn btn-sm ${activeTab === 'reactive' ? 'primary-btn' : 'ghost-btn'}" @click=${() => { activeTab = 'reactive'; SPPUX.updateSubEditor(renderInspector()); }}>
                            ⚡ Reactive Modifiers
                        </button>
                        <button type="button" class="btn btn-sm ${activeTab === 'validation' ? 'primary-btn' : 'ghost-btn'}" @click=${() => { activeTab = 'validation'; SPPUX.updateSubEditor(renderInspector()); }}>
                            🛡️ Validation Rules
                        </button>
                    </div>

                    <!-- TAB 1: General Properties -->
                    ${activeTab === 'general' ? html`
                        <div style="display: flex; flex-direction: column; gap: 12px; padding: 4px 0;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div class="input-group">
                                    <label>Field Name / Key *</label>
                                    <input type="text" name="name" value="${f.name || ''}" placeholder="e.g. email_address" required>
                                </div>
                                <div class="input-group">
                                    <label>Element Type</label>
                                    <select name="type" @change=${(e) => { f.type = e.target.value; SPPUX.updateSubEditor(renderInspector()); }}>
                                        ${ELEMENT_TYPES.map(t => html`
                                            <option value="${t.type}" ?selected="${f.type === t.type}">${t.icon} ${t.label} (${t.type})</option>
                                        `)}
                                    </select>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div class="input-group">
                                    <label>Label</label>
                                    <input type="text" name="label" value="${f.label || ''}" placeholder="User-facing label">
                                </div>
                                <div class="input-group">
                                    <label>Placeholder</label>
                                    <input type="text" name="placeholder" value="${f.placeholder || ''}" placeholder="Placeholder text">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px;">
                                <div class="input-group">
                                    <label>Help Text / Hint</label>
                                    <input type="text" name="help" value="${f.help || ''}" placeholder="Instructional hint displayed below field">
                                </div>
                                <div class="input-group">
                                    <label>Grid Width (col: 1-12)</label>
                                    <select name="col">
                                        <option value="12" ?selected="${f.col === 12 || !f.col}">12 (100% Full Width)</option>
                                        <option value="6" ?selected="${f.col === 6}">6 (50% Half Width)</option>
                                        <option value="4" ?selected="${f.col === 4}">4 (33% One-Third)</option>
                                        <option value="3" ?selected="${f.col === 3}">3 (25% One-Quarter)</option>
                                        <option value="8" ?selected="${f.col === 8}">8 (66% Two-Thirds)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="input-group">
                                <label>Default / Initial Value</label>
                                <input type="text" name="value" value="${f.value || ''}" placeholder="Initial default value">
                            </div>
                        </div>
                    ` : ''}

                    <!-- TAB 2: Element Options -->
                    ${activeTab === 'options' ? html`
                        <div style="display: flex; flex-direction: column; gap: 12px; padding: 4px 0;">
                            ${hasOptions ? html`
                                <div class="input-group">
                                    <label>Choice Options (Format: <code>key: Label</code>, one per line)</label>
                                    <textarea name="options_text" rows="5" placeholder="student: Student\nteacher: Teacher\nparent: Parent" style="font-family: monospace;">${optionsText}</textarea>
                                </div>
                                <div class="input-group">
                                    <label>Dynamic Data Source (Model Class or Service)</label>
                                    <input type="text" name="datasource" value="${f.datasource || ''}" placeholder="e.g. App\\Models\\Department::all()">
                                </div>
                            ` : ''}

                            ${isFile ? html`
                                <div class="input-group">
                                    <label>Accepted MIME / File Extensions</label>
                                    <input type="text" name="accept" value="${f.accept || '*/*'}" placeholder="e.g. image/*, .pdf, .docx">
                                </div>
                            ` : ''}

                            ${isNumeric ? html`
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                                    <div class="input-group">
                                        <label>Minimum Value</label>
                                        <input type="number" name="min" value="${f.min !== null && f.min !== undefined ? f.min : ''}">
                                    </div>
                                    <div class="input-group">
                                        <label>Maximum Value</label>
                                        <input type="number" name="max" value="${f.max !== null && f.max !== undefined ? f.max : ''}">
                                    </div>
                                    <div class="input-group">
                                        <label>Step Increment</label>
                                        <input type="number" name="step" value="${f.step !== null && f.step !== undefined ? f.step : 1}">
                                    </div>
                                </div>
                            ` : ''}

                            ${isRating ? html`
                                <div class="input-group">
                                    <label>Max Stars</label>
                                    <input type="number" name="rating_max" value="${f.max || 5}" min="3" max="10">
                                </div>
                            ` : ''}

                            ${isOtp ? html`
                                <div class="input-group">
                                    <label>Passcode Digits Length</label>
                                    <input type="number" name="length" value="${f.length || 6}" min="4" max="8">
                                </div>
                            ` : ''}

                            ${isCode ? html`
                                <div class="input-group">
                                    <label>Code Language</label>
                                    <select name="language">
                                        <option value="php" ?selected="${f.language === 'php'}">PHP</option>
                                        <option value="javascript" ?selected="${f.language === 'javascript'}">JavaScript</option>
                                        <option value="json" ?selected="${f.language === 'json'}">JSON</option>
                                        <option value="sql" ?selected="${f.language === 'sql'}">SQL</option>
                                        <option value="html" ?selected="${f.language === 'html'}">HTML</option>
                                    </select>
                                </div>
                            ` : ''}

                            ${!hasOptions && !isFile && !isNumeric && !isRating && !isOtp && !isCode ? html`
                                <div style="padding: 2rem; text-align: center; color: var(--text-dim);">
                                    No custom options required for element type <code>${f.type}</code>.
                                </div>
                            ` : ''}
                        </div>
                    ` : ''}

                    <!-- TAB 3: Reactive Modifiers (depends_on & computed) -->
                    ${activeTab === 'reactive' ? html`
                        <div style="display: flex; flex-direction: column; gap: 12px; padding: 4px 0;">
                            <div class="input-group">
                                <label title="Show or hide this field depending on another field value">
                                    🔗 Conditional Visibility (<code>depends_on</code>) ℹ️
                                </label>
                                <input type="text" 
                                       name="depends_on_field" 
                                       value="${typeof f.depends_on === 'object' ? Object.keys(f.depends_on || {})[0] || '' : (f.depends_on || '')}" 
                                       placeholder="Target field name (e.g. role)">
                                <input type="text" 
                                       name="depends_on_value" 
                                       value="${typeof f.depends_on === 'object' ? Object.values(f.depends_on || {})[0] || '' : ''}" 
                                       placeholder="Required value (e.g. teacher)"
                                       style="margin-top: 6px;">
                                <small style="color: var(--text-dim); margin-top: 4px;">Shows this field only when the target field matches the required value.</small>
                            </div>

                            <hr style="margin: 4px 0; border: none; border-top: 1px solid var(--glass-border);">

                            <div class="input-group">
                                <label title="Automatically calculate value using formula referencing other fields">
                                    🧮 Dynamic Calculation (<code>computed</code>) ℹ️
                                </label>
                                <input type="text" 
                                       name="computed" 
                                       value="${f.computed || ''}" 
                                       placeholder="e.g. unit_price * quantity * (1 - discount_pct / 100)">
                                <small style="color: var(--text-dim); margin-top: 4px;">Mathematical or string expression evaluated live in browser and server.</small>
                            </div>
                        </div>
                    ` : ''}

                    <!-- TAB 4: Validation Rules -->
                    ${activeTab === 'validation' ? html`
                        <div style="display: flex; flex-direction: column; gap: 12px; padding: 4px 0;">
                            <!-- Standard Validations -->
                            <div>
                                <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--text-dim); margin-bottom: 6px;">
                                    Standard HTML5 / Framework Rules
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; cursor: pointer;">
                                        <input type="checkbox" name="val_required" ?checked="${!!f.required || (f.validation || '').split('|').includes('required')}">
                                        <span>* Required field</span>
                                    </label>

                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; cursor: pointer;">
                                        <input type="checkbox" name="val_email" ?checked="${(f.validation || '').split('|').includes('email')}">
                                        <span>📧 Valid Email</span>
                                    </label>

                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; cursor: pointer;">
                                        <input type="checkbox" name="val_numeric" ?checked="${(f.validation || '').split('|').includes('numeric')}">
                                        <span>🔢 Numeric value</span>
                                    </label>

                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; cursor: pointer;">
                                        <input type="checkbox" name="val_url" ?checked="${(f.validation || '').split('|').includes('url')}">
                                        <span>🌐 Valid URL</span>
                                    </label>

                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; cursor: pointer;">
                                        <input type="checkbox" name="val_alpha" ?checked="${(f.validation || '').split('|').includes('alpha')}">
                                        <span>🔤 Alphabetic letters</span>
                                    </label>

                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; cursor: pointer;">
                                        <input type="checkbox" name="val_alphanumeric" ?checked="${(f.validation || '').split('|').includes('alphanumeric')}">
                                        <span>🔣 Alphanumeric</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Built-in Enterprise & Regional IDs -->
                            <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 6px; padding: 10px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--primary, #3b82f6); display: flex; align-items: center; gap: 4px;">
                                        <span>🛡️</span> Built-in Enterprise & Regional IDs
                                    </div>
                                    <span style="font-size: 0.68rem; color: var(--text-dim);">Server & Browser sync</span>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; cursor: pointer;" title="Permanent Account Number (10 alphanumeric)">
                                        <input type="checkbox" name="val_pan" ?checked="${(f.validation || '').split('|').includes('pan')}">
                                        <span>🇮🇳 <b>PAN Card</b></span>
                                    </label>

                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; cursor: pointer;" title="UIDAI Aadhaar Number (12 digits)">
                                        <input type="checkbox" name="val_aadhaar" ?checked="${(f.validation || '').split('|').includes('aadhaar')}">
                                        <span>🇮🇳 <b>Aadhaar Card</b></span>
                                    </label>

                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; cursor: pointer;" title="GST Identification Number (15 chars)">
                                        <input type="checkbox" name="val_gstin" ?checked="${(f.validation || '').split('|').includes('gstin')}">
                                        <span>🧾 <b>GSTIN Tax ID</b></span>
                                    </label>

                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; cursor: pointer;" title="Indian Financial System Code (11 alphanumeric)">
                                        <input type="checkbox" name="val_ifsc" ?checked="${(f.validation || '').split('|').includes('ifsc')}">
                                        <span>🏦 <b>IFSC Code</b></span>
                                    </label>

                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; cursor: pointer;" title="Postal PIN Code (6 digits)">
                                        <input type="checkbox" name="val_pincode" ?checked="${(f.validation || '').split('|').includes('pincode')}">
                                        <span>📍 <b>PIN Code</b></span>
                                    </label>

                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; cursor: pointer;" title="Indian Mobile Number (10 digits, starts 6-9)">
                                        <input type="checkbox" name="val_indmobile" ?checked="${(f.validation || '').split('|').includes('indmobile')}">
                                        <span>📱 <b>Indian Mobile</b></span>
                                    </label>

                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; cursor: pointer;" title="Strong Password (min 8 chars, mixed case, number, symbol)">
                                        <input type="checkbox" name="val_passwordstrength" ?checked="${(f.validation || '').split('|').includes('passwordstrength')}">
                                        <span>🔐 <b>Strong Password</b></span>
                                    </label>

                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; cursor: pointer;" title="Credit Card Number (13-19 digits)">
                                        <input type="checkbox" name="val_creditcard" ?checked="${(f.validation || '').split('|').includes('creditcard')}">
                                        <span>💳 <b>Credit Card</b></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Application Custom Rules (validators.yml) -->
                            ${(this.state.validators?.app_rules || []).length > 0 ? html`
                                <div style="background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 6px; padding: 10px;">
                                    <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #10b981; margin-bottom: 6px;">
                                        ✨ Application Custom Rules (validators.yml)
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                        ${(this.state.validators.app_rules).map(r => html`
                                            <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; cursor: pointer;" title="${r.message || r.pattern || r.class}">
                                                <input type="checkbox" name="val_app_${r.alias}" ?checked="${(f.validation || '').split('|').includes(r.alias)}">
                                                <span>🏷️ <b>${r.alias}</b></span>
                                            </label>
                                        `)}
                                    </div>
                                </div>
                            ` : ''}

                            <!-- Numeric / String Constraints -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div class="input-group">
                                    <label style="font-size: 0.8rem;">Min (length or value)</label>
                                    <input type="number" name="val_constraint_min" value="${f.min !== null && f.min !== undefined ? f.min : ''}" placeholder="e.g. 3">
                                </div>
                                <div class="input-group">
                                    <label style="font-size: 0.8rem;">Max (length or value)</label>
                                    <input type="number" name="val_constraint_max" value="${f.max !== null && f.max !== undefined ? f.max : ''}" placeholder="e.g. 50">
                                </div>
                            </div>

                            <div class="input-group" style="margin-top: 2px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                    <label style="font-size: 0.8rem;">Validation Pipeline (Pipe-delimited string)</label>
                                    <button type="button" class="btn ghost-btn btn-sm" style="padding: 2px 8px; font-size: 0.72rem;" @click=${() => this.openNewRuleModal()}>
                                        ⚡ + New Custom Rule
                                    </button>
                                </div>
                                <input type="text" name="validation_custom" value="${f.validation || ''}" placeholder="e.g. required|min:3|max:100|pan">
                                <small style="color: var(--text-dim); margin-top: 2px; font-size: 0.72rem;">Any registered alias (e.g. <code>pan</code>, <code>ifsc</code>, or app rules) is enforced across PHP and browser.</small>
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
        };

        this.openSubEditor(`Edit Field: ${field.name}`, renderInspector(), {}, (result) => {
            // Apply updated values
            if (result.name) field.name = result.name.trim();
            if (result.type) field.type = result.type;
            if (result.label !== undefined) field.label = result.label;
            if (result.placeholder !== undefined) field.placeholder = result.placeholder;
            if (result.help !== undefined) field.help = result.help;
            if (result.value !== undefined) field.value = result.value;
            if (result.col !== undefined) field.col = parseInt(result.col) || 12;

            // Options
            if (result.options_text !== undefined) {
                const lines = result.options_text.split('\n').filter(l => l.trim().length > 0);
                const optsObj = {};
                lines.forEach(line => {
                    const parts = line.split(':');
                    if (parts.length >= 2) {
                        optsObj[parts[0].trim()] = parts.slice(1).join(':').trim();
                    } else {
                        optsObj[line.trim()] = line.trim();
                    }
                });
                field.options = Object.keys(optsObj).length > 0 ? optsObj : null;
            }
            if (result.datasource !== undefined) field.datasource = result.datasource.trim() || null;
            if (result.accept !== undefined) field.accept = result.accept.trim() || null;
            if (result.min !== undefined && result.min !== '') field.min = parseFloat(result.min);
            if (result.max !== undefined && result.max !== '') field.max = parseFloat(result.max);
            if (result.step !== undefined && result.step !== '') field.step = parseFloat(result.step);
            if (result.length !== undefined && result.length !== '') field.length = parseInt(result.length);
            if (result.language !== undefined) field.language = result.language;

            // Reactive
            if (result.depends_on_field) {
                const targetField = result.depends_on_field.trim();
                const targetVal = (result.depends_on_value || '').trim();
                field.depends_on = targetVal ? { [targetField]: targetVal } : targetField;
            } else {
                field.depends_on = null;
            }
            if (result.computed !== undefined) field.computed = result.computed.trim() || null;

            // Validation
            field.required = !!result.val_required;
            let valRules = [];
            if (result.validation_custom) {
                valRules = result.validation_custom.split('|').map(r => r.trim()).filter(Boolean);
            }
            if (result.val_required && !valRules.includes('required')) valRules.unshift('required');
            if (result.val_email && !valRules.includes('email')) valRules.push('email');
            if (result.val_numeric && !valRules.includes('numeric')) valRules.push('numeric');
            if (result.val_url && !valRules.includes('url')) valRules.push('url');
            if (result.val_alpha && !valRules.includes('alpha')) valRules.push('alpha');
            if (result.val_alphanumeric && !valRules.includes('alphanumeric')) valRules.push('alphanumeric');

            // Built-in Enterprise & Regional IDs
            const enterpriseKeys = ['pan', 'aadhaar', 'gstin', 'ifsc', 'pincode', 'indmobile', 'passwordstrength', 'creditcard'];
            enterpriseKeys.forEach(k => {
                if (result[`val_${k}`] && !valRules.includes(k)) {
                    valRules.push(k);
                } else if (result[`val_${k}`] === false) {
                    valRules = valRules.filter(r => r !== k);
                }
            });

            // App Custom Rules
            if (this.state.validators?.app_rules) {
                this.state.validators.app_rules.forEach(r => {
                    const k = r.alias;
                    if (result[`val_app_${k}`] && !valRules.includes(k)) {
                        valRules.push(k);
                    } else if (result[`val_app_${k}`] === false) {
                        valRules = valRules.filter(item => item !== k);
                    }
                });
            }

            // Constraints
            if (result.val_constraint_min !== undefined && result.val_constraint_min !== '') {
                field.min = parseFloat(result.val_constraint_min);
            }
            if (result.val_constraint_max !== undefined && result.val_constraint_max !== '') {
                field.max = parseFloat(result.val_constraint_max);
            }

            field.validation = valRules.join('|');

            this.refreshModal();
        });
    }

    addField(type, atIndex = null) {
        const c = this.state.currentFormConfig;
        if (!c.fields) c.fields = [];

        const elDef = ELEMENT_TYPES.find(e => e.type === type) || { type: 'text', label: 'Field', default: { col: 12 } };
        const name = this._getNextAvailableName(type, c.fields.map(f => f.name));
        
        const newField = Object.assign({
            name,
            type,
            label: name.charAt(0).toUpperCase() + name.slice(1).replace(/_/g, ' '),
            placeholder: '',
            help: '',
            value: '',
            col: 12,
            required: false,
            validation: '',
            depends_on: null,
            computed: null
        }, elDef.default || {});

        if (atIndex !== null && atIndex >= 0 && atIndex <= c.fields.length) {
            c.fields.splice(atIndex, 0, newField);
        } else {
            c.fields.push(newField);
        }

        this.refreshModal();
    }

    duplicateField(idx) {
        const c = this.state.currentFormConfig;
        const field = c.fields[idx];
        if (!field) return;

        const copy = JSON.parse(JSON.stringify(field));
        copy.name = this._getNextAvailableName(field.name + '_copy', c.fields.map(f => f.name));
        c.fields.splice(idx + 1, 0, copy);
        this.refreshModal();
    }

    removeField(idx) {
        const c = this.state.currentFormConfig;
        c.fields.splice(idx, 1);
        this.refreshModal();
    }

    setFieldCol(idx, col) {
        const c = this.state.currentFormConfig;
        if (c.fields[idx]) {
            c.fields[idx].col = col;
            this.refreshModal();
        }
    }

    clearCanvas() {
        if (confirm('Are you sure you want to clear all fields from this form?')) {
            this.state.currentFormConfig.fields = [];
            this.refreshModal();
        }
    }

    // =============================================
    // DRAG AND DROP HANDLERS
    // =============================================

    onPaletteDragStart(e, type) {
        e.dataTransfer.setData('source', 'palette');
        e.dataTransfer.setData('elementType', type);
    }

    onFieldDragStart(e, idx) {
        e.dataTransfer.setData('source', 'canvas');
        e.dataTransfer.setData('fieldIndex', String(idx));
    }

    onFieldDragOver(e) {
        e.preventDefault();
        const target = e.target.closest('.field-item');
        if (target) target.classList.add('drag-over');
    }

    onFieldDragLeave(e) {
        const target = e.target.closest('.field-item');
        if (target) target.classList.remove('drag-over');
    }

    onFieldDrop(e, targetIdx) {
        e.preventDefault();
        document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
        const source = e.dataTransfer.getData('source');

        if (source === 'palette') {
            const type = e.dataTransfer.getData('elementType');
            if (type) this.addField(type, targetIdx);
        } else if (source === 'canvas') {
            const fromIdx = parseInt(e.dataTransfer.getData('fieldIndex'));
            if (!isNaN(fromIdx) && fromIdx !== targetIdx) {
                const c = this.state.currentFormConfig;
                const field = c.fields.splice(fromIdx, 1)[0];
                c.fields.splice(targetIdx, 0, field);
                this.refreshModal();
            }
        }
    }

    onCanvasDrop(e) {
        e.preventDefault();
        const source = e.dataTransfer.getData('source');
        if (source === 'palette') {
            const type = e.dataTransfer.getData('elementType');
            if (type) this.addField(type);
        }
    }

    // =============================================
    // SOURCE (YAML) & LIVE PREVIEW
    // =============================================

    getSourceHtml() {
        return html`
            <div style="display: flex; flex-direction: column; height: 100%; gap: 8px;">
                <div style="font-size: 0.8rem; color: var(--text-dim); display: flex; justify-content: space-between; align-items: center;">
                    <span>Declarative YAML Manifest</span>
                    <span>Edits are automatically parsed and synced to Visual Builder</span>
                </div>
                <textarea id="editor-content" 
                          spellcheck="false" 
                          .value=${this.state.currentFormSource || ''}
                          @input=${(e) => { this.state.currentFormSource = e.target.value; }}
                          style="flex: 1; min-height: 480px; font-family: 'Fira Code', monospace; font-size: 0.85rem; line-height: 1.5; padding: 1rem; background: var(--input-bg); border: 1px solid var(--glass-border); border-radius: 8px; color: var(--text-bright);"></textarea>
            </div>
        `;
    }

    setPreviewDevice(device) {
        this.state.previewDevice = device;
        this.refreshModal();
    }

    getPreviewHtml() {
        const { previewDevice, previewLoading, previewHtml, previewError, currentFormConfig } = this.state;
        if (previewLoading) {
            return html`
                <div class="preview-loading" style="padding: 3rem; text-align: center;">
                    <div class="sppux-spinner"></div>
                    <p style="margin-top: 1rem; color: var(--text-dim);">Rendering authentic framework preview...</p>
                </div>
            `;
        }
        if (previewError) {
            return html`<div class="alert error" style="margin: 2rem;">Preview render failed: ${previewError}</div>`;
        }
        const deviceWidth = previewDevice === 'mobile' ? '375px' : (previewDevice === 'tablet' ? '768px' : '100%');
        const trustedContent = previewHtml ? new SPPUX.TrustedHTML(previewHtml) : html`<p style="color:var(--text-dim); text-align:center;">No preview content generated.</p>`;

        return html`
            <div style="display: flex; flex-direction: column; height: 100%; gap: 10px;">
                <!-- Viewport Switcher Toolbar -->
                <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.2); padding: 8px 14px; border-radius: 8px; border: 1px solid var(--glass-border);">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="badge tiny success">Live Framework Engine</span>
                        <span style="font-size: 0.8rem; color: var(--text-dim);">${currentFormConfig.name || 'form'}.yml</span>
                    </div>
                    <div style="display: flex; gap: 4px;">
                        <button type="button" class="btn btn-sm ${previewDevice === 'desktop' ? 'primary-btn' : 'ghost-btn'}" @click=${() => this.setPreviewDevice('desktop')}>💻 Desktop</button>
                        <button type="button" class="btn btn-sm ${previewDevice === 'tablet' ? 'primary-btn' : 'ghost-btn'}" @click=${() => this.setPreviewDevice('tablet')}>📟 Tablet</button>
                        <button type="button" class="btn btn-sm ${previewDevice === 'mobile' ? 'primary-btn' : 'ghost-btn'}" @click=${() => this.setPreviewDevice('mobile')}>📱 Mobile</button>
                    </div>
                </div>

                <!-- Viewport Content Container -->
                <div style="flex: 1; overflow-y: auto; display: flex; justify-content: center; padding: 1rem 0;">
                    <div id="preview-viewport-wrapper" style="width: ${deviceWidth}; transition: width 0.3s ease; background: var(--panel-bg-solid); padding: 1.5rem; border-radius: 8px; border: 1px solid var(--glass-border); box-shadow: 0 10px 30px rgba(0,0,0,0.3); height: fit-content;">
                        ${trustedContent}
                    </div>
                </div>
            </div>
        `;
    }

    async loadPreview() {
        this.state.previewLoading = true;
        this.state.previewError = '';
        this.refreshModal();

        const yaml = this.state.currentFormSource || this._serializeToYaml(this.state.currentFormConfig);
        const app = this.selectedApp;

        try {
            const fd = new FormData();
            fd.append('action', 'preview_form');
            fd.append('yaml', yaml);
            fd.append('appname', app);
            const res = await this.apiPost(fd, {}, { lock: false });

            if (res.success && res.data?.html) {
                this.state.previewHtml = res.data.html;
                this.state.previewError = '';
            } else {
                // Fallback attempt via get_form_html
                const fd2 = new FormData();
                fd2.append('action', 'get_form_html');
                fd2.append('yaml', yaml);
                fd2.append('appname', app);
                const res2 = await this.apiPost(fd2, {}, { lock: false });
                if (res2.success && res2.data?.html) {
                    this.state.previewHtml = res2.data.html;
                    this.state.previewError = '';
                } else {
                    this.state.previewHtml = '';
                    this.state.previewError = res.message || res2.message || 'Preview generation failed';
                }
            }
        } catch (err) {
            this.state.previewHtml = '';
            this.state.previewError = err.message || 'Preview generation failed';
        } finally {
            this.state.previewLoading = false;
            this.refreshModal();
        }
    }

    async renderPreview() {
        return this.loadPreview();
    }

    async syncSourceToBuilder(source) {
        const fd = new FormData();
        fd.append('action', 'parse_form_yaml');
        fd.append('yaml', source);
        try {
            const res = await this.apiPost(fd, {}, { lock: false });
            if (res.success && res.data?.config) {
                this.state.currentFormConfig = this._normalizeConfig(res.data.config);
                this.state.currentFormSource = source;
                this.refreshModal();
            }
        } catch (e) {
            console.error("Sync error:", e);
        }
    }

    async save() {
        const { currentFormConfig, activeFormTab } = this.state;
        const app = this.selectedApp;

        let yaml = '';
        if (activeFormTab === 'source') {
            yaml = document.getElementById('editor-content')?.value || this.state.currentFormSource;
        } else {
            yaml = this._serializeToYaml(currentFormConfig);
        }

        const name = (currentFormConfig.name || 'unnamed_form').trim();
        const fd = new FormData();
        fd.append('action', 'save_form_config');
        fd.append('name', name);
        fd.append('yaml', yaml);
        fd.append('content', yaml);
        fd.append('appname', app);

        try {
            const res = await this.apiPost(fd);
            if (res.success) {
                this.notify(`Form '${name}' saved successfully.`, 'success');
                this.closeModal();
                await this.fetchData();
            } else {
                throw new Error(res.message);
            }
        } catch (err) {
            this.notify(`Save failed: ${err.message}`, 'error');
        }
    }

    cloneForm(name, content) {
        const newName = this._getNextAvailableName(name + '_copy', this.existingFormNames || []);
        this.openEditor(newName, 'yml', content, 'builder');
    }

    async confirmDelete(type, name) {
        if (confirm(`Are you sure you want to permanently delete form '${name}'?`)) {
            const fd = new FormData();
            fd.append('action', 'delete_form');
            fd.append('name', name);
            fd.append('appname', this.selectedApp);
            const res = await this.apiPost(fd);
            if (res.success) {
                this.notify(`Form '${name}' deleted.`, 'success');
                await this.fetchData();
            }
        }
    }

    // =============================================
    // NORMALIZATION & SERIALIZATION HELPERS
    // =============================================

    _normalizeConfig(raw) {
        if (!raw || typeof raw !== 'object') raw = {};

        const c = {
            id: raw.id || raw.name || (raw.form && (raw.form.id || raw.form.name)) || 'my_form',
            name: raw.name || raw.id || (raw.form && (raw.form.name || raw.form.id)) || 'my_form',
            public_name: raw.public_name || raw.title || '',
            method: (raw.method || (raw.form && raw.form.method) || 'post').toLowerCase(),
            action: raw.action || (raw.form && raw.form.action) || '',
            entity: raw.entity || (raw.form && raw.form.entity) || '',
            submit_label: raw.submit_label || (raw.form && raw.form.submit_label) || 'Submit',
            honeypot: raw.honeypot !== undefined ? !!raw.honeypot : (raw.form?.honeypot !== undefined ? !!raw.form.honeypot : false),
            workflow_actions: raw.workflow_actions !== undefined ? !!raw.workflow_actions : (raw.form?.workflow_actions !== undefined ? !!raw.form.workflow_actions : false),
            offline: raw.offline !== undefined ? !!raw.offline : (raw.form?.offline !== undefined ? !!raw.form.offline : false),
            telemetry: raw.telemetry !== undefined ? !!raw.telemetry : (raw.form?.telemetry !== undefined ? !!raw.form.telemetry : false),
            autosave: raw.autosave !== undefined ? !!raw.autosave : (raw.form?.autosave !== undefined ? !!raw.form.autosave : false),
            fields: []
        };

        let rawFields = raw.fields;
        if (!rawFields && raw.form && raw.form.fields) rawFields = raw.form.fields;
        if (!rawFields && raw.form && raw.form.controls && raw.form.controls.control) rawFields = raw.form.controls.control;
        if (!rawFields && raw.controls && raw.controls.control) rawFields = raw.controls.control;

        if (Array.isArray(rawFields)) {
            c.fields = rawFields.map((f, i) => {
                const name = f.name || f.id || `field_${i + 1}`;
                let type = (f.type || 'text').toLowerCase();
                if (type.startsWith('spp')) type = type.substring(3).toLowerCase();
                return {
                    name,
                    type,
                    label: f.label || (name.charAt(0).toUpperCase() + name.slice(1).replace(/_/g, ' ')),
                    placeholder: f.placeholder || '',
                    help: f.help || f.help_text || '',
                    value: f.value || f.default || '',
                    col: f.col ? parseInt(f.col) : 12,
                    required: !!f.required,
                    validation: f.validation || (f.rules ? (Array.isArray(f.rules) ? f.rules.join('|') : f.rules) : ''),
                    depends_on: f.depends_on || null,
                    computed: f.computed || '',
                    options: f.options || null,
                    datasource: f.datasource || '',
                    accept: f.accept || '',
                    min: f.min !== undefined ? f.min : null,
                    max: f.max !== undefined ? f.max : null,
                    step: f.step !== undefined ? f.step : null,
                    length: f.length || null,
                    language: f.language || 'php'
                };
            });
        } else if (rawFields && typeof rawFields === 'object') {
            c.fields = Object.entries(rawFields).map(([key, f]) => {
                if (typeof f !== 'object' || f === null) f = { type: 'text' };
                let type = (f.type || 'text').toLowerCase();
                if (type.startsWith('spp')) type = type.substring(3).toLowerCase();
                return {
                    name: key,
                    type,
                    label: f.label || (key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ')),
                    placeholder: f.placeholder || '',
                    help: f.help || f.help_text || '',
                    value: f.value || f.default || '',
                    col: f.col ? parseInt(f.col) : 12,
                    required: !!f.required,
                    validation: f.validation || (f.rules ? (Array.isArray(f.rules) ? f.rules.join('|') : f.rules) : ''),
                    depends_on: f.depends_on || null,
                    computed: f.computed || '',
                    options: f.options || null,
                    datasource: f.datasource || '',
                    accept: f.accept || '',
                    min: f.min !== undefined ? f.min : null,
                    max: f.max !== undefined ? f.max : null,
                    step: f.step !== undefined ? f.step : null,
                    length: f.length || null,
                    language: f.language || 'php'
                };
            });
        }

        return c;
    }

    _serializeToYaml(config) {
        const lines = [];
        lines.push(`name: ${config.name || 'my_form'}`);
        if (config.public_name) lines.push(`public_name: "${config.public_name}"`);
        if (config.method && config.method !== 'post') lines.push(`method: ${config.method}`);
        if (config.action) lines.push(`action: ${config.action}`);
        if (config.entity) lines.push(`entity: ${config.entity}`);
        if (config.submit_label && config.submit_label !== 'Submit') lines.push(`submit_label: "${config.submit_label}"`);
        if (config.honeypot) lines.push(`honeypot: true`);
        if (config.workflow_actions) lines.push(`workflow_actions: true`);
        if (config.offline) lines.push(`offline: true`);
        if (config.telemetry) lines.push(`telemetry: true`);
        if (config.autosave) lines.push(`autosave: true`);
        lines.push('');
        lines.push('fields:');

        (config.fields || []).forEach(f => {
            lines.push(`  ${f.name}:`);
            lines.push(`    type: ${f.type || 'text'}`);
            if (f.label) lines.push(`    label: "${f.label}"`);
            if (f.placeholder) lines.push(`    placeholder: "${f.placeholder}"`);
            if (f.help) lines.push(`    help: "${f.help}"`);
            if (f.value) lines.push(`    value: "${f.value}"`);
            if (f.col && f.col !== 12) lines.push(`    col: ${f.col}`);
            if (f.required) lines.push(`    required: true`);
            if (f.validation) lines.push(`    validation: "${f.validation}"`);
            if (f.depends_on) {
                if (typeof f.depends_on === 'object') {
                    lines.push(`    depends_on:`);
                    for (const [k, v] of Object.entries(f.depends_on)) {
                        lines.push(`      ${k}: ${v}`);
                    }
                } else {
                    lines.push(`    depends_on: ${f.depends_on}`);
                }
            }
            if (f.computed) lines.push(`    computed: "${f.computed}"`);
            if (f.options) {
                lines.push(`    options:`);
                if (typeof f.options === 'object' && !Array.isArray(f.options)) {
                    for (const [k, v] of Object.entries(f.options)) {
                        lines.push(`      ${k}: "${v}"`);
                    }
                } else if (Array.isArray(f.options)) {
                    f.options.forEach(opt => {
                        if (typeof opt === 'object') {
                            const k = Object.keys(opt)[0];
                            lines.push(`      ${k}: "${opt[k]}"`);
                        } else {
                            lines.push(`      - "${opt}"`);
                        }
                    });
                }
            }
            if (f.datasource) lines.push(`    datasource: "${f.datasource}"`);
            if (f.accept) lines.push(`    accept: "${f.accept}"`);
            if (f.min !== null && f.min !== undefined && f.min !== '') lines.push(`    min: ${f.min}`);
            if (f.max !== null && f.max !== undefined && f.max !== '') lines.push(`    max: ${f.max}`);
            if (f.step !== null && f.step !== undefined && f.step !== '') lines.push(`    step: ${f.step}`);
            if (f.length) lines.push(`    length: ${f.length}`);
            if (f.language && f.language !== 'php') lines.push(`    language: ${f.language}`);
            lines.push('');
        });

        return lines.join('\n');
    }

    _getNextAvailableName(base, existing) {
        let name = base;
        let i = 1;
        while (existing.includes(name)) {
            name = base + '_' + i;
            i++;
        }
        return name;
    }
}
