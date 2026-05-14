<div class="page-view" style="animation: fadeIn 0.3s ease;">
    <h2>📖 Architectural Flow Explained</h2>
    <p style="color: var(--text-dim); line-height: 1.6;">
        Every SPP application operates cleanly decoupled:
    </p>
    <ul style="text-align: left; color: var(--text-dim); line-height: 1.8;">
        <li><b>Pages</b> (<code>/pages/</code>): Contain raw layout blueprints with Zero-JS directive hooks.</li>
        <li><b>Components</b> (<code>/components/</code>): Reusable modular UI building blocks.</li>
        <li><b>Services</b> (<code>/serv/</code>): Isolated data processing endpoints mapping directly to client targets.</li>
        <li><b>Events</b> (<code>/events/</code>): Houses localized domain event listeners and overriding hook handlers natively.</li>
        <li><b>Assets</b> (<code>/assets/</code>): Holds full local application media branding routed statically.</li>
        <li><b>Configurations</b> (<code>/etc/</code>): Application-specific definitions and service maps.</li>
    </ul>
    <button class="btn" style="margin-top:1rem;" onclick="location.reload()">Back to Live Preview</button>
</div>