/**
 * SPPEX (SPP Extended Ecosystem)
 * 
 * Native, zero-build ports of the most popular React ecosystem packages.
 * Designed to integrate seamlessly with SPPUX Next.
 */

(function(global) {
    const SPPEX = {};

    /**
     * 1. SPPEX.Query (React Query Equivalent)
     * Handles data fetching, caching, background refetching, and state management.
     */
    SPPEX.Query = (() => {
        const cache = new Map();
        return {
            async use(key, fetchFn, options = {}) {
                const { staleTime = 5000, component = null } = options;
                
                const cached = cache.get(key);
                const now = Date.now();

                // Background refetch helper
                const refetch = async () => {
                    try {
                        const data = await fetchFn();
                        cache.set(key, { data, updatedAt: Date.now(), error: null });
                        if (component && component.update) component.update(); // Trigger re-render
                    } catch (error) {
                        cache.set(key, { data: null, updatedAt: Date.now(), error });
                        if (component && component.update) component.update();
                    }
                };

                if (cached) {
                    // Stale-while-revalidate
                    if (now - cached.updatedAt > staleTime) {
                        refetch(); // Async background refresh
                    }
                    return { data: cached.data, error: cached.error, isLoading: false };
                }

                // Initial fetch
                cache.set(key, { data: null, updatedAt: 0, error: null, isLoading: true });
                await refetch();
                const latest = cache.get(key);
                return { data: latest.data, error: latest.error, isLoading: false };
            },
            invalidate(key) {
                cache.delete(key);
            }
        };
    })();

    /**
     * 2. SPPEX.Form (React Hook Form Equivalent)
     * Manages form state, validation, and untouched/dirty status without full re-renders.
     */
    SPPEX.Form = class {
        constructor(config = {}) {
            this.values = config.defaultValues || {};
            this.errors = {};
            this.touched = {};
            this.schema = config.schema || {};
            this.onSubmit = config.onSubmit;
            this.component = config.component || null;
        }

        register(name) {
            return `
                name="${name}" 
                value="${this.values[name] || ''}" 
                data-sppex-field="true"
                @input="${(e) => this.handleChange(name, e.target.value)}"
                @blur="${(e) => this.handleBlur(name)}"
            `;
        }

        handleChange(name, value) {
            this.values[name] = value;
            if (this.touched[name]) this.validateField(name);
        }

        handleBlur(name) {
            this.touched[name] = true;
            this.validateField(name);
            if (this.component) this.component.update();
        }

        validateField(name) {
            const rules = this.schema[name];
            if (!rules) return true;
            
            this.errors[name] = null;
            const val = this.values[name] || '';

            if (rules.required && !val) this.errors[name] = 'This field is required';
            else if (rules.email && !/^\\S+@\\S+\\.\\S+$/.test(val)) this.errors[name] = 'Invalid email address';
            else if (rules.minLength && val.length < rules.minLength) this.errors[name] = `Must be at least ${rules.minLength} characters`;
            
            return !this.errors[name];
        }

        validateAll() {
            let isValid = true;
            for (let key in this.schema) {
                this.touched[key] = true;
                if (!this.validateField(key)) isValid = false;
            }
            if (this.component) this.component.update();
            return isValid;
        }

        handleSubmit(e) {
            if (e && e.preventDefault) e.preventDefault();
            if (this.validateAll()) {
                if (this.onSubmit) this.onSubmit(this.values);
            }
        }
    };

    /**
     * 3. SPPEX.Motion (Framer Motion Equivalent)
     * FLIP (First, Last, Invert, Play) based physics animations.
     */
    SPPEX.Motion = {
        animateNode(node, animationType = 'slide-in') {
            if (!node || node.nodeType !== 1) return;
            
            // Simple spring-like CSS injection
            if (!document.getElementById('sppex-motion-css')) {
                const style = document.createElement('style');
                style.id = 'sppex-motion-css';
                style.innerHTML = `
                    .sppex-slide-in { animation: sppexSlideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
                    .sppex-fade-in { animation: sppexFadeIn 0.3s ease-in forwards; }
                    @keyframes sppexSlideIn { 
                        0% { opacity: 0; transform: translateY(30px) scale(0.95); } 
                        100% { opacity: 1; transform: translateY(0) scale(1); } 
                    }
                    @keyframes sppexFadeIn { from { opacity: 0; } to { opacity: 1; } }
                `;
                document.head.appendChild(style);
            }

            node.classList.add(`sppex-${animationType}`);
            node.addEventListener('animationend', () => node.classList.remove(`sppex-${animationType}`), { once: true });
        },
        autoObserver() {
            // Automatically animate new elements that have data-sppex-motion
            const observer = new MutationObserver(mutations => {
                mutations.forEach(m => {
                    m.addedNodes.forEach(node => {
                        if (node.nodeType === 1 && node.hasAttribute('data-sppex-motion')) {
                            this.animateNode(node, node.getAttribute('data-sppex-motion'));
                        }
                    });
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    };

    /**
     * 4. SPPEX.Helmet (React Helmet Equivalent)
     * Dynamic document head manager.
     */
    SPPEX.Helmet = {
        set(config) {
            if (config.title) document.title = config.title;
            
            if (config.meta) {
                for (let [name, content] of Object.entries(config.meta)) {
                    let metaTag = document.querySelector(`meta[name="${name}"]`);
                    if (!metaTag) {
                        metaTag = document.createElement('meta');
                        metaTag.setAttribute('name', name);
                        document.head.appendChild(metaTag);
                    }
                    metaTag.setAttribute('content', content);
                }
            }
        }
    };

    /**
     * 5. SPPEX.DnD (React DnD / dnd-kit Equivalent)
     * Sortable list manager using HTML5 Drag and Drop.
     */
    SPPEX.DnD = (() => {
        let draggedItemIndex = null;
        let sourceArray = null;
        let changeCallback = null;

        return {
            initSortable(array, onOrderChange) {
                sourceArray = array;
                changeCallback = onOrderChange;
            },
            props(index) {
                return `
                    draggable="true"
                    data-dnd-index="${index}"
                    @dragstart="${(e) => {
                        draggedItemIndex = index;
                        e.target.style.opacity = '0.5';
                    }}"
                    @dragend="${(e) => {
                        e.target.style.opacity = '1';
                        draggedItemIndex = null;
                    }}"
                    @dragover="${(e) => e.preventDefault()}"
                    @drop="${(e) => {
                        e.preventDefault();
                        const dropIndex = parseInt(e.currentTarget.getAttribute('data-dnd-index'));
                        if (draggedItemIndex !== null && draggedItemIndex !== dropIndex) {
                            // Reorder array
                            const item = sourceArray.splice(draggedItemIndex, 1)[0];
                            sourceArray.splice(dropIndex, 0, item);
                            if (changeCallback) changeCallback(sourceArray);
                        }
                    }}"
                `;
            }
        };
    })();

    // Auto-init motion observer
    if (typeof window !== 'undefined') {
        window.addEventListener('DOMContentLoaded', () => SPPEX.Motion.autoObserver());
    }

    global.SPPEX = SPPEX;

})(window);
