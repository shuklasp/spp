/**
 * SPP-UX Master Grid (v1 - Legendary High-Performance Edition)
 *
 * A virtualized, high-performance data grid component.
 * Features: Virtual scrolling, sorting, filtering, and inline editing.
 */

(function(SPPUX) {
    if (!SPPUX) {
        console.error("SPPUX Core not found. MasterGrid requires sppux.js");
        return;
    }

    class MasterGrid extends BaseComponent {
        onInit() {
            this.state = {
                data: this.props.data || [],
                columns: this.props.columns || [],
                rowHeight: this.props.rowHeight || 40,
                visibleCount: 20,
                startIndex: 0,
                scrollTop: 0,
                sortKey: null,
                sortDir: 1,
                filters: {},
                editingCell: null // { rowId, colKey }
            };

            this._updateVisibleRange = this._updateVisibleRange.bind(this);
        }

        onMount() {
            const viewport = this.container.querySelector('.sppux-grid-viewport');
            if (viewport) {
                viewport.addEventListener('scroll', this._updateVisibleRange);
                this._updateVisibleRange();
            }
        }

        _updateVisibleRange() {
            const viewport = this.container.querySelector('.sppux-grid-viewport');
            if (!viewport) return;

            const scrollTop = viewport.scrollTop;
            const startIndex = Math.floor(scrollTop / this.state.rowHeight);
            
            if (startIndex !== this.state.startIndex) {
                this.setState({ startIndex, scrollTop });
            }
        }

        handleSort(key) {
            const dir = (this.state.sortKey === key) ? -this.state.sortDir : 1;
            const sorted = [...this.state.data].sort((a, b) => {
                const valA = a[key], valB = b[key];
                return valA > valB ? dir : valA < valB ? -dir : 0;
            });
            this.setState({ data: sorted, sortKey: key, sortDir: dir });
        }

        handleFilter(key, value) {
            const newFilters = { ...this.state.filters, [key]: value };
            const filtered = (this.props.data || []).filter(item => {
                return Object.entries(newFilters).every(([k, v]) => {
                    if (!v) return true;
                    return String(item[k]).toLowerCase().includes(v.toLowerCase());
                });
            });
            this.setState({ data: filtered, filters: newFilters, startIndex: 0 });
        }

        startEdit(rowId, colKey) {
            this.setState({ editingCell: { rowId, colKey } });
        }

        saveEdit(rowId, colKey, value) {
            const newData = this.state.data.map(item => {
                if (item.id === rowId) return { ...item, [colKey]: value };
                return item;
            });
            this.setState({ data: newData, editingCell: null });
            this.props.onUpdate?.(rowId, colKey, value);
        }

        render() {
            const { data, columns, rowHeight, startIndex, visibleCount, scrollTop, sortKey, sortDir, filters, editingCell } = this.state;
            const totalHeight = data.length * rowHeight;
            const visibleData = data.slice(startIndex, startIndex + visibleCount + 5);
            const offsetY = startIndex * rowHeight;

            return html`
                <div class="sppux-grid glass-panel">
                    <div class="sppux-grid-header">
                        ${columns.map(col => html`
                            <div class="grid-col-header" style="width: ${col.width || '150px'}" @click=${() => this.handleSort(col.key)}>
                                <span>${col.label}</span>
                                ${sortKey === col.key ? (sortDir === 1 ? ' ↑' : ' ↓') : ''}
                                <input type="text" class="grid-filter" placeholder="Filter..." 
                                    value="${filters[col.key] || ''}"
                                    @click=${(e) => e.stopPropagation()}
                                    @input=${(e) => this.handleFilter(col.key, e.target.value)}>
                            </div>
                        `)}
                    </div>
                    <div class="sppux-grid-viewport" style="height: 400px; overflow-y: auto;">
                        <div class="grid-canvas" style="height: ${totalHeight}px; position: relative;">
                            <div class="grid-rows-container" style="transform: translateY(${offsetY}px)">
                                ${visibleData.map((row, idx) => html`
                                    <div class="grid-row" style="height: ${rowHeight}px; display: flex;">
                                        ${columns.map(col => {
                                            const isEditing = editingCell?.rowId === row.id && editingCell?.colKey === col.key;
                                            return html`
                                                <div class="grid-cell" style="width: ${col.width || '150px'}" 
                                                    @dblclick=${() => col.editable && this.startEdit(row.id, col.key)}>
                                                    ${isEditing ? html`
                                                        <input type="text" class="grid-edit-input" 
                                                            value="${row[col.key]}" 
                                                            @blur=${(e) => this.saveEdit(row.id, col.key, e.target.value)}
                                                            @keydown=${(e) => e.key === 'Enter' && this.saveEdit(row.id, col.key, e.target.value)}>
                                                    ` : row[col.key]}
                                                </div>
                                            `;
                                        })}
                                    </div>
                                `)}
                            </div>
                        </div>
                    </div>
                    <div class="sppux-grid-footer">
                        Total Records: ${data.length}
                    </div>
                </div>
            `;
        }
    }

    SPPUX.MasterGrid = MasterGrid;

})(window.SPPUX);
