/**
 * SPPEX Pro (Air-Gapped Sovereign Expansion)
 * 
 * 10 native, zero-dependency implementations of the most heavily relied-upon 
 * enterprise primitives, bypassing the need for 100+ external React packages.
 */

(function(global) {
    const SPPEX = global.SPPEX || {};

    /**
     * 1. SPPEX.VirtualList (Port of react-window)
     * Renders only the visible items in a massive list to prevent DOM crashing.
     */
    SPPEX.VirtualList = class {
        constructor(container, itemHeight, totalItems, renderItem) {
            this.container = typeof container === 'string' ? document.querySelector(container) : container;
            this.itemHeight = itemHeight;
            this.totalItems = totalItems;
            this.renderItem = renderItem;
            
            this.container.style.overflowY = 'auto';
            this.container.style.position = 'relative';
            
            this.inner = document.createElement('div');
            this.inner.style.height = (totalItems * itemHeight) + 'px';
            this.inner.style.position = 'relative';
            this.inner.style.width = '100%';
            
            this.container.appendChild(this.inner);
            this.container.addEventListener('scroll', () => this.renderVisible());
            
            this.renderVisible();
        }

        renderVisible() {
            const scrollTop = this.container.scrollTop;
            const viewportHeight = this.container.clientHeight;
            
            const startIndex = Math.max(0, Math.floor(scrollTop / this.itemHeight) - 2);
            const endIndex = Math.min(this.totalItems - 1, Math.ceil((scrollTop + viewportHeight) / this.itemHeight) + 2);
            
            this.inner.innerHTML = ''; // Fast clear
            
            for (let i = startIndex; i <= endIndex; i++) {
                const el = document.createElement('div');
                el.style.position = 'absolute';
                el.style.top = (i * this.itemHeight) + 'px';
                el.style.left = '0';
                el.style.right = '0';
                el.style.height = this.itemHeight + 'px';
                
                const htmlContent = this.renderItem(i);
                if (typeof htmlContent === 'string') el.innerHTML = htmlContent;
                else el.appendChild(htmlContent); // Handle DOM nodes if necessary
                
                this.inner.appendChild(el);
            }
        }
    };

    /**
     * 2. SPPEX.InfiniteScroll (Port of react-infinite-scroll-component)
     * Auto-loads data when the user reaches the bottom.
     */
    SPPEX.InfiniteScroll = {
        init(containerSelector, onLoadMore) {
            const container = document.querySelector(containerSelector);
            if (!container) return;
            
            const trigger = document.createElement('div');
            trigger.className = 'sppex-infinite-trigger';
            trigger.style.height = '1px';
            trigger.style.width = '100%';
            container.appendChild(trigger);

            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    onLoadMore().then(() => {
                        // Re-append trigger to bottom after new items are added
                        container.appendChild(trigger);
                    });
                }
            }, { root: null, rootMargin: '100px' });

            observer.observe(trigger);
        }
    };

    /**
     * 3. SPPEX.StoreSync (Port of useLocalStorage hooks)
     * Syncs a Proxy store with LocalStorage automatically.
     */
    SPPEX.StoreSync = {
        attach(storeKey, proxyStore) {
            const saved = localStorage.getItem(storeKey);
            if (saved) {
                try {
                    const parsed = JSON.parse(saved);
                    for (const [k, v] of Object.entries(parsed)) {
                        proxyStore[k] = v; // Triggers proxy setters silently? Careful not to loop.
                    }
                } catch(e) {}
            }
            
            proxyStore.subscribe(() => {
                // Warning: In a real environment, we'd need a specific deep extraction
                // Assuming standard SPPUX proxy allows JSON stringify (it might fail if cyclical)
                try {
                    localStorage.setItem(storeKey, JSON.stringify(proxyStore.get()));
                } catch(e) { console.error("StoreSync error:", e); }
            });
        }
    };

    /**
     * 4. SPPEX.Machine (Port of xstate)
     * Finite State Machine for complex logic.
     */
    SPPEX.Machine = class {
        constructor(config) {
            this.state = config.initial;
            this.states = config.states;
            this.context = config.context || {};
            this.subscribers = new Set();
        }

        transition(event, payload = {}) {
            const currentStateConfig = this.states[this.state];
            if (!currentStateConfig || !currentStateConfig.on || !currentStateConfig.on[event]) {
                console.warn(`Invalid transition: ${event} from ${this.state}`);
                return;
            }

            const target = currentStateConfig.on[event];
            this.state = target;
            
            // Execute actions if defined
            const targetConfig = this.states[target];
            if (targetConfig && targetConfig.entry) {
                targetConfig.entry(this.context, payload);
            }

            this.notify();
        }

        subscribe(fn) {
            this.subscribers.add(fn);
            return () => this.subscribers.delete(fn);
        }

        notify() {
            this.subscribers.forEach(fn => fn(this.state, this.context));
        }
    };

    /**
     * 5. SPPEX.Carousel (Port of react-slick)
     * Native CSS scroll-snap slider wrapper.
     */
    SPPEX.Carousel = class {
        constructor(selector) {
            this.container = document.querySelector(selector);
            if (!this.container) return;
            
            // Apply native CSS rules dynamically
            this.container.style.display = 'flex';
            this.container.style.overflowX = 'auto';
            this.container.style.scrollSnapType = 'x mandatory';
            this.container.style.scrollBehavior = 'smooth';
            this.container.style.scrollbarWidth = 'none'; // Firefox
            
            Array.from(this.container.children).forEach(child => {
                child.style.flex = '0 0 100%';
                child.style.scrollSnapAlign = 'start';
            });
        }
        
        next() {
            const width = this.container.clientWidth;
            this.container.scrollBy({ left: width, behavior: 'smooth' });
        }
        
        prev() {
            const width = this.container.clientWidth;
            this.container.scrollBy({ left: -width, behavior: 'smooth' });
        }
    };

    /**
     * 6. SPPEX.Floating (Port of @floating-ui)
     * Edge-aware tooltips.
     */
    SPPEX.Floating = {
        init() {
            document.addEventListener('mouseover', (e) => {
                const trigger = e.target.closest('[data-sppex-float]');
                if (!trigger || trigger._floatingActive) return;
                
                trigger._floatingActive = true;
                const tooltipText = trigger.getAttribute('data-sppex-float');
                
                const tooltip = document.createElement('div');
                tooltip.className = 'sppex-floating-tooltip';
                tooltip.innerHTML = tooltipText;
                tooltip.style.position = 'absolute';
                tooltip.style.background = '#333';
                tooltip.style.color = '#fff';
                tooltip.style.padding = '5px 10px';
                tooltip.style.borderRadius = '4px';
                tooltip.style.zIndex = '9999';
                tooltip.style.pointerEvents = 'none';
                
                document.body.appendChild(tooltip);
                
                const rect = trigger.getBoundingClientRect();
                const tooltipRect = tooltip.getBoundingClientRect();
                
                let top = rect.top - tooltipRect.height - 8;
                let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
                
                // Flip if hitting top edge
                if (top < 0) {
                    top = rect.bottom + 8;
                }
                
                // Shift if hitting side edges
                if (left < 0) left = 8;
                if (left + tooltipRect.width > window.innerWidth) left = window.innerWidth - tooltipRect.width - 8;
                
                tooltip.style.top = top + window.scrollY + 'px';
                tooltip.style.left = left + window.scrollX + 'px';
                
                const remove = () => {
                    tooltip.remove();
                    delete trigger._floatingActive;
                    trigger.removeEventListener('mouseleave', remove);
                };
                trigger.addEventListener('mouseleave', remove);
            });
        }
    };

    /**
     * 7. SPPEX.Select (Port of react-select)
     * Typeahead multi-select logic.
     */
    SPPEX.Select = class {
        // A minimal wrapper that transforms a standard <select multiple> 
        // into a pill-based visual UI.
        constructor(selector) {
            const selectEl = document.querySelector(selector);
            if (!selectEl || selectEl.tagName !== 'SELECT') return;
            
            selectEl.style.display = 'none';
            const wrapper = document.createElement('div');
            wrapper.className = 'sppex-select-wrapper';
            wrapper.style.border = '1px solid #ccc';
            wrapper.style.padding = '5px';
            wrapper.style.display = 'flex';
            wrapper.style.flexWrap = 'wrap';
            wrapper.style.gap = '5px';
            wrapper.style.minHeight = '38px';
            wrapper.style.cursor = 'pointer';
            
            const pillContainer = document.createElement('div');
            pillContainer.style.display = 'flex';
            pillContainer.style.gap = '5px';
            
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.style.border = 'none';
            searchInput.style.outline = 'none';
            searchInput.style.flex = '1';
            
            const dropdown = document.createElement('div');
            dropdown.style.position = 'absolute';
            dropdown.style.background = '#fff';
            dropdown.style.border = '1px solid #ccc';
            dropdown.style.display = 'none';
            dropdown.style.maxHeight = '200px';
            dropdown.style.overflowY = 'auto';
            dropdown.style.zIndex = '100';
            
            wrapper.appendChild(pillContainer);
            wrapper.appendChild(searchInput);
            selectEl.parentNode.insertBefore(wrapper, selectEl.nextSibling);
            wrapper.appendChild(dropdown); // Positioned relative to wrapper usually, simplified here

            const renderPills = () => {
                pillContainer.innerHTML = '';
                Array.from(selectEl.selectedOptions).forEach(opt => {
                    const pill = document.createElement('span');
                    pill.style.background = '#e0e0e0';
                    pill.style.padding = '2px 8px';
                    pill.style.borderRadius = '12px';
                    pill.style.fontSize = '12px';
                    pill.innerHTML = `${opt.text} <span style="cursor:pointer; color:red; margin-left:4px;">x</span>`;
                    pill.querySelector('span').onclick = (e) => {
                        e.stopPropagation();
                        opt.selected = false;
                        selectEl.dispatchEvent(new Event('change'));
                        renderPills();
                    };
                    pillContainer.appendChild(pill);
                });
            };

            const renderDropdown = (query = '') => {
                dropdown.innerHTML = '';
                let hasMatch = false;
                Array.from(selectEl.options).forEach(opt => {
                    if (!opt.selected && opt.text.toLowerCase().includes(query.toLowerCase())) {
                        hasMatch = true;
                        const item = document.createElement('div');
                        item.style.padding = '8px';
                        item.style.cursor = 'pointer';
                        item.textContent = opt.text;
                        item.onmouseover = () => item.style.background = '#f5f5f5';
                        item.onmouseout = () => item.style.background = 'transparent';
                        item.onclick = () => {
                            opt.selected = true;
                            selectEl.dispatchEvent(new Event('change'));
                            renderPills();
                            searchInput.value = '';
                            dropdown.style.display = 'none';
                        };
                        dropdown.appendChild(item);
                    }
                });
                if (hasMatch) dropdown.style.display = 'block';
                else dropdown.style.display = 'none';
            };

            wrapper.onclick = () => {
                searchInput.focus();
                renderDropdown(searchInput.value);
            };
            
            searchInput.oninput = (e) => renderDropdown(e.target.value);
            
            document.addEventListener('click', (e) => {
                if (!wrapper.contains(e.target)) dropdown.style.display = 'none';
            });
            
            renderPills();
        }
    };

    /**
     * 8. SPPEX.DatePicker (Port of react-datepicker)
     * Enhances HTML5 date inputs with a visual calendar if needed, 
     * but relies on the browser's native picker for true zero-dependency power.
     */
    SPPEX.DatePicker = {
        init(selector) {
            const inputs = document.querySelectorAll(selector);
            inputs.forEach(input => {
                if (input.type !== 'date') input.type = 'date';
                input.style.padding = '8px';
                input.style.border = '1px solid #ccc';
                input.style.borderRadius = '4px';
                input.style.fontFamily = 'inherit';
            });
        }
    };

    /**
     * 9. SPPEX.Markdown (Port of react-markdown)
     * Native regex-based micro-parser.
     */
    SPPEX.Markdown = {
        parse(text) {
            let html = text
                .replace(/^### (.*$)/gim, '<h3>$1</h3>')
                .replace(/^## (.*$)/gim, '<h2>$1</h2>')
                .replace(/^# (.*$)/gim, '<h1>$1</h1>')
                .replace(/\\*\\*(.*?)\\*\\*/gim, '<strong>$1</strong>')
                .replace(/\\*(.*?)\\*/gim, '<em>$1</em>')
                .replace(/\\[(.*?)\\]\\((.*?)\\)/gim, '<a href="$2">$1</a>')
                .replace(/\\n$/gim, '<br />');
            return html.trim();
        }
    };

    /**
     * 10. SPPEX.i18n (Port of react-i18next)
     * Reactive dictionary manager.
     */
    SPPEX.i18n = {
        _dict: {},
        _locale: 'en',
        _listeners: new Set(),
        
        init(resources, defaultLocale = 'en') {
            this._dict = resources;
            this._locale = defaultLocale;
        },
        
        changeLanguage(locale) {
            this._locale = locale;
            this._listeners.forEach(fn => fn(locale));
        },
        
        t(key) {
            const langDict = this._dict[this._locale] || {};
            return langDict[key] || key;
        },
        
        subscribe(fn) {
            this._listeners.add(fn);
            return () => this._listeners.delete(fn);
        }
    };

    // Auto-init Floating UI
    if (typeof window !== 'undefined') {
        window.addEventListener('DOMContentLoaded', () => SPPEX.Floating.init());
    }

    global.SPPEX = SPPEX;

})(window);
