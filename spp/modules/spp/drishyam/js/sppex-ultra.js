/**
 * SPPEX Ultra (The Next 20 Packages)
 * 
 * 20 native, zero-dependency implementations covering the next tier
 * of advanced UI and structural primitives.
 */

(function(global) {
    const SPPEX = global.SPPEX || {};

    // ==========================================
    // 1. ADVANCED DATA & LAYOUTS
    // ==========================================

    /**
     * 1. SPPEX.DataGrid (Port of ag-grid)
     */
    SPPEX.DataGrid = class {
        constructor(selector, columns, data) {
            this.container = document.querySelector(selector);
            this.columns = columns;
            this.data = data;
            this.sortCol = null;
            this.sortAsc = true;
            this.render();
        }
        sortData(colField) {
            if (this.sortCol === colField) this.sortAsc = !this.sortAsc;
            else { this.sortCol = colField; this.sortAsc = true; }
            this.data.sort((a, b) => {
                if (a[colField] < b[colField]) return this.sortAsc ? -1 : 1;
                if (a[colField] > b[colField]) return this.sortAsc ? 1 : -1;
                return 0;
            });
            this.render();
        }
        render() {
            if (!this.container) return;
            let html = '<table style="width:100%; border-collapse:collapse; text-align:left;"><thead><tr>';
            this.columns.forEach(col => {
                const arrow = this.sortCol === col.field ? (this.sortAsc ? ' ↑' : ' ↓') : '';
                html += `<th style="padding:10px; border-bottom:2px solid #ccc; cursor:pointer;" data-field="${col.field}">${col.headerName}${arrow}</th>`;
            });
            html += '</tr></thead><tbody>';
            this.data.forEach(row => {
                html += '<tr>';
                this.columns.forEach(col => {
                    html += `<td style="padding:10px; border-bottom:1px solid #eee;">${row[col.field] || ''}</td>`;
                });
                html += '</tr>';
            });
            html += '</tbody></table>';
            this.container.innerHTML = html;
            this.container.querySelectorAll('th').forEach(th => {
                th.onclick = () => this.sortData(th.getAttribute('data-field'));
            });
        }
    };

    /**
     * 2. SPPEX.Masonry (Port of react-masonry-css)
     */
    SPPEX.Masonry = {
        init(selector, columns = 3) {
            const container = document.querySelector(selector);
            if (!container) return;
            container.style.columnCount = columns;
            container.style.columnGap = '20px';
            Array.from(container.children).forEach(child => {
                child.style.breakInside = 'avoid';
                child.style.marginBottom = '20px';
            });
        }
    };

    /**
     * 3. SPPEX.Resizable (Port of react-resizable)
     */
    SPPEX.Resizable = {
        init(selector) {
            document.querySelectorAll(selector).forEach(el => {
                el.style.resize = 'both';
                el.style.overflow = 'auto';
            });
        }
    };

    /**
     * 4. SPPEX.Tree (Port of react-treebeard)
     */
    SPPEX.Tree = class {
        constructor(selector, data) {
            this.container = document.querySelector(selector);
            this.data = data;
            this.render();
        }
        renderNode(node) {
            let html = `<div style="margin-left: 15px;">`;
            if (node.children) {
                html += `<div style="cursor:pointer; font-weight:bold;" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none'">
                            ▸ ${node.name}
                         </div>`;
                html += `<div style="display:none;">${node.children.map(c => this.renderNode(c)).join('')}</div>`;
            } else {
                html += `<div>📄 ${node.name}</div>`;
            }
            html += `</div>`;
            return html;
        }
        render() {
            if (this.container) this.container.innerHTML = this.renderNode(this.data);
        }
    };


    // ==========================================
    // 2. SPECIALIZED UI CONTROLS
    // ==========================================

    /**
     * 5. SPPEX.Dropzone (Port of react-dropzone)
     */
    SPPEX.Dropzone = {
        init(selector, onDrop) {
            const el = document.querySelector(selector);
            if (!el) return;
            el.addEventListener('dragover', (e) => { e.preventDefault(); el.style.background = '#eef'; });
            el.addEventListener('dragleave', () => { el.style.background = 'transparent'; });
            el.addEventListener('drop', (e) => {
                e.preventDefault();
                el.style.background = 'transparent';
                if (e.dataTransfer.files.length > 0) onDrop(e.dataTransfer.files);
            });
        }
    };

    /**
     * 6. SPPEX.ContextMenu (Port of react-contexify)
     */
    SPPEX.ContextMenu = {
        init(selector, menuItems) {
            document.addEventListener('contextmenu', (e) => {
                const target = e.target.closest(selector);
                if (!target) return;
                e.preventDefault();
                
                let existing = document.getElementById('sppex-context-menu');
                if (existing) existing.remove();
                
                const menu = document.createElement('div');
                menu.id = 'sppex-context-menu';
                menu.style.position = 'absolute';
                menu.style.top = e.pageY + 'px';
                menu.style.left = e.pageX + 'px';
                menu.style.background = '#fff';
                menu.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
                menu.style.zIndex = '9999';
                menu.style.borderRadius = '4px';
                menu.style.padding = '5px 0';

                menuItems.forEach(item => {
                    const btn = document.createElement('div');
                    btn.textContent = item.label;
                    btn.style.padding = '8px 15px';
                    btn.style.cursor = 'pointer';
                    btn.onmouseover = () => btn.style.background = '#eee';
                    btn.onmouseout = () => btn.style.background = 'transparent';
                    btn.onclick = () => { item.onClick(target); menu.remove(); };
                    menu.appendChild(btn);
                });
                
                document.body.appendChild(menu);
                const close = () => { menu.remove(); document.removeEventListener('click', close); };
                setTimeout(() => document.addEventListener('click', close), 10);
            });
        }
    };

    /**
     * 7. SPPEX.ColorPicker (Port of react-color)
     */
    SPPEX.ColorPicker = {
        init(selector) {
            document.querySelectorAll(selector).forEach(el => {
                if(el.type !== 'color') el.type = 'color';
                el.style.border = 'none';
                el.style.width = '40px';
                el.style.height = '40px';
                el.style.padding = '0';
                el.style.cursor = 'pointer';
            });
        }
    };

    /**
     * 8. SPPEX.RangeSlider (Port of rc-slider)
     */
    SPPEX.RangeSlider = {
        init(selector) {
            document.querySelectorAll(selector).forEach(el => {
                if(el.type !== 'range') el.type = 'range';
                const label = document.createElement('span');
                label.textContent = el.value;
                label.style.marginLeft = '10px';
                el.parentNode.insertBefore(label, el.nextSibling);
                el.addEventListener('input', () => label.textContent = el.value);
            });
        }
    };

    /**
     * 9. SPPEX.Rating (Port of react-rating)
     */
    SPPEX.Rating = class {
        constructor(selector, max = 5, onSelect) {
            this.container = document.querySelector(selector);
            this.max = max;
            this.onSelect = onSelect;
            this.current = 0;
            this.render();
        }
        render() {
            if (!this.container) return;
            this.container.innerHTML = '';
            this.container.style.display = 'inline-flex';
            this.container.style.cursor = 'pointer';
            this.container.style.fontSize = '24px';
            this.container.style.color = '#ccc';

            for (let i = 1; i <= this.max; i++) {
                const star = document.createElement('div');
                star.innerHTML = '★';
                star.style.color = i <= this.current ? '#fbbf24' : '#ccc';
                star.onmouseover = () => {
                    Array.from(this.container.children).forEach((s, idx) => s.style.color = idx < i ? '#fbbf24' : '#ccc');
                };
                star.onmouseout = () => this.render();
                star.onclick = () => { this.current = i; this.render(); if (this.onSelect) this.onSelect(i); };
                this.container.appendChild(star);
            }
        }
    };

    // ==========================================
    // 3. PRESENTATION & CONTENT
    // ==========================================

    /**
     * 10. SPPEX.Skeleton (Port of react-loading-skeleton)
     */
    SPPEX.Skeleton = {
        render(count = 1) {
            if (!document.getElementById('sppex-skeleton-css')) {
                const style = document.createElement('style');
                style.id = 'sppex-skeleton-css';
                style.innerHTML = `
                    @keyframes sppex-shimmer { 0% { background-position: -200px 0; } 100% { background-position: calc(200px + 100%) 0; } }
                    .sppex-skeleton { background: #eee; background-image: linear-gradient(90deg, #eee, #f5f5f5, #eee); background-size: 200px 100%; background-repeat: no-repeat; animation: sppex-shimmer 1.2s ease-in-out infinite; border-radius: 4px; margin-bottom: 8px; height: 20px; width: 100%; }
                `;
                document.head.appendChild(style);
            }
            return Array(count).fill('<div class="sppex-skeleton"></div>').join('');
        }
    };

    /**
     * 11. SPPEX.Accordion (Port of react-accessible-accordion)
     */
    SPPEX.Accordion = {
        init(selector) {
            const container = document.querySelector(selector);
            if (!container) return;
            const items = container.querySelectorAll('.accordion-item');
            items.forEach(item => {
                const header = item.querySelector('.accordion-header');
                const body = item.querySelector('.accordion-body');
                header.style.cursor = 'pointer';
                header.onclick = () => {
                    const isOpen = body.style.display === 'block';
                    items.forEach(i => i.querySelector('.accordion-body').style.display = 'none');
                    body.style.display = isOpen ? 'none' : 'block';
                };
            });
        }
    };

    /**
     * 12. SPPEX.Timeline (Port of react-vertical-timeline)
     */
    SPPEX.Timeline = class {
        constructor(selector, events) {
            this.container = document.querySelector(selector);
            this.events = events;
            this.render();
        }
        render() {
            if (!this.container) return;
            this.container.style.borderLeft = '2px solid #6366f1';
            this.container.style.paddingLeft = '20px';
            this.container.style.marginLeft = '10px';
            
            let html = '';
            this.events.forEach(ev => {
                html += `
                    <div style="position: relative; margin-bottom: 20px;">
                        <div style="position: absolute; left: -27px; top: 0; width: 12px; height: 12px; border-radius: 50%; background: #6366f1;"></div>
                        <div style="font-weight: bold;">${ev.date}</div>
                        <div>${ev.content}</div>
                    </div>
                `;
            });
            this.container.innerHTML = html;
        }
    };

    /**
     * 13. SPPEX.Highlight (Port of react-syntax-highlighter)
     */
    SPPEX.Highlight = {
        json(str) {
            return str.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, match => {
                let color = 'darkorange'; // number
                if (/^"/.test(match)) {
                    if (/:$/.test(match)) color = 'red'; // key
                    else color = 'green'; // string
                } else if (/true|false/.test(match)) color = 'blue'; // boolean
                else if (/null/.test(match)) color = 'magenta'; // null
                return `<span style="color: ${color}">${match}</span>`;
            });
        }
    };

    /**
     * 14. SPPEX.AvatarGroup (Port of mui/avatar-group)
     */
    SPPEX.AvatarGroup = {
        render(images, max = 3) {
            let html = `<div style="display:flex;">`;
            images.slice(0, max).forEach((img, i) => {
                html += `<img src="${img}" style="width:40px; height:40px; border-radius:50%; border:2px solid #fff; margin-left:${i===0?0:'-15px'}; z-index:${max-i}; background:#ccc;" />`;
            });
            if (images.length > max) {
                html += `<div style="width:40px; height:40px; border-radius:50%; border:2px solid #fff; margin-left:-15px; z-index:0; background:#f0f0f0; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:bold;">+${images.length - max}</div>`;
            }
            html += `</div>`;
            return html;
        }
    };

    /**
     * 15. SPPEX.ProgressBar (Port of react-circular-progressbar)
     */
    SPPEX.ProgressBar = {
        linear(selector, percentage) {
            const el = document.querySelector(selector);
            if (!el) return;
            el.innerHTML = `
                <div style="width:100%; background:#e0e0e0; border-radius:4px; height:8px; overflow:hidden;">
                    <div style="width:${percentage}%; background:#10b981; height:100%; transition: width 0.3s ease;"></div>
                </div>
            `;
        }
    };

    /**
     * 16. SPPEX.Badge (Port of mui/badge)
     */
    SPPEX.Badge = {
        wrap(htmlContent, badgeText) {
            return `
                <div style="position:relative; display:inline-block;">
                    ${htmlContent}
                    <span style="position:absolute; top:-5px; right:-10px; background:red; color:white; border-radius:10px; padding:2px 6px; font-size:10px; font-weight:bold;">${badgeText}</span>
                </div>
            `;
        }
    };


    // ==========================================
    // 4. NAVIGATION & UTILITIES
    // ==========================================

    /**
     * 17. SPPEX.Pagination (Port of react-paginate)
     */
    SPPEX.Pagination = class {
        constructor(selector, totalPages, onPageChange) {
            this.container = document.querySelector(selector);
            this.totalPages = totalPages;
            this.current = 1;
            this.onPageChange = onPageChange;
            this.render();
        }
        render() {
            if (!this.container) return;
            let html = `<div style="display:flex; gap:5px;">`;
            for (let i = 1; i <= this.totalPages; i++) {
                html += `<button style="padding:5px 10px; border:1px solid #ccc; background:${this.current === i ? '#6366f1' : '#fff'}; color:${this.current === i ? '#fff' : '#000'}; cursor:pointer;" data-page="${i}">${i}</button>`;
            }
            html += `</div>`;
            this.container.innerHTML = html;
            this.container.querySelectorAll('button').forEach(btn => {
                btn.onclick = () => {
                    this.current = parseInt(btn.getAttribute('data-page'));
                    this.render();
                    if (this.onPageChange) this.onPageChange(this.current);
                };
            });
        }
    };

    /**
     * 18. SPPEX.Breadcrumbs (Port of react-breadcrumbs)
     */
    SPPEX.Breadcrumbs = {
        render(paths) {
            return `<div style="font-family:sans-serif; font-size:14px;">` + 
                paths.map((p, i) => i === paths.length - 1 
                    ? `<span style="color:#666;">${p.name}</span>` 
                    : `<a href="${p.url}" style="color:#6366f1; text-decoration:none;">${p.name}</a>`
                ).join(' <span style="margin:0 5px; color:#ccc;">/</span> ') + 
            `</div>`;
        }
    };

    /**
     * 19. SPPEX.CopyToClipboard (Port of react-copy-to-clipboard)
     */
    SPPEX.CopyToClipboard = {
        attach(selector, textToCopy) {
            const el = document.querySelector(selector);
            if (!el) return;
            el.onclick = () => {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    const orig = el.textContent;
                    el.textContent = 'Copied!';
                    setTimeout(() => el.textContent = orig, 1500);
                });
            };
        }
    };

    /**
     * 20. SPPEX.WebSocket (Port of react-use-websocket)
     */
    SPPEX.WebSocket = class {
        constructor(url, onMessage) {
            this.url = url;
            this.onMessage = onMessage;
            this.socket = null;
            this.reconnectDelay = 1000;
            this.connect();
        }
        connect() {
            this.socket = new WebSocket(this.url);
            this.socket.onmessage = (e) => {
                if (this.onMessage) this.onMessage(JSON.parse(e.data));
            };
            this.socket.onclose = () => {
                console.warn(`WebSocket closed. Reconnecting in ${this.reconnectDelay}ms...`);
                setTimeout(() => {
                    this.reconnectDelay *= 2; // Exponential backoff
                    this.connect();
                }, this.reconnectDelay);
            };
            this.socket.onopen = () => { this.reconnectDelay = 1000; };
        }
        send(data) {
            if (this.socket && this.socket.readyState === WebSocket.OPEN) {
                this.socket.send(JSON.stringify(data));
            }
        }
    };

    global.SPPEX = SPPEX;

})(window);
