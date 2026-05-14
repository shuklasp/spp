import LekhniEditor from '../../../spp/modules/contrib/lekhni/js/lekhni-editor.js?v=2026_05_13_v1';

/**
 * Lekhak Editor Wrapper
 * Fully leverages the genericized global contrib Lekhni core workspace engine
 * while injecting specialized app-aware categories, preview routing, and slash extension modules.
 */
export default class EditorView extends LekhniEditor {
    async onInit(params) {
        await super.onInit(params);

        // Supplement categories array natively from application models
        this.state.categories = [
            ...this.state.categories,
            'Lekhak Chronicle',
            'Press Release',
            'Product Story',
            'Case Study'
        ];

        // Append custom application-level block registries onto the interactive slash palette
        this.slashCommands = [
            ...this.slashCommands,
            { 
                id: 'lekhak_product', 
                label: 'Product Showcase Box', 
                icon: '🛍️', 
                desc: 'Insert dynamic Lekhak eCommerce card adapter' 
            },
            { 
                id: 'lekhak_template', 
                label: 'App Component Embed', 
                icon: '🧩', 
                desc: 'Mount reusable app framework UI template string' 
            }
        ];
    }

    // Intercept custom application-level block execution identifiers securely
    executeSlashCommand(cmdId) {
        if (cmdId === 'lekhak_product') {
            const productTitle = prompt("Enter Showcase Product Name:") || "Premium Plan Subscription";
            const productPrice = prompt("Enter Product Price String:") || "$99.00 / yr";
            
            const productHtml = `
                <div class="lekhak-app-product-card" contenteditable="false" style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; margin: 1.5rem 0; border: 2px solid #6366f1; border-radius: 12px; background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3);">
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(99,102,241,0.2); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">🛍️</div>
                        <div>
                            <div style="font-size: 0.75rem; text-transform: uppercase; color: #a5b4fc; font-weight: bold; letter-spacing: 0.05em;">Lekhak Integrated Commerce</div>
                            <div style="font-size: 1.1rem; color: white; font-weight: 800; font-family: 'Outfit', sans-serif;">${productTitle}</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 1.25rem; font-weight: bold; color: #4ade80;">${productPrice}</span>
                        <button style="background: #6366f1; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; cursor: pointer;">Purchase Now</button>
                    </div>
                </div>
                <p><br></p>
            `;
            this.format('insertHTML', productHtml);
            this.notify("Lekhak Commerce adapter embedded perfectly.", "success");
            return;
        }

        if (cmdId === 'lekhak_template') {
            const templateHtml = `
                <div class="lekhak-template-embed" contenteditable="false" style="padding: 16px; border-left: 4px solid #f59e0b; background: rgba(245, 158, 11, 0.1); border-radius: 0 8px 8px 0; margin: 1rem 0; color: #fcd34d;">
                    <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: bold; margin-bottom: 4px; color: #f59e0b;">🧩 Dynamic Component Hook</div>
                    <div>[Inherited Framework Screen Layout Block Rendered Securely]</div>
                </div>
                <p><br></p>
            `;
            this.format('insertHTML', templateHtml);
            this.notify("App dynamic component placeholder mounted.", "info");
            return;
        }

        // Delegate all default underlying core framework block identifiers back to super
        super.executeSlashCommand(cmdId);
    }

    // Fully extend lit-html templates to append top header live application preview handling
    render() {
        const baseTemplate = super.render();
        const { id, embedded } = this.state;
        const baseUrl = this.admin?.config?.baseUrl || '';

        // Safely map enhanced app view bindings if running fullscreen
        if (!embedded && id) {
            return html`
                <div class="lekhak-enriched-editor-shell" style="display: flex; flex-direction: column; height: 100vh; width: 100%;">
                    <div class="app-integration-bar" style="background: #090d16; border-bottom: 1px solid #1e293b; padding: 6px 24px; display: flex; justify-content: space-between; align-items: center; z-index: 2001;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="background: #6366f1; color: white; font-size: 0.65rem; font-weight: bold; padding: 2px 6px; border-radius: 4px;">LEKHAK CMS</span>
                            <span style="font-size: 0.75rem; color: #94a3b8;">Active Integration Pipeline Layer</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <a href="${baseUrl}/node/${id}" target="_blank" style="color: #38bdf8; font-size: 0.75rem; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 4px;" title="Launch complete frontend resource template stream">
                                🌐 Launch Live Preview ↗
                            </a>
                        </div>
                    </div>
                    <div style="flex-grow: 1; position: relative;">
                        ${baseTemplate}
                    </div>
                </div>
            `;
        }

        return baseTemplate;
    }
}
