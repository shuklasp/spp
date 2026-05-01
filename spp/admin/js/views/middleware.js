/**
 * Middleware Pipeline View Component
 */
export async function render(container) {
    const admin = window.admin;
    const res = await admin.api('list_middleware', { context: admin.selectedApp });
    
    if (!res.success) {
        container.innerHTML = `<div class="error-state">Failed to load middleware stack.</div>`;
        return;
    }

    const { global, application } = res.data;

    let globalHtml = global.map(m => `
        <div class="middleware-item glass-panel">
            <div class="mw-icon">🌐</div>
            <div class="mw-details">
                <div class="mw-name">${m}</div>
                <div class="mw-scope">Global Scope</div>
            </div>
        </div>
    `).join('');

    let appHtml = application.map(m => `
        <div class="middleware-item glass-panel app-scope">
            <div class="mw-icon">📱</div>
            <div class="mw-details">
                <div class="mw-name">${m}</div>
                <div class="mw-scope">Application: ${admin.selectedApp}</div>
            </div>
        </div>
    `).join('');

    container.innerHTML = `
        <div class="pipeline-container">
            <div class="pipeline-header">
                <h3>Request Lifecycle</h3>
                <p>Onion-style middleware execution order (Top to Bottom)</p>
            </div>
            <div class="pipeline-visual">
                <div class="pipeline-flow">
                    <div class="flow-marker start">REQUEST IN</div>
                    ${globalHtml || '<div class="empty-mw">No Global Middleware</div>'}
                    <div class="flow-divider">--- Application Border ---</div>
                    ${appHtml || '<div class="empty-mw">No App-Specific Middleware</div>'}
                    <div class="flow-marker end">APP HANDLER</div>
                </div>
            </div>
        </div>
    `;
}
