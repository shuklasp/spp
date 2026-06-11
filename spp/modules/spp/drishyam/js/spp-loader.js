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
        console.error(`Failed to mount SPP component:`, error);
        el.innerHTML = `<div class="spp-ux-error" style="color: #e53e3e; padding: 1rem; border: 1px solid #feb2b2; border-radius: 8px; background: #fff5f5; font-family: system-ui;">
            <strong>SPP-UX Loader Error:</strong> ${error.message}
        </div>`;
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
