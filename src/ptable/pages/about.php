<?php
/**
 * About Page — Shows framework architecture information
 */
?>
<div style="max-width: 800px; margin: 2rem auto; padding: 0 1rem;">
    <div style="background: #fff; border-radius: 16px; padding: 2.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #e2e8f0;">
        <h1 style="margin: 0 0 1rem;">📖 About ptable</h1>
        <p style="color: #64748b; line-height: 1.7;">
            This application was scaffolded by the SPP Framework <code>make:app</code> command.
            Every file is fully commented and serves as a live tutorial.
        </p>
        <h3 style="color: #6366f1; margin-top: 1.5rem;">Architecture</h3>
        <ul style="color: #64748b; line-height: 2;">
            <li><b>Pages</b> (<code>pages/</code>): Server-rendered PHP with augmentation</li>
            <li><b>Components</b> (<code>comp/</code>): SPP-UX reactive components</li>
            <li><b>Controllers</b> (<code>serv/</code>): Business logic & view rendering</li>
            <li><b>Views</b> (<code>resources/views/</code>): Blade templates</li>
            <li><b>Events</b> (<code>events/</code>): Framework event handlers</li>
            <li><b>Tests</b> (<code>tests/</code>): Parikshak unit & evolutionary tests</li>
            <li><b>Config</b> (<code>etc/</code>): Routes, services, forms, settings</li>
        </ul>
    </div>
</div>