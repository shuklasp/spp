<div class="glass-panel">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; border-bottom: 1px solid var(--surface-border); padding-bottom: 1rem;">
        <h2 style="margin: 0; border: none; padding: 0; font-size: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line></svg>
            DAG Job Orchestrator (SPPQueue)
        </h2>
        <span style="background: rgba(96, 165, 250, 0.2); color: #60a5fa; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600;">Directed Acyclic Graph</span>
    </div>
    
    <p>SPPQueue handles complex background job orchestration natively, supporting dependency resolution, DAGs (Directed Acyclic Graphs), retries, and rate limiting via a token bucket algorithm.</p>

    <div style="margin-top: 2rem;">
        <!-- Container for polling the queue status every 2 seconds -->
        <div hx-get="<?= \SPP\App::url('backend-showcase/queue/status', 'samvaad') ?>" hx-trigger="load, every 2s">
            <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                Initializing DAG simulation...
            </div>
        </div>
    </div>
</div>
