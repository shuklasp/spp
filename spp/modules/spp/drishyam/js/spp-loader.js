/**
 * SPP-UX component loader (Enhanced V3)
 *
 * Supports JS-based components and Declarative HTML Templates.
 */

window.__spp_templates = window.__spp_templates || new Map();

/**
 * Scans for <template data-spp-ux="Name"> and registers them.
 */
function registerDeclarativeTemplates(root = document) {
    root.querySelectorAll('template[data-spp-ux]').forEach(template => {
        const name = template.dataset.sppUx;
        // Use textContent of the template's content fragment to get raw text (no entities like &gt;)
        const content = template.content.textContent || template.innerHTML;
        
        // We still need to handle the case where someone put HTML in the template 
        // that isn't part of an interpolation. 
        // Actually, for SPP-UX templates, we treat the whole thing as a tagged template string.
        window.__spp_templates.set(name, content);
    });
}

/**
 * Creates a dynamic component class from an HTML template string.
 */
function createComponentFromTemplate(name, templateContent) {
    return class extends BaseComponent {
        constructor(admin, container, props) {
            super(admin, container, props);
            try {
                // The templateContent is now a raw string. 
                // We wrap it in the html tag function.
                this._compiledRender = new Function('props', 'state', 'html', 'Fragment', `
                    return html\`${templateContent}\`;
                `);
            } catch (e) {
                console.error(`Failed to compile template for ${name}:`, e);
                this._compiledRender = () => html`<div style="color:red; padding: 1rem; border: 1px solid red; border-radius: 8px;">
                    <strong>Template Compilation Error [${name}]:</strong> ${e.message}
                    <pre style="font-size: 10px; margin-top: 10px;">${templateContent.substring(0, 100)}...</pre>
                </div>`;
            }
        }

        render() {
            return this._compiledRender.call(this, this.props, this.state, window.html, window.Fragment);
        }
    };
}

async function mountSPPUXComponent(el) {
    if (el.__sppUxInstance) return el.__sppUxInstance;

    const type = el.dataset.sppType || 'ux';
    const componentPath = el.dataset.sppPath;
    const componentName = el.dataset.sppComponent;
    const props = JSON.parse(el.dataset.sppProps || '{}');

    if (!componentPath && !componentName) return null;

    try {
        let Component;
        
        if (componentPath) {
            const module = await import(componentPath);
            Component = module.default;
        } else if (window[componentName]) {
            Component = window[componentName];
        } else if (window.__spp_templates.has(componentName)) {
            Component = createComponentFromTemplate(componentName, window.__spp_templates.get(componentName));
        }

        if (!Component) {
            throw new Error(`Component "${componentName || componentPath}" not found in JS or Templates.`);
        }

        if (type === 'react') {
            const React = await import('https://esm.sh/react');
            const ReactDOM = await import('https://esm.sh/react-dom/client');
            const root = ReactDOM.createRoot(el);
            root.render(React.createElement(Component, props));
            return root;
        }

        if (type === 'vue') {
            const { createApp } = await import('https://esm.sh/vue');
            const app = createApp(Component, props);
            app.mount(el);
            return app;
        }

        const instance = new Component(window.spp_admin, el, props);
        el.__sppUxInstance = instance;

        if (instance.onInit) await instance.onInit();
        instance.update();
        if (instance.onMount) await instance.onMount();

        return instance;
    } catch (error) {
        console.error(`[SPP-UX] Failed to mount component:`, error);
        el.innerHTML = `
            <div style="margin:2rem; padding:2rem; background:rgba(255,50,50,0.1); border:1px solid rgba(255,50,50,0.3); border-radius:12px; font-family:system-ui, sans-serif; color:#ef4444; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
                <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; border-bottom:1px solid rgba(255,50,50,0.2); padding-bottom:1rem;">
                    <span style="font-size:2rem;">💥</span>
                    <div>
                        <h2 style="margin:0; font-size:1.4rem; color:#ef4444;">Frontend Component Error</h2>
                        <div style="font-size:0.9rem; opacity:0.8; margin-top:0.3rem;">The SPP-UX engine caught a critical compilation or runtime error.</div>
                    </div>
                </div>
                
                <div style="background:rgba(0,0,0,0.2); padding:1.2rem; border-radius:8px; margin-bottom:1.5rem;">
                    <strong style="display:block; margin-bottom:0.5rem; font-size:1.1rem;">${error.name || 'Error'}: ${error.message}</strong>
                    <pre style="margin:0; white-space:pre-wrap; font-size:0.85rem; color:#f87171; line-height:1.5; font-family:monospace; overflow-x:auto;">${error.stack || 'No stack trace available'}</pre>
                </div>

                <div style="display:flex; justify-content:flex-end;">
                    <button class="spp-error-reload-btn" style="background:#ef4444; color:white; border:none; padding:0.6rem 1.2rem; border-radius:6px; font-weight:bold; cursor:pointer; font-size:0.9rem; transition:background 0.2s;">
                        ↻ Hard Reload App
                    </button>
                </div>
            </div>
        `;
        const reloadBtn = el.querySelector('.spp-error-reload-btn');
        if (reloadBtn) {
            reloadBtn.addEventListener('click', () => window.location.reload(true));
        }
        return null;
    }
}

function mountAllSPPUXComponents(root = document) {
    registerDeclarativeTemplates(root);
    return Promise.all(
        Array.from(root.querySelectorAll('[data-spp-component], [data-spp-path]')).map(mountSPPUXComponent)
    );
}

// Auto-scan on DOM load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => mountAllSPPUXComponents());
} else {
    mountAllSPPUXComponents();
}

window.mountSPPComponent = mountSPPUXComponent;
window.mountAllSPPComponents = mountAllSPPUXComponents;
