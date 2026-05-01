/**
 * Distributed Task Queue View Component
 */
export async function render(container) {
    const admin = window.admin;
    const res = await admin.api('list_queue');
    
    if (!res.success) {
        container.innerHTML = `<div class="error-state">Failed to load task queue.</div>`;
        return;
    }

    const queue = res.data.queue;

    let queueHtml = queue.map(job => `
        <tr class="job-row">
            <td><code>${job.id}</code></td>
            <td><span class="job-class">${job.job}</span></td>
            <td><pre class="job-data">${JSON.stringify(job.data, null, 2)}</pre></td>
            <td><span class="badge secondary">${new Date(job.created_at * 1000).toLocaleString()}</span></td>
            <td><span class="status-pill waiting">Waiting</span></td>
        </tr>
    `).join('');

    container.innerHTML = `
        <div class="queue-manager glass-panel">
            <div class="manager-header">
                <h3>Shared Task Queue</h3>
                <div class="stats">
                    <span class="stat-item">Pending: <strong>${queue.length}</strong></span>
                </div>
            </div>
            <table class="spp-table">
                <thead>
                    <tr>
                        <th>Job ID</th>
                        <th>Worker Class</th>
                        <th>Payload</th>
                        <th>Queued At</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${queueHtml || '<tr><td colspan="5" class="text-center">Queue is empty. Workers are idle.</td></tr>'}
                </tbody>
            </table>
        </div>
    `;

    // Add Auto-refresh
    const actions = document.getElementById('header-actions');
    actions.innerHTML = `
        <button class="btn primary-btn btn-sm" id="refresh-queue">
            <span>🔄 Refresh</span>
        </button>
    `;
    document.getElementById('refresh-queue').onclick = () => render(container);
}
