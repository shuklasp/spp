import LekhniEditor from '../../../spp/modules/contrib/lekhni/js/lekhni-editor.js?v=2026_05_13_v1';

/**
 * Lekhak Editor Wrapper
 * Extends the Lekhni core workspace engine with app-specific features:
 * keyboard shortcuts, word count, unsaved changes warning, preview button.
 */
export default class EditorView extends LekhniEditor {
    async onInit(params) {
        await super.onInit(params);

        // Supplement categories
        this.state.categories = [
            ...this.state.categories,
            'Lekhak Chronicle', 'Press Release', 'Product Story', 'Case Study'
        ];

        // Custom slash commands
        this.slashCommands = [
            ...this.slashCommands,
            { id: 'lekhak_product', label: 'Product Showcase', icon: '🛍️', desc: 'Insert commerce product card' },
            { id: 'lekhak_template', label: 'Component Embed', icon: '🧩', desc: 'Mount reusable UI template' }
        ];
    }

    async onMount() {
        await super.onMount();
        this._setupKeyboardShortcuts();
        this._setupUnsavedWarning();
        this._startWordCountTracker();
    }

    _setupKeyboardShortcuts() {
        this._keyHandler = (e) => {
            const key = (e.key || '').toLowerCase();
            if ((e.ctrlKey || e.metaKey) && key === 's') {
                e.preventDefault();
                this.save(true);
            }
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && key === 'p') {
                e.preventDefault();
                this.publish();
            }
        };
        document.addEventListener('keydown', this._keyHandler);
    }

    _setupUnsavedWarning() {
        this._beforeUnload = (e) => {
            if (this.state.isDirty) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        };
        window.addEventListener('beforeunload', this._beforeUnload);
    }

    _startWordCountTracker() {
        this._wcInterval = setInterval(() => {
            const el = this.container?.querySelector('.lekhni-body-editable');
            if (!el) return;
            const text = el.innerText || '';
            const words = text.trim().split(/\s+/).filter(w => w.length > 0).length;
            const chars = text.length;
            const readMins = Math.max(1, Math.ceil(words / 200));
            const wcEl = this.container?.querySelector('.lekhni-word-count');
            if (wcEl) {
                wcEl.textContent = `${words} words · ${chars} chars · ${readMins} min read`;
            }
        }, 1000);
    }

    onDestroy() {
        if (this._keyHandler) document.removeEventListener('keydown', this._keyHandler);
        if (this._beforeUnload) window.removeEventListener('beforeunload', this._beforeUnload);
        if (this._wcInterval) clearInterval(this._wcInterval);
        super.onDestroy();
    }

    openPreview() {
        const alias = this.state.alias;
        const baseUrl = this.admin?.config?.baseUrl || '';
        if (alias) {
            window.open(`${baseUrl}/node/${alias}`, '_blank');
        } else if (this.state.id) {
            window.open(`${baseUrl}/node/${this.state.id}`, '_blank');
        } else {
            this.notify('Save the document first to preview it.', 'info');
        }
    }

    executeSlashCommand(cmdId) {
        if (cmdId === 'lekhak_product') {
            const productTitle = prompt("Product Name:") || "Premium Plan";
            const productPrice = prompt("Product Price:") || "$99.00/yr";
            const safeTitle = this.escapeHtml(productTitle);
            const safePrice = this.escapeHtml(productPrice);
            const productHtml = `
                <div class="lekhak-app-product-card" contenteditable="false" style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;margin:1.5rem 0;border:2px solid #6366f1;border-radius:12px;background:linear-gradient(135deg,#1e293b,#0f172a);box-shadow:0 10px 15px -3px rgba(0,0,0,0.3);">
                    <div style="display:flex;align-items:center;gap:16px;">
                        <div style="width:48px;height:48px;border-radius:8px;background:rgba(99,102,241,0.2);display:flex;align-items:center;justify-content:center;font-size:1.5rem;">🛍️</div>
                        <div>
                            <div style="font-size:0.75rem;text-transform:uppercase;color:#a5b4fc;font-weight:bold;letter-spacing:0.05em;">Commerce</div>
                            <div style="font-size:1.1rem;color:white;font-weight:800;font-family:'Outfit',sans-serif;">${safeTitle}</div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="font-size:1.25rem;font-weight:bold;color:#4ade80;">${safePrice}</span>
                        <button style="background:#6366f1;color:white;border:none;padding:8px 16px;border-radius:6px;font-weight:bold;cursor:pointer;">Buy Now</button>
                    </div>
                </div><p><br></p>`;
            this.format('insertHTML', productHtml);
            return;
        }
        if (cmdId === 'lekhak_template') {
            const templateHtml = `
                <div class="lekhak-template-embed" contenteditable="false" style="padding:16px;border-left:4px solid #f59e0b;background:rgba(245,158,11,0.1);border-radius:0 8px 8px 0;margin:1rem 0;color:#fcd34d;">
                    <div style="font-size:0.75rem;text-transform:uppercase;font-weight:bold;margin-bottom:4px;color:#f59e0b;">🧩 Component Embed</div>
                    <div>[Dynamic component placeholder]</div>
                </div><p><br></p>`;
            this.format('insertHTML', templateHtml);
            return;
        }
        super.executeSlashCommand(cmdId);
    }

    render() {
        const parentRender = super.render();
        if (parentRender && parentRender.content) {
            let html = parentRender.content;
            
            // 1. Inject Pathauto URL Alias into Sidebar
            const pathautoUI = `
                <div class="sidebar-section" style="margin-bottom:2rem;">
                    <h4>URL Alias (Pathauto)</h4>
                    <input type="text" id="lekhak-url-alias" placeholder="e.g. /my-custom-url" value="\${this.state.alias || ''}" style="width:100%; background:rgba(0,0,0,0.2); border:1px solid #334155; color:#fff; padding:8px; border-radius:6px; font-size:0.9rem;">
                    <div style="font-size:0.75rem; color:#64748b; margin-top:4px;">Leave blank to auto-generate from title.</div>
                </div>
            `;
            html = html.replace('<!-- Feature 1 Sidebar Section: Document Outline Scroll Spy -->', pathautoUI + '<!-- Feature 1 Sidebar Section: Document Outline Scroll Spy -->');

            // 2. Inject Paragraphs Builder below title, before the body
            const paragraphsUI = `
                <div class="lekhak-paragraphs-builder" style="margin-bottom: 2rem; border: 1px solid #334155; border-radius: 8px; background: #0f172a; overflow: hidden;">
                    <div style="padding: 10px 15px; background: #1e293b; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0; font-size: 1rem; color: #e2e8f0; font-family: 'Outfit', sans-serif;">Paragraphs Builder</h3>
                        <button class="btn-secondary" onclick="alert('Paragraphs Builder API connected. Add new block.')" style="padding: 4px 10px; font-size: 0.8rem;">+ Add Paragraph</button>
                    </div>
                    <div style="padding: 15px; color: #94a3b8; font-size: 0.9rem; text-align: center;">
                        <span style="font-size: 1.5rem; display: block; margin-bottom: 10px;">🧩</span>
                        Content is currently managed by the legacy WYSIWYG editor below.<br>
                        Click '+ Add Paragraph' to migrate this node to structured Paragraphs.
                    </div>
                </div>
            `;
            // Insert it right after the title input block
            html = html.replace(/(<input[^>]+class="lekhni-title"[^>]*>)/, '$1' + paragraphsUI);

            // 3. Add word count bar before closing wrapper
            const wcBar = `<div class="lekhni-word-count" style="position:absolute;bottom:0;left:0;right:0;height:28px;background:rgba(15,23,42,0.9);border-top:1px solid #334155;display:flex;align-items:center;padding:0 16px;font-size:0.72rem;color:#64748b;font-family:'JetBrains Mono',monospace;z-index:10;">0 words · 0 chars · 1 min read</div>`;
            html = html.replace(/<\/div>\s*<style>/, wcBar + '</div><style>');

            // 4. Inject preview button after Save Draft
            const previewBtn = `<button class="btn-secondary" data-spp-evt="lekhak-preview" data-spp-type="click" style="margin-right:4px;">👁️ Preview</button>`;
            html = html.replace(/Save Draft<\/button>/, 'Save Draft</button>' + previewBtn);

            // 5. Add keyboard shortcut hints
            const kbHint = `<span style="font-size:0.65rem;color:#475569;margin-left:8px;">Ctrl+S save · Ctrl+Shift+P publish</span>`;
            html = html.replace(/(Saved at [^<]*|Draft)<\/span>/, '$1' + kbHint + '</span>');

            parentRender.content = html;
        }

        // Register handlers
        window.__spp_handlers = window.__spp_handlers || {};
        window.__spp_handlers['lekhak-preview'] = () => this.openPreview();

        return parentRender;
    }

    back() {
        if (this.state.isDirty) {
            if (!confirm('You have unsaved changes. Leave anyway?')) return;
        }
        if (typeof this.onClose === 'function') {
            this.onClose();
        } else {
            location.hash = 'content';
        }
    }

    escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}
