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
    const componentName = el.dataset.sppComponent || el.dataset.sppWidget;
    const props = JSON.parse(el.dataset.sppProps || '{}');

    if (!componentPath && !componentName) return null;

    try {
        let Component;
        
        if (componentPath) {
            const module = await import(componentPath);
            Component = module.default;
        } else if (window[componentName]) {
            Component = window[componentName];
        } else if (window.SPPUX && window.SPPUX[componentName]) {
            Component = window.SPPUX[componentName];
        } else if (window.SPPUX) {
            const widgetMap = {
                'table': 'DataTable',
                'datatable': 'DataTable',
                'tabs': 'Tabs',
                'daterange': 'DateRangePicker',
                'date-range': 'DateRangePicker',
                'filter': 'FilterBuilder',
                'filter-builder': 'FilterBuilder',
                'fileupload': 'FileUpload',
                'file-upload': 'FileUpload',
                'timeline': 'Timeline',
                'code': 'CodeBlock',
                'code-block': 'CodeBlock',
                'metricgrid': 'MetricGrid',
                'metric-grid': 'MetricGrid',
                'metrics': 'MetricGrid',
                'badge': 'Badge',
                'empty': 'EmptyState',
                'empty-state': 'EmptyState',
                'diff': 'DiffViewer',
                'diff-viewer': 'DiffViewer',
                'transfer': 'TransferList',
                'transfer-list': 'TransferList',
                'wizard': 'Wizard',
                'breadcrumb': 'Breadcrumb',
                'dropdown': 'DropdownMenu',
                'dropdown-menu': 'DropdownMenu',
                'tree': 'TreeView',
                'treeview': 'TreeView',
                'tree-view': 'TreeView',
                'kanban': 'KanbanBoard',
                'kanbanboard': 'KanbanBoard',
                'kanban-board': 'KanbanBoard',
                'accordion': 'Accordion',
                'collapse': 'Accordion',
                'card': 'Card',
                'statcard': 'Card',
                'stat-card': 'Card',
                'list': 'ListView',
                'listview': 'ListView',
                'list-view': 'ListView',
                'descriptionlist': 'DescriptionList',
                'description-list': 'DescriptionList',
                'keyval': 'DescriptionList',
                'key-val': 'DescriptionList',
                'avatar': 'Avatar',
                'avatargroup': 'Avatar',
                'avatar-group': 'Avatar',
                'kbd': 'Kbd',
                'keyboard': 'Kbd',
                'rating': 'Rating',
                'starrating': 'Rating',
                'star-rating': 'Rating',
                'slider': 'Slider',
                'rangeslider': 'Slider',
                'range-slider': 'Slider',
                'colorpicker': 'ColorPicker',
                'color-picker': 'ColorPicker',
                'switch': 'Switch',
                'toggle': 'Switch',
                'markdowneditor': 'MarkdownEditor',
                'markdown-editor': 'MarkdownEditor',
                'editor': 'MarkdownEditor',
                'signature': 'SignaturePad',
                'signaturepad': 'SignaturePad',
                'signature-pad': 'SignaturePad',
                'pagination': 'Pagination',
                'pager': 'Pagination',
                'stepper': 'Stepper',
                'steps': 'Stepper',
                'modal': 'Modal',
                'dialog': 'Modal',
                'drawer': 'Drawer',
                'offcanvas': 'Drawer',
                'alert': 'Alert',
                'callout': 'Alert',
                'progress': 'ProgressBar',
                'progressbar': 'ProgressBar',
                'progress-bar': 'ProgressBar',
                'skeleton': 'Skeleton',
                'placeholder': 'Skeleton',
                'qrcode': 'QRCode',
                'qr': 'QRCode'
            };
            const mappedName = widgetMap[componentName.toLowerCase()];
            if (mappedName && window.SPPUX[mappedName]) {
                Component = window.SPPUX[mappedName];
            }
        }
        
        if (!Component && window.__spp_templates.has(componentName)) {
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
        console.error(`[SPP-UX] Failed to mount component [${componentName || componentPath}]:`, error);

        const rawMsg = error.message || String(error);
        const isSyntax = error instanceof SyntaxError || 
            rawMsg.includes('Unexpected token') || 
            rawMsg.includes('SyntaxError') || 
            rawMsg.includes('Invalid or unexpected');
        const isNotFound = rawMsg.includes('Failed to fetch dynamically imported module') || 
            rawMsg.includes('not found') || 
            rawMsg.includes('Cannot find module');

        let errorBadge = 'Runtime Exception';
        let badgeColor = '#d29922';
        let categoryTitle = 'Component Execution Error';
        let plainExplanation = `An uncaught exception occurred while loading or initializing the <strong>${componentName || componentPath || 'SPP-UX'}</strong> component.`;
        let diagnosticTips = [
            'Open the browser developer console (<kbd style="background:#21262d; padding:1px 5px; border-radius:3px;">F12</kbd> &rarr; Console) to view the full execution callstack.',
            'Check for null property access, invalid initial props, or unhandled promise rejections in <code>onInit()</code>.'
        ];

        if (isSyntax) {
            errorBadge = 'Syntax Error';
            badgeColor = '#f85149';
            categoryTitle = 'JavaScript Parsing / Syntax Error';
            plainExplanation = `The browser JavaScript runtime could not parse the component module <strong>${componentName || componentPath || ''}</strong> due to invalid syntax.`;
            diagnosticTips = [
                'A syntax error indicates malformed code, such as an unexpected token, an unclosed brace <code>{ }</code>, mismatched parentheses <code>( )</code>, or a misplaced keyword.',
                'Inspect the component source or run a quick syntax check in your terminal: <code>node --check &lt;file&gt;</code>.'
            ];
        } else if (isNotFound) {
            errorBadge = 'Module Not Found';
            badgeColor = '#f0883e';
            categoryTitle = 'Component Module Missing or Unreachable';
            plainExplanation = `The component script could not be loaded at <code>${componentPath || componentName}</code>.`;
            diagnosticTips = [
                `Verify that the component file exists on disk and is served with a 200 OK status.`,
                'Check web server directory permissions and routing rewrite rules.'
            ];
        }

        const copyData = {
            component: componentName || componentPath,
            path: componentPath,
            error: rawMsg,
            stack: error.stack || 'No stack trace available',
            timestamp: new Date().toISOString(),
            url: window.location.href,
            userAgent: navigator.userAgent
        };

        el.innerHTML = `
            <div style="margin:1.5rem auto; max-width:860px; background:#0d1117; border:1px solid #30363d; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.5); overflow:hidden; font-family:Inter,system-ui,sans-serif;">
                <div style="background:#161b22; padding:16px 20px; border-bottom:1px solid #30363d; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:1.5rem;">⚠️</span>
                        <div>
                            <h3 style="margin:0; font-size:1rem; font-weight:700; color:#f0f6fc; display:flex; align-items:center; gap:8px;">
                                ${categoryTitle}
                                <span style="background:${badgeColor}22; color:${badgeColor}; border:1px solid ${badgeColor}66; font-size:0.68rem; font-weight:700; padding:2px 7px; border-radius:12px; text-transform:uppercase;">
                                    ${errorBadge}
                                </span>
                            </h3>
                            <div style="margin-top:3px; font-size:0.75rem; color:#8b949e;">Target: <code style="color:#58a6ff; background:#1f242c; padding:1px 5px; border-radius:3px;">${componentName || componentPath || 'SPP-UX Component'}</code></div>
                        </div>
                    </div>
                    <div style="display:flex; gap:6px;">
                        <button type="button" class="spp-component-reload-btn" style="background:#238636; color:#ffffff; border:none; padding:5px 12px; border-radius:6px; font-size:0.78rem; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:5px;">
                            <span>↻</span> Reload Component
                        </button>
                    </div>
                </div>

                <div style="padding:20px;">
                    <p style="color:#c9d1d9; font-size:0.86rem; line-height:1.5; margin-top:0;">
                        ${plainExplanation}
                    </p>

                    <div style="margin:14px 0; background:#090d13; border:1px solid rgba(248,81,73,0.35); border-left:4px solid ${badgeColor}; border-radius:6px; padding:12px; overflow-x:auto;">
                        <div style="font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; color:${badgeColor}; margin-bottom:6px;">Diagnostic Log Output</div>
                        <pre style="margin:0; font-family:'Fira Code',Consolas,monospace; font-size:0.8rem; color:#f0f6fc; white-space:pre-wrap; word-break:break-word; line-height:1.45;">${rawMsg}</pre>
                    </div>

                    <div style="background:#161b22; border:1px solid #30363d; border-radius:8px; padding:14px; margin-top:16px;">
                        <h4 style="margin:0 0 8px; font-size:0.82rem; font-weight:600; color:#58a6ff; display:flex; align-items:center; gap:6px;">
                            <span>💡</span> Troubleshooting &amp; Remediation
                        </h4>
                        <ul style="margin:0; padding-left:18px; font-size:0.8rem; color:#8b949e; line-height:1.55;">
                            ${diagnosticTips.map(tip => `<li>${tip}</li>`).join('')}
                        </ul>
                    </div>

                    <div style="margin-top:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; padding-top:14px; border-top:1px solid #21262d;">
                        <button type="button" class="spp-component-copy-btn" style="background:#21262d; border:1px solid #30363d; color:#c9d1d9; padding:4px 10px; border-radius:6px; font-size:0.75rem; cursor:pointer;">
                            📋 Copy Diagnostics
                        </button>
                        <span style="font-size:0.72rem; color:#484f58;">Press <kbd style="background:#21262d; padding:2px 4px; border-radius:3px; color:#8b949e;">F12</kbd> for DevTools console stack trace</span>
                    </div>
                </div>
            </div>
        `;

        const reloadBtn = el.querySelector('.spp-component-reload-btn');
        if (reloadBtn) {
            reloadBtn.addEventListener('click', () => {
                delete el.__sppUxInstance;
                if (el.dataset.sppPath) {
                    const clean = el.dataset.sppPath.split('?')[0];
                    el.dataset.sppPath = `${clean}?v=${Date.now()}`;
                }
                mountSPPUXComponent(el);
            });
        }

        const copyBtn = el.querySelector('.spp-component-copy-btn');
        if (copyBtn) {
            copyBtn.addEventListener('click', () => {
                const text = JSON.stringify(copyData, null, 2);
                navigator.clipboard.writeText(text).then(() => alert('Diagnostics copied to clipboard!')).catch(() => prompt('Copy diagnostics:', text));
            });
        }

        return null;
    }
}

function mountAllSPPUXComponents(root = document) {
    registerDeclarativeTemplates(root);
    return Promise.all(
        Array.from(root.querySelectorAll('[data-spp-component], [data-spp-widget], [data-spp-path]')).map(mountSPPUXComponent)
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
