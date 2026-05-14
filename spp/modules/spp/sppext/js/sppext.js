/**
 * SPP-UX Extension Library (sppext) - Premium Version
 * Providing native-feeling wrappers for the world's most powerful JS libraries.
 */

(function() {
    const initSppExt = (SPPUX) => {
        if (!SPPUX || !SPPUX.Bridge) return false;
        
        console.log("🚀 Initializing SPPEXT Premium Components...");

        const UI = {
            wrap(container, title, icon = 'Active') {
                if (container.parentNode.classList.contains('spp-ext-container')) return container;
                const wrapper = document.createElement('div');
                wrapper.className = 'spp-ext-container';
                const header = document.createElement('div');
                header.className = 'spp-ext-header';
                header.innerHTML = `<span class="spp-ext-title">${title}</span><span class="spp-ext-status">${icon}</span>`;
                container.parentNode.insertBefore(wrapper, container);
                wrapper.appendChild(header);
                wrapper.appendChild(container);
                return container;
            }
        };

    /**
     * Rich Text Editor (Quill)
     */
    SPPUX.Editor = SPPUX.Bridge.createWrapper({
        assets: [
            'https://cdn.quilljs.com/1.3.6/quill.snow.css',
            'https://cdn.quilljs.com/1.3.6/quill.js'
        ],
        init(container, props) {
            const content = UI.wrap(container, props.label || 'Rich Text Editor');
            content.style.height = props.height || '300px';
            const quill = new Quill(content, { theme: 'snow', placeholder: props.placeholder });
            if (props.name) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden'; hidden.name = props.name;
                container.appendChild(hidden);
                quill.on('text-change', () => hidden.value = quill.root.innerHTML);
                if (props.value) quill.root.innerHTML = props.value;
            }
            return quill;
        }
    });

    /**
     * Code Editor (Monaco)
     */
    SPPUX.Code = SPPUX.Bridge.createWrapper({
        assets: ['res/spp/js/monaco.js'],
        init(container, props) {
            const content = UI.wrap(container, props.label || 'Code Editor');
            content.style.height = props.height || '400px';
            
            require.config({ paths: { vs: 'res/spp/js' }});
            require(['vs/editor/editor.main'], () => {
                const editor = monaco.editor.create(content, {
                    value: props.value || '',
                    language: props.language || 'javascript',
                    theme: 'vs-dark',
                    automaticLayout: true,
                    minimap: { enabled: false },
                    fontSize: 14,
                    padding: { top: 10 }
                });
                if (props.name) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden'; hidden.name = props.name;
                    container.appendChild(hidden);
                    editor.onDidChangeModelContent(() => hidden.value = editor.getValue());
                }
                this.instance = editor;
            });
        },
        cleanup(instance) { if(instance) instance.dispose(); }
    });

    /**
     * Interactive Maps (Leaflet)
     */
    SPPUX.Map = SPPUX.Bridge.createWrapper({
        assets: [
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
        ],
        init(container, props) {
            const content = UI.wrap(container, props.label || 'Geospatial Map');
            content.style.height = props.height || '400px';
            const map = L.map(content).setView(props.center || [0, 0], props.zoom || 2);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
            if (props.markers) {
                props.markers.forEach(m => {
                    const pos = Array.isArray(m) ? [m[0], m[1]] : m.pos;
                    const text = Array.isArray(m) ? m[2] : m.text;
                    L.marker(pos).addTo(map).bindPopup(text || '');
                });
            }
            return map;
        },
        cleanup(instance) { instance.remove(); }
    });

    /**
     * Advanced Calendar (Flatpickr)
     */
    SPPUX.Calendar = SPPUX.Bridge.createWrapper({
        assets: [
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
            'https://cdn.jsdelivr.net/npm/flatpickr'
        ],
        init(container, props) {
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'sppux-input sppux-calendar';
            input.placeholder = props.placeholder || 'Select date...';
            input.value = props.value || '';
            container.appendChild(input);
            return flatpickr(input, {
                enableTime: props.enableTime || false,
                mode: props.mode || 'single',
                dateFormat: props.format || 'Y-m-d',
                theme: 'dark',
                ...props.options
            });
        },
        cleanup(instance) { instance.destroy(); }
    });

    /**
     * Sortable Lists (SortableJS)
     */
    SPPUX.Sortable = SPPUX.Bridge.createWrapper({
        assets: ['https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js'],
        init(container, props) {
            const content = UI.wrap(container, props.label || 'Sortable List');
            const list = document.createElement('div');
            list.className = 'spp-sortable-list';
            content.appendChild(list);
            if (props.items) {
                props.items.forEach(item => {
                    const el = document.createElement('div');
                    el.className = 'spp-sortable-item';
                    el.innerHTML = `<span class="spp-sortable-handle">☰</span> <span>${item.html || item.content || item.text || item}</span>`;
                    el.dataset.id = item.id || '';
                    list.appendChild(el);
                });
            }
            return new Sortable(list, {
                animation: 150,
                ghostClass: 'ghost',
                onEnd: (evt) => { if(props.onSort && window[props.onSort]) window[props.onSort](evt); }
            });
        }
    });

    return true;
    };

    // Polling for Bridge
    const poll = () => {
        if (window.SPPUX && window.SPPUX.Bridge) {
            if (initSppExt(window.SPPUX)) return;
        }
        setTimeout(poll, 50);
    };
    poll();
})();
