/**
 * SPP-UX Universal Bridge
 *
 * Provides a standardized way to integrate 3rd-party libraries (Chart.js, Quill, etc.)
 * into the SPP-UX component lifecycle.
 */

(function(SPPUX) {
    if (!SPPUX) return;

    SPPUX.Bridge = {
        _loaded: new Set(),

        /**
         * Load a 3rd party script and/or stylesheet dynamically.
         * Returns a promise that resolves when all are loaded.
         */
        async load(assets) {
            if (typeof assets === 'string') assets = [assets];
            
            const promises = assets.map(src => {
                if (this._loaded.has(src)) return Promise.resolve();

                return new Promise((resolve, reject) => {
                    let el;
                    if (src.endsWith('.css')) {
                        el = document.createElement('link');
                        el.rel = 'stylesheet';
                        el.href = src;
                    } else {
                        el = document.createElement('script');
                        el.src = src;
                    }

                    el.addEventListener('load', () => {
                        this._loaded.add(src);
                        resolve();
                    });
                    el.addEventListener('error', () => reject(new Error(`Failed to load bridge asset: ${src}`)));
                    document.head.appendChild(el);
                });
            });

            return Promise.all(promises);
        },

        /**
         * Standardized wrapper for 3rd party initialization.
         */
        createWrapper(options) {
            const { assets, init, cleanup } = options;
            return class extends BaseComponent {
                constructor(a, b, c) {
                    let app = null, container = null, props = {};
                    if (a instanceof HTMLElement || typeof a === 'string') {
                        container = typeof a === 'string' ? document.querySelector(a) : a;
                        props = b || {};
                    } else if (b instanceof HTMLElement || typeof b === 'string') {
                        if (c !== undefined) {
                            app = a;
                            container = typeof b === 'string' ? document.querySelector(b) : b;
                            props = c || {};
                        } else {
                            props = a || {};
                            container = typeof b === 'string' ? document.querySelector(b) : b;
                        }
                    } else {
                        app = a;
                        container = b;
                        props = c || {};
                    }
                    super(app, container, props);
                }
                async onMount() {
                    if (assets) await SPPUX.Bridge.load(assets);
                    this.instance = init.call(this, this.container, this.props);
                }
                onDestroy() {
                    if (cleanup) cleanup.call(this, this.instance);
                }
            };
        }
    };

})(window.SPPUX);
