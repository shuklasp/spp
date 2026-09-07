import { BaseComponent, html, Fragment } from '../../sppux/js/spp-ux.js';

export default class SPPReportPivot extends BaseComponent {
    constructor(admin, container, props) {
        super(admin, container, props);
        this.state = {
            data: this.props.data || [],
            columns: Object.keys((this.props.data || [])[0] || {}),
            rowField: '',
            colField: '',
            valField: '',
            aggregate: 'SUM',
            showDrillDownModal: false,
            drillDownData: null,
            drillDownTitle: ''
        };
    }

    // Very simple cross-tab generator
    generatePivot() {
        if (!this.state.rowField || !this.state.colField || !this.state.valField) {
            return { rows: [], cols: [], matrix: {} };
        }

        const rows = new Set();
        const cols = new Set();
        const matrix = {};

        this.state.data.forEach(row => {
            const rVal = row[this.state.rowField] || '(Blank)';
            const cVal = row[this.state.colField] || '(Blank)';
            const vVal = parseFloat(row[this.state.valField]) || 0;

            rows.add(rVal);
            cols.add(cVal);

            if (!matrix[rVal]) matrix[rVal] = {};
            if (!matrix[rVal][cVal]) {
                matrix[rVal][cVal] = { sum: 0, count: 0, min: vVal, max: vVal };
            }

            matrix[rVal][cVal].sum += vVal;
            matrix[rVal][cVal].count += 1;
            matrix[rVal][cVal].min = Math.min(matrix[rVal][cVal].min, vVal);
            matrix[rVal][cVal].max = Math.max(matrix[rVal][cVal].max, vVal);
        });

        return {
            rows: Array.from(rows).sort(),
            cols: Array.from(cols).sort(),
            matrix: matrix
        };
    }

    getAggValue(cell) {
        if (!cell) return '';
        switch (this.state.aggregate) {
            case 'SUM': return cell.sum;
            case 'COUNT': return cell.count;
            case 'AVG': return (cell.sum / cell.count).toFixed(2);
            case 'MIN': return cell.min;
            case 'MAX': return cell.max;
            default: return cell.sum;
        }
    }

    showDrillDown(rVal, cVal) {
        // Filter the raw data where row matches and col matches
        const filtered = this.state.data.filter(row => {
            const rowMatch = (row[this.state.rowField] || '(Blank)') == rVal;
            const colMatch = (row[this.state.colField] || '(Blank)') == cVal;
            return rowMatch && colMatch;
        });
        
        this.state.drillDownData = filtered;
        this.state.drillDownTitle = `${this.state.rowField}: ${rVal} | ${this.state.colField}: ${cVal}`;
        this.state.showDrillDownModal = true;
        this.update();
    }

