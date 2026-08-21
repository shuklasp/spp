/**
 * ============================================================================
 * Counter Sub-Component — ptable
 * ============================================================================
 *
 * Demonstrates:
 *   - Props: receive data from parent via data-spp-props
 *   - Local state: independent state management
 *   - Lifecycle: onInit, onMount, onDestroy, afterUpdate
 *   - Events: communicating with parent components
 *
 * HOW TO MOUNT THIS COMPONENT:
 *
 *   From SPP-UX (in another component's render()):
 *     html`<div data-spp-component="1" data-spp-type="ux"
 *               data-spp-path="${SPPUX.componentBase + '/counter.js'}"
 *               data-spp-props='{"initialCount": 5}'></div>`
 *
 *   From PHP (in a Blade template or PHP page):
 *     <?php \SPPMod\Drishyam\SPPUX::render('counter', ['initialCount' => 5]); ?>
 *
 *   From Blade:
 *     @sppux('counter', ['initialCount' => 5])
 * ============================================================================
 */
export default class Counter extends BaseComponent {

    async onInit() {
        // Props are available as this.props (set via data-spp-props or PHP)
        this.setState({
            count: this.props.initialCount || 0,
            history: []
        });
    }

    onMount() {
        // DOM is ready — you can access this.container here
        console.log('[Counter] Mounted with initial count:', this.state.count);
    }

    afterUpdate() {
        // Called after every re-render triggered by setState
        // Useful for DOM measurements, scroll restoration, etc.
    }

    onDestroy() {
        // Cleanup: clear timers, remove event listeners, etc.
        console.log('[Counter] Destroyed');
    }

    increment() {
        const newCount = this.state.count + 1;
        this.setState({
            count: newCount,
            history: [...this.state.history, { action: '+1', value: newCount, time: new Date().toLocaleTimeString() }]
        });
    }

    decrement() {
        const newCount = this.state.count - 1;
        this.setState({
            count: newCount,
            history: [...this.state.history, { action: '-1', value: newCount, time: new Date().toLocaleTimeString() }]
        });
    }

    reset() {
        this.setState({ count: 0, history: [] });
        this.notify('Counter reset!', 'info');
    }

    render() {
        return html`
            <div style="padding:1.5rem; background:rgba(255,255,255,0.03); border-radius:14px; border:1px solid rgba(255,255,255,0.06);">
                <h3 style="margin-top:0;">🧮 Counter Component</h3>
                <p style="opacity:0.5; font-size:0.85rem;">Props: initialCount=${this.props.initialCount || 0}</p>

                <div style="display:flex; align-items:center; gap:1rem; margin:1rem 0;">
                    <button @click="${() => this.decrement()}" style="width:40px;height:40px;border-radius:10px;border:1px solid rgba(255,255,255,0.1);background:rgba(239,68,68,0.15);color:#ef4444;cursor:pointer;font-size:1.2rem;">−</button>
                    <span style="font-size:2rem; font-weight:800; min-width:60px; text-align:center;">${this.state.count}</span>
                    <button @click="${() => this.increment()}" style="width:40px;height:40px;border-radius:10px;border:1px solid rgba(255,255,255,0.1);background:rgba(34,197,94,0.15);color:#22c55e;cursor:pointer;font-size:1.2rem;">+</button>
                    <button @click="${() => this.reset()}" style="padding:0.5rem 1rem;border-radius:8px;border:1px solid rgba(255,255,255,0.1);background:transparent;cursor:pointer;font-family:inherit;color:inherit;opacity:0.6;">Reset</button>
                </div>

                ${this.state.history.length > 0 ? html`
                    <details style="margin-top:1rem;">
                        <summary style="cursor:pointer; opacity:0.5; font-size:0.85rem;">History (${this.state.history.length} actions)</summary>
                        <div style="margin-top:0.5rem; font-size:0.8rem; font-family:monospace; opacity:0.5;">
                            ${this.state.history.slice(-5).map(h => html`
                                <div>${h.time}: ${h.action} → ${h.value}</div>
                            `)}
                        </div>
                    </details>
                ` : ''}
            </div>
        `;
    }
}