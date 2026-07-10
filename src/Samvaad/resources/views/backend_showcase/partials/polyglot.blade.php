<div class="glass-panel">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; border-bottom: 1px solid var(--surface-border); padding-bottom: 1rem;">
        <h2 style="margin: 0; border: none; padding: 0; font-size: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            Microservices Bridge (SPPPolyglot)
        </h2>
        <span style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600;">PHP ⇔ Many</span>
    </div>
    
    <p>SPPPolyglot allows the framework to orchestrate standalone microservices written in Python, Node.js, Go, Java, C++, C#, and Perl as if they were native PHP classes. Data is automatically serialized and exchanged seamlessly.</p>

    <div style="margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap;">
        <!-- Python -->
        <form hx-post="<?= \SPP\App::url('backend-showcase/polyglot/execute', 'samvaad') ?>" hx-target="#polyglot-response" hx-indicator="#poly-spinner">
            <input type="hidden" name="lang" value="py">
            <button type="submit" class="btn" style="background: #3b82f6;">Python</button>
        </form>

        <!-- Node.js -->
        <form hx-post="<?= \SPP\App::url('backend-showcase/polyglot/execute', 'samvaad') ?>" hx-target="#polyglot-response" hx-indicator="#poly-spinner">
            <input type="hidden" name="lang" value="js">
            <button type="submit" class="btn" style="background: #10b981;">Node.js</button>
        </form>

        <!-- Go -->
        <form hx-post="<?= \SPP\App::url('backend-showcase/polyglot/execute', 'samvaad') ?>" hx-target="#polyglot-response" hx-indicator="#poly-spinner">
            <input type="hidden" name="lang" value="go">
            <button type="submit" class="btn" style="background: #06b6d4;">Go</button>
        </form>

        <!-- Java -->
        <form hx-post="<?= \SPP\App::url('backend-showcase/polyglot/execute', 'samvaad') ?>" hx-target="#polyglot-response" hx-indicator="#poly-spinner">
            <input type="hidden" name="lang" value="java">
            <button type="submit" class="btn" style="background: #f43f5e;">Java</button>
        </form>

        <!-- C++ -->
        <form hx-post="<?= \SPP\App::url('backend-showcase/polyglot/execute', 'samvaad') ?>" hx-target="#polyglot-response" hx-indicator="#poly-spinner">
            <input type="hidden" name="lang" value="cpp">
            <button type="submit" class="btn" style="background: #64748b;">C++</button>
        </form>

        <!-- C# -->
        <form hx-post="<?= \SPP\App::url('backend-showcase/polyglot/execute', 'samvaad') ?>" hx-target="#polyglot-response" hx-indicator="#poly-spinner">
            <input type="hidden" name="lang" value="cs">
            <button type="submit" class="btn" style="background: #8b5cf6;">C# (.NET)</button>
        </form>

        <!-- Perl -->
        <form hx-post="<?= \SPP\App::url('backend-showcase/polyglot/execute', 'samvaad') ?>" hx-target="#polyglot-response" hx-indicator="#poly-spinner">
            <input type="hidden" name="lang" value="pl">
            <button type="submit" class="btn" style="background: #d946ef;">Perl</button>
        </form>
    </div>

    <div style="margin-top: 1rem; display: flex; align-items: center; gap: 10px;">
        <span id="poly-spinner" class="htmx-indicator" style="color: #f59e0b;">
            Executing Cross-Language IPC... <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>
        </span>
    </div>

    <div style="margin-top: 1rem;">
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">Microservice Response (JSON to PHP Array)</div>
        <div id="polyglot-response" style="padding: 1.5rem; background: rgba(0,0,0,0.3); border: 1px solid var(--surface-border); border-radius: 8px; font-family: monospace; min-height: 100px;">
            <span style="color: var(--text-muted);">Click a language above to execute its microservice...</span>
        </div>
    </div>
</div>
