<div class="glass-panel">
    <h2>Welcome to the Backend Showcase</h2>
    <p>This section of the Samvaad application is designed to demonstrate the powerful enterprise capabilities built into the SPP framework. Unlike the SPP-UX showcase, this dashboard relies on <strong>traditional server-side rendering (Blade)</strong> and <strong>HTMX</strong>.</p>
    
    <div style="margin-top: 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 8px;">
            <h3 style="margin-top: 0; color: #60a5fa;">HTMX & External Partials</h3>
            <p style="font-size: 0.9rem;">
                Navigating between these tabs does not trigger a full page reload. Instead, the `BackendShowcaseController` detects the `HX-Request` header and serves <strong>external partial views</strong> directly into the DOM container.
            </p>
        </div>
        <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 8px;">
            <h3 style="margin-top: 0; color: #34d399;">Enterprise Backend</h3>
            <p style="font-size: 0.9rem;">
                Click through the sidebar to see demonstrations of SPPEntity (ORM), the CQRS Event Store, Workflow Management (Saga Pattern), and PHP 8 Attribute Routing.
            </p>
        </div>
    </div>
</div>
