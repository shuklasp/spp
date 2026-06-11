import { BaseComponent, html, Fragment } from '../../sppux/js/spp-ux.js';

export default class SPPReportDashboard extends BaseComponent {
    constructor(admin, container, props) {
        super(admin, container, props);
        this.state = {
            loading: true,
            error: null,
            savedReports: [],
            selectedReport: this.props.report || '',
            chartType: this.props.type || 'bar',
            reportData: null,
            chartInstance: null,
            drillDownData: null,
            showDrillDownModal: false
        };
        this.props.apiEndpoint = this.props.apiEndpoint || '/spp/admin/api.php?action=report_api&modname=sppreport';
    }

    async init() {
        if (this.state.chartType === 'map' && !window.L) {
            await this.loadLeaflet();
        } else if (this.state.chartType !== 'map' && !window.Chart) {
            await this.loadChartJs();
        }
        
        if (this.state.selectedReport) {
            await this.loadAndRenderReport(this.state.selectedReport);
        } else {
            await this.fetchSavedReports();
        }
        
        this.state.loading = false;
        this.update();
    }

    loadChartJs() {
        return new Promise((resolve, reject) => {
            if (window.Chart) return resolve();
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    loadLeaflet() {
        return new Promise((resolve, reject) => {
            if (window.L) return resolve();
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(link);

            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    async fetchSavedReports() {
        try {
            const res = await fetch(`${this.props.apiEndpoint}&report_action=list`);
            const data = await res.json();
            if (data.status === 'success') {
                this.state.savedReports = data.reports;
            }
        } catch (e) {
            this.state.error = 'Failed to load report list.';
        }
    }

    async loadAndRenderReport(name) {
        if (!name) return;
        this.state.loading = true;
        this.state.selectedReport = name;
        this.update();

        try {
            // 1. Load Config
            const res1 = await fetch(`${this.props.apiEndpoint}&report_action=load&name=${encodeURIComponent(name)}`);
            const configData = await res1.json();
            if (configData.status !== 'success') throw new Error(configData.message);

            // 2. Run Preview
            const res2 = await fetch(`${this.props.apiEndpoint}&report_action=preview`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(configData.config)
            });
            const runData = await res2.json();
            if (runData.status !== 'success') throw new Error(runData.message);

            this.state.reportData = runData.data;
            this.state.error = null;
        } catch (err) {
            this.state.error = err.message;
            this.state.reportData = null;
        } finally {
            this.state.loading = false;
            this.update();
            setTimeout(() => this.drawChart(), 100); // Wait for canvas to render
        }
    }

    async drawChart() {
        if (!this.state.reportData || this.state.reportData.length === 0) return;
        
        const container = this.container.querySelector('.chart-container');
        if (!container) return;
        container.innerHTML = ''; // clear

        if (this.state.chartType === 'map') {
            if (!window.L) await this.loadLeaflet();
            const mapDiv = document.createElement('div');
            mapDiv.style.height = '100%';
            mapDiv.style.width = '100%';
            container.appendChild(mapDiv);

            const map = L.map(mapDiv).setView([0, 0], 2);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap'
            }).addTo(map);

            const bounds = [];
            this.state.reportData.forEach(row => {
                const lat = parseFloat(row.lat || row.latitude);
                const lng = parseFloat(row.lng || row.longitude || row.lon);
                if (!isNaN(lat) && !isNaN(lng)) {
                    L.marker([lat, lng]).addTo(map)
                        .bindPopup(Object.entries(row).map(([k,v]) => `<b>${k}</b>: ${v}`).join('<br>'));
                    bounds.push([lat, lng]);
                }
            });
            if (bounds.length > 0) map.fitBounds(bounds);
            return;
        }

        if (!window.Chart) await this.loadChartJs();
        const canvas = document.createElement('canvas');
        container.appendChild(canvas);

        // Auto-detect labels and dataset values
        // Usually, the first string column is the label, and the first numeric column is the value
        const keys = Object.keys(this.state.reportData[0]);
        let labelKey = keys[0];
        let valueKey = keys[1] || keys[0];

        // Try to be smart
        for (let k of keys) {
            if (typeof this.state.reportData[0][k] === 'number' || !isNaN(parseFloat(this.state.reportData[0][k]))) {
                valueKey = k;
                break;
            }
        }

        const labels = this.state.reportData.map(r => r[labelKey]);
        const values = this.state.reportData.map(r => parseFloat(r[valueKey]) || 0);

        // Generate some pretty colors
        const bgColors = values.map((_, i) => `hsl(${(i * 360 / values.length) % 360}, 70%, 50%)`);

        this.state.chartInstance = new window.Chart(canvas, {
            type: this.state.chartType,
            data: {
                labels: labels,
                datasets: [{
                    label: valueKey,
                    data: values,
                    backgroundColor: this.state.chartType === 'line' ? 'rgba(54, 162, 235, 0.2)' : bgColors,
                    borderColor: this.state.chartType === 'line' ? 'rgba(54, 162, 235, 1)' : bgColors,
                    borderWidth: 1,
                    fill: this.state.chartType === 'line'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: this.state.selectedReport
                    }
                },
                onClick: async (event, elements) => {
                    if (elements.length > 0) {
                        const idx = elements[0].index;
                        const labelValue = labels[idx];
                        await this.fetchDrillDown(labelKey, labelValue);
                    }
                }
            }
        });
    }

    async fetchDrillDown(filterField, filterValue) {
        this.state.loading = true;
        this.update();
        try {
            // Load original config
            const res1 = await fetch(`${this.props.apiEndpoint}&report_action=load&name=${encodeURIComponent(this.state.selectedReport)}`);
            const configData = await res1.json();
            if (configData.status !== 'success') throw new Error(configData.message);

            const cfg = configData.config;
            // Inject new filter condition
            if (!cfg.filters) cfg.filters = { logic: 'AND', conditions: [] };
            cfg.filters.conditions.push({ field: filterField, operator: '=', value: filterValue });
            
            // Strip aggregates so we see raw rows (naïve approach for drill-down)
            if (cfg.columns) {
                cfg.columns.forEach(c => c.aggregate = '');
            }
            cfg.group_by = [];
            cfg.limit = 500; // Cap at 500 rows for modal

            // Run Preview
            const res2 = await fetch(`${this.props.apiEndpoint}&report_action=preview`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(cfg)
            });
            const runData = await res2.json();
            if (runData.status !== 'success') throw new Error(runData.message);

            this.state.drillDownData = runData.data;
            this.state.showDrillDownModal = true;
        } catch (e) {
            alert("Drill-down failed: " + e.message);
        } finally {
            this.state.loading = false;
            this.update();
        }
    }

    render() {
        if (this.state.loading) return html`<div class="sppux-spinner"></div>`;

        return html`
            <div class="spp-report-dashboard" style="background: var(--sppux-panel, white); padding: 20px; border-radius: var(--sppux-radius-lg, 8px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: flex; flex-direction: column; height: 100%; min-height: 400px;">
                
                ${!this.props.report ? html`
                    <div style="display: flex; gap: 15px; margin-bottom: 20px; align-items: center; background: var(--sppux-glass-bg, #f9f9f9); padding: 10px; border-radius: 8px;">
                        <select class="spp-input" style="flex: 1;" @change=${(e) => this.loadAndRenderReport(e.target.value)}>
                            <option value="">-- Select Report to Chart --</option>
                            ${this.state.savedReports.map(r => html`
                                <option value="${r}" ${this.state.selectedReport === r ? 'selected' : ''}>${r}</option>
                            `)}
                        </select>
                        <select class="spp-input" @change=${(e) => { this.state.chartType = e.target.value; this.drawChart(); }}>
                            ${['bar', 'pie', 'doughnut', 'line', 'map'].map(t => html`
                                <option value="${t}" ${this.state.chartType === t ? 'selected' : ''}>${t.charAt(0).toUpperCase() + t.slice(1)}</option>
                            `)}
                        </select>
                    </div>
                ` : Fragment}

                ${this.state.error ? html`<div style="color: red; padding: 10px; background: #fee; border-radius: 4px;">${this.state.error}</div>` : Fragment}

                <div class="chart-container" style="flex: 1; position: relative; min-height: 300px; z-index: 1;">
                    ${(this.state.selectedReport && !this.state.loading && (!this.state.reportData || this.state.reportData.length === 0)) ? html`
                        <div style="display:flex; align-items:center; justify-content:center; height:100%; color:#888;">No chart data available for this report.</div>
                    ` : Fragment}
                </div>

                ${this.state.showDrillDownModal ? html`
                    <div class="spp-modal-overlay" style="position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); z-index:9999; display:flex; align-items:center; justify-content:center;">
                        <div class="spp-modal-content" style="background:var(--sppux-bg, white); padding:20px; border-radius:var(--sppux-radius-lg, 8px); width:800px; max-width:90vw; max-height:90vh; overflow-y:auto; box-shadow:0 10px 40px rgba(0,0,0,0.2);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                <h3 style="margin:0;">Drill-Down Data</h3>
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