    render() {
        if (this.state.data.length === 0) return html`<div>No data for pivot</div>`;

        const pivotData = this.generatePivot();

        return html`
            <div class="spp-pivot-builder" style="background: var(--sppux-card-bg); border-radius: var(--sppux-radius-lg); border: 1px solid var(--sppux-glass-border); padding: 15px;">
                <div style="display:flex; gap:10px; margin-bottom:20px; align-items:center; background:var(--sppux-glass-bg); padding:10px; border-radius:8px;">
                    <strong>Pivot Settings:</strong>
                    
                    <select class="spp-input" @change=${e => { this.state.rowField = e.target.value; this.update(); }}>
                        <option value="">-- Row Field --</option>
                        ${this.state.columns.map(c => html`<option value="${c}" ${this.state.rowField === c ? 'selected' : ''}>${c}</option>`)}
                    </select>

                    <span>X</span>

                    <select class="spp-input" @change=${e => { this.state.colField = e.target.value; this.update(); }}>
                        <option value="">-- Column Field --</option>
                        ${this.state.columns.map(c => html`<option value="${c}" ${this.state.colField === c ? 'selected' : ''}>${c}</option>`)}
                    </select>

                    <span style="margin-left:15px;">Value:</span>

                    <select class="spp-input" @change=${e => { this.state.valField = e.target.value; this.update(); }}>
                        <option value="">-- Value Field --</option>
                        ${this.state.columns.map(c => html`<option value="${c}" ${this.state.valField === c ? 'selected' : ''}>${c}</option>`)}
                    </select>

                    <select class="spp-input" @change=${e => { this.state.aggregate = e.target.value; this.update(); }}>
                        ${['SUM', 'COUNT', 'AVG', 'MIN', 'MAX'].map(a => html`<option value="${a}" ${this.state.aggregate === a ? 'selected' : ''}>${a}</option>`)}
                    </select>
                </div>

                <div style="overflow-x:auto;">
                    ${pivotData.cols.length > 0 ? html`
                        <table style="width:100%; border-collapse:collapse; text-align:right;">
                            <thead>
                                <tr>
                                    <th style="padding:10px; border:1px solid var(--sppux-glass-border); background:var(--sppux-glass-bg); text-align:left;">
                                        ${this.state.rowField} \\ ${this.state.colField}
                                    </th>
                                    ${pivotData.cols.map(c => html`<th style="padding:10px; border:1px solid var(--sppux-glass-border); background:var(--sppux-glass-bg);">${c}</th>`)}
                                </tr>
                            </thead>
                            <tbody>
                                ${pivotData.rows.map(r => html`
                                    <tr>
                                        <td style="padding:10px; border:1px solid var(--sppux-glass-border); background:var(--sppux-glass-bg); text-align:left; font-weight:bold;">${r}</td>
                                        ${pivotData.cols.map(c => {
                                            const cell = pivotData.matrix[r][c];
                                            return html`<td style="padding:10px; border:1px solid var(--sppux-glass-border); cursor:pointer; color:var(--sppux-primary);" @click=${() => this.showDrillDown(r, c)}>${this.getAggValue(cell)}</td>`;
                                        })}
                                    </tr>
                                `)}
                            </tbody>
                        </table>
                    ` : html`<div style="text-align:center; padding:20px; color:var(--sppux-text-dim);">Select Row, Column, and Value fields to generate Pivot Table.</div>`}
                </div>

                ${this.state.showDrillDownModal ? html`
                    <div class="spp-modal-overlay" style="position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); z-index:9999; display:flex; align-items:center; justify-content:center;">
                        <div class="spp-modal-content" style="background:var(--sppux-bg, white); padding:20px; border-radius:var(--sppux-radius-lg, 8px); width:800px; max-width:90vw; max-height:90vh; overflow-y:auto; box-shadow:0 10px 40px rgba(0,0,0,0.2);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                <h3 style="margin:0;">Drill-Down: ${this.state.drillDownTitle}</h3>
                                <button class="btn danger icon" @click=${() => { this.state.showDrillDownModal = false; this.update(); }}>×</button>
                            </div>
                            
                            ${(!this.state.drillDownData || this.state.drillDownData.length === 0) ? html`<p>No raw data found.</p>` : html`
                                <div style="overflow-x:auto;">
                                    <table style="width:100%; border-collapse: collapse; text-align: left;">
                                        <thead>
                                            <tr style="background:var(--sppux-glass-bg, #eee);">
                                                ${Object.keys(this.state.drillDownData[0]).map(k => html`<th style="padding:8px; border:1px solid var(--sppux-glass-border, #ddd);">${k}</th>`)}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${this.state.drillDownData.map(row => html`
                                                <tr>
                                                    ${Object.values(row).map(val => html`<td style="padding:6px 8px; border:1px solid var(--sppux-glass-border, #ddd);">${val}</td>`)}
                                                </tr>
                                            `)}
                                        </tbody>
                                    </table>
                                </div>
                            `}
                        </div>
                    </div>
                ` : Fragment}
            </div>
        `;
    }
}
