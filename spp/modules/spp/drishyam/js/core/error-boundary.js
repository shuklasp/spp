/**
 * SPP-UX Error Boundary — Component-Level Error Recovery
 * 
 * Provides React-style error boundaries that catch rendering errors
 * in child components and display a fallback UI instead of crashing
 * the entire application.
 * 
 * @module core/error-boundary
 * @version 13.0.0
 */

/**
 * Error boundary base class. Extend this instead of BaseComponent
 * when you want a component to catch and recover from errors in
 * its subtree.
 * 
 * Override `renderFallback()` to customize the error display.
 * Override `onError(error, info)` to log errors to a service.
 * 
 * @example
 * class AppErrorBoundary extends ErrorBoundary {
 *     renderFallback() {
 *         return html`
 *             <div class="error-panel">
 *                 <h3>Something went wrong</h3>
 *                 <p>${this.state.error?.message}</p>
 *                 <button @click=${() => this.recover()}>Try Again</button>
 *             </div>
 *         `;
 *     }
 * }
 */
export class ErrorBoundaryMixin {
    /**
     * Apply error boundary capabilities to a BaseComponent class.
     * Called by the framework when integrating.
     * 
     * @param {typeof BaseComponent} BaseClass
     * @returns {typeof BaseComponent} Enhanced class with error boundary
     */
    static applyTo(BaseClass) {
        return class ErrorBoundary extends BaseClass {
            constructor(app, container, props) {
                super(app, container, props);
                this.state = {
                    ...this.state,
                    _hasError: false,
                    _error: null,
                    _errorInfo: null
                };
            }

            /**
             * Called when a child component's render() or lifecycle throws.
             * Override to log errors to a monitoring service.
             * 
             * @param {Error} error - The caught error
             * @param {{ componentName: string, phase: string }} info - Error context
             */
            onError(error, info) {
                console.error(
                    `[SPPUX ErrorBoundary] Caught error in ${info?.componentName || 'unknown'}:`,
                    error
                );
            }

            /**
             * Catch an error from a child component and switch to fallback UI.
             * Called internally by the framework during render/lifecycle.
             * 
             * @param {Error} error
             * @param {Object} [info]
             */
            catchError(error, info = {}) {
                this.onError(error, info);
                // Use direct state assignment + update to avoid re-triggering errors
                this.state = {
                    ...this.state,
                    _hasError: true,
                    _error: error,
                    _errorInfo: info
                };
                // Force a synchronous update to show fallback immediately
                this.update();
            }

            /**
             * Reset the error state and attempt to re-render the original content.
             */
            recover() {
                this.setState({
                    _hasError: false,
                    _error: null,
                    _errorInfo: null
                });
            }

            /**
             * Override the update cycle to intercept render errors.
             */
            update() {
                if (this.state._hasError) {
                    // Render fallback UI directly, bypassing normal render()
                    const fallback = this.renderFallback();
                    if (fallback && this.container) {
                        const temp = document.createElement('div');
                        temp.innerHTML = fallback.toString();
                        this._reconcile(this.container, temp);
                    }
                    return;
                }
                // Normal update path
                super.update();
            }

            /**
             * Override this to provide custom error UI.
             * Default implementation shows a styled error card.
             * 
             * @returns {TrustedHTML}
             */
            renderFallback() {
                const err = this.state._error;
                const info = this.state._errorInfo;
                // Use window.html since we may not have direct access to the import
                const h = window.html || ((s, ...v) => ({ content: String.raw(s, ...v), __isTrusted: true }));
                return h`
                    <div style="margin: 1rem; padding: 1.5rem; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); border-radius: 12px; font-family: system-ui, sans-serif;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                            <span style="font-size: 1.5rem;">💥</span>
                            <div>
                                <div style="font-weight: 700; color: #ef4444; font-size: 1rem;">Component Error</div>
                                <div style="font-size: 0.8rem; opacity: 0.7; color: #ef4444;">${info?.componentName || 'Unknown Component'} · ${info?.phase || 'render'}</div>
                            </div>
                        </div>
                        <pre style="margin: 0; padding: 1rem; background: rgba(0,0,0,0.15); border-radius: 8px; font-size: 0.8rem; color: #f87171; overflow-x: auto; white-space: pre-wrap;">${err?.message || 'Unknown error'}</pre>
                        <div style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
                            <button @click=${() => this.recover()} style="background: #ef4444; color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem;">↻ Retry</button>
                        </div>
                    </div>
                `;
            }
        };
    }
}

/**
 * Walk up a notional component tree to find the nearest error boundary.
 * Since SPP-UX doesn't have a formal component tree (components are
 * DOM-mounted independently), we walk up the DOM to find a container
 * that has an error boundary component mounted on it.
 * 
 * @param {Element} startElement - Element where the error occurred
 * @param {Set<BaseComponent>} components - Active component set (SPPUX._components)
 * @returns {Object|null} Error boundary component instance or null
 */
export function findNearestErrorBoundary(startElement, components) {
    if (!startElement || !components) return null;

    let el = startElement.parentElement;
    while (el) {
        for (const comp of components) {
            if (comp.container === el && typeof comp.catchError === 'function') {
                return comp;
            }
        }
        el = el.parentElement;
    }
    return null;
}
