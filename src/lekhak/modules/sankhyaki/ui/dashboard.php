<?php
namespace Lekhak\Modules\Sankhyaki\UI;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sankhyaki Analytics Dashboard</title>
    <!-- We load the locally downloaded Chart.js -->
    <script src="/lekhak/modules/sankhyaki/assets/js/chart.min.js"></script>
    <style>
        /* Premium Vanilla CSS - Dark Mode Glassmorphism Theme */
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap');
        
        :root {
            /* Default to dark */
            --bg-color: #0f172a;
            --surface-color: rgba(30, 41, 59, 0.7);
            --surface-border: rgba(255, 255, 255, 0.1);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-gradient: linear-gradient(135deg, #3b82f6, #8b5cf6);
            --card-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
            --chart-text: #94a3b8;
            --chart-tooltip-bg: rgba(15, 23, 42, 0.9);
            
            --font-family: 'Outfit', sans-serif;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        :root[data-theme="light"] {
            --bg-color: #f8fafc;
            --surface-color: rgba(255, 255, 255, 0.8);
            --surface-border: rgba(0, 0, 0, 0.1);
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --accent-gradient: linear-gradient(135deg, #2563eb, #7c3aed);
            --card-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.1);
            --chart-text: #475569;
            --chart-tooltip-bg: rgba(255, 255, 255, 0.9);
        }

        :root[data-theme="saffron"] {
            --bg-color: #fffaf0;
            --surface-color: rgba(255, 237, 213, 0.8);
            --surface-border: rgba(249, 115, 22, 0.2);
            --text-primary: #7c2d12;
            --text-secondary: #9a3412;
            --accent-gradient: linear-gradient(135deg, #f97316, #ea580c);
            --card-shadow: 0 10px 30px -10px rgba(249, 115, 22, 0.15);
            --chart-text: #9a3412;
            --chart-tooltip-bg: rgba(255, 237, 213, 0.9);
        }

        body {
            margin: 0;
            padding: 0;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(59, 130, 246, 0.15), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(139, 92, 246, 0.15), transparent 25%);
            background-attachment: fixed;
            color: var(--text-primary);
            font-family: var(--font-family);
            min-height: 100vh;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p.subtitle {
            color: var(--text-secondary);
            margin: 0.5rem 0 0 0;
            font-size: 1.1rem;
        }

        .btn-refresh {
            background: var(--surface-color);
            border: 1px solid var(--surface-border);
            color: var(--text-primary);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-family: var(--font-family);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }

        .theme-select {
            background: var(--surface-color);
            border: 1px solid var(--surface-border);
            color: var(--text-primary);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-family: var(--font-family);
            font-size: 1rem;
            cursor: pointer;
            backdrop-filter: blur(10px);
            margin-right: 1rem;
            outline: none;
        }

        .header-actions {
            display: flex;
            align-items: center;
        }
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .grid-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .glass-card {
            background: var(--surface-color);
            border: 1px solid var(--surface-border);
            border-radius: 16px;
            padding: 1.5rem;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .glass-card:hover {
            transform: translateY(-5px);
            border-color: rgba(255, 255, 255, 0.2);
            box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.6);
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 4px;
            background: var(--accent-gradient);
            opacity: 0;
            transition: var(--transition);
        }

        .glass-card:hover::before {
            opacity: 1;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }

        .grid-charts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            color: var(--text-secondary);
            font-weight: 600;
            padding: 1rem;
            border-bottom: 1px solid var(--surface-border);
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
        }

        tr:last-child td { border-bottom: none; }
        
        tr:hover td {
            background: rgba(255, 255, 255, 0.03);
        }

        /* Micro animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animated {
            animation: fadeIn 0.6s ease-out forwards;
        }
        
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        
        /* Chart.js overrides to fit dark theme */
        canvas {
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header class="animated">
            <div>
                <h1>Sankhyaki</h1>
                <p class="subtitle">Real-time Advanced Analytics Dashboard</p>
            </div>
            <div class="header-actions">
                <select class="theme-select" id="theme-selector" onchange="setTheme(this.value)">
                    <option value="dark">Dark Mode</option>
                    <option value="light">Day Mode</option>
                    <option value="saffron">Saffron Mode</option>
                </select>
                <button class="btn-refresh" onclick="fetchStats()">Refresh Data</button>
            </div>
        </header>

        <div class="grid-overview animated delay-1">
            <div class="glass-card">
                <div class="stat-label">Pageviews</div>
                <div class="stat-value" id="val-pageviews">-</div>
            </div>
            <div class="glass-card">
                <div class="stat-label">Unique Visitors</div>
                <div class="stat-value" id="val-visitors">-</div>
            </div>
            <div class="glass-card">
                <div class="stat-label">Bounce Rate</div>
                <div class="stat-value" id="val-bounce">-</div>
            </div>
            <div class="glass-card">
                <div class="stat-label">Avg Time on Page</div>
                <div class="stat-value" id="val-time">-</div>
            </div>
        </div>

        <div class="grid-charts animated delay-2">
            <div class="glass-card">
                <h3 class="stat-label">Device Breakdown</h3>
                <div class="chart-container">
                    <canvas id="deviceChart"></canvas>
                </div>
            </div>
            <div class="glass-card">
                <h3 class="stat-label">Top Geographies</h3>
                <div class="chart-container">
                    <canvas id="geoChart"></canvas>
                </div>
            </div>
        </div>

        <div class="grid-charts animated delay-3">
            <div class="glass-card" style="grid-column: 1 / -1;">
                <h3 class="stat-label">Top Pages</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>URL Path</th>
                                <th>Views</th>
                            </tr>
                        </thead>
                        <tbody id="table-top-pages">
                            <tr><td colspan="2" style="text-align: center;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global chart instances for destruction on reload
        let deviceChartInst = null;
        let geoChartInst = null;

        let lastStatsData = null;

        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('sankhyaki-theme', theme);
            document.getElementById('theme-selector').value = theme;
            
            // Re-render charts with new theme colors
            if (lastStatsData) {
                renderDashboard(lastStatsData);
            }
        }

        // Apply saved theme on load
        const savedTheme = localStorage.getItem('sankhyaki-theme') || 'dark';
        setTheme(savedTheme);

        function updateChartDefaults() {
            const rootStyle = getComputedStyle(document.documentElement);
            Chart.defaults.color = rootStyle.getPropertyValue('--chart-text').trim();
            Chart.defaults.font.family = "'Outfit', sans-serif";
            Chart.defaults.plugins.tooltip.backgroundColor = rootStyle.getPropertyValue('--chart-tooltip-bg').trim();
            Chart.defaults.plugins.tooltip.titleColor = rootStyle.getPropertyValue('--text-primary').trim();
            Chart.defaults.plugins.tooltip.bodyColor = rootStyle.getPropertyValue('--text-primary').trim();
            Chart.defaults.plugins.tooltip.padding = 12;
            Chart.defaults.plugins.tooltip.cornerRadius = 8;
            Chart.defaults.plugins.tooltip.borderColor = rootStyle.getPropertyValue('--surface-border').trim();
            Chart.defaults.plugins.tooltip.borderWidth = 1;
        }

        async function fetchStats() {
            try {
                const apiUrl = window.location.pathname.replace(/\/admin\/sankhyaki\/?$/, '/api/sankhyaki/stats');
                const response = await fetch(apiUrl);
                const json = await response.json();
                if (json.success) {
                    renderDashboard(json.data);
                } else {
                    alert('Error loading stats: ' + json.message);
                }
            } catch (error) {
                console.error("Error fetching stats:", error);
            }
        }

        function renderDashboard(data) {
            lastStatsData = data;
            updateChartDefaults();

            // Update Overview Cards
            document.getElementById('val-pageviews').innerText = data.overview.pageviews.toLocaleString();
            document.getElementById('val-visitors').innerText = data.overview.unique_visitors.toLocaleString();
            document.getElementById('val-bounce').innerText = data.overview.bounce_rate + '%';
            document.getElementById('val-time').innerText = formatTime(data.overview.avg_time_on_page);

            // Update Top Pages Table
            const tbody = document.getElementById('table-top-pages');
            tbody.innerHTML = '';
            if (data.top_pages && data.top_pages.length > 0) {
                data.top_pages.forEach(page => {
                    tbody.innerHTML += `<tr>
                        <td>${escapeHtml(page.url)}</td>
                        <td>${page.views.toLocaleString()}</td>
                    </tr>`;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="2">No data available.</td></tr>';
            }

            // Render Device Doughnut Chart
            if (deviceChartInst) deviceChartInst.destroy();
            const ctxDevice = document.getElementById('deviceChart').getContext('2d');
            const deviceLabels = data.devices ? data.devices.map(d => d.device_type) : [];
            const deviceData = data.devices ? data.devices.map(d => d.count) : [];
            
            deviceChartInst = new Chart(ctxDevice, {
                type: 'doughnut',
                data: {
                    labels: deviceLabels.length ? deviceLabels : ['No Data'],
                    datasets: [{
                        data: deviceData.length ? deviceData : [1],
                        backgroundColor: [
                            '#3b82f6', // Blue
                            '#8b5cf6', // Purple
                            '#06b6d4', // Cyan
                            '#10b981'  // Emerald
                        ],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: {
                        legend: { position: 'right' }
                    }
                }
            });

            // Render Geo Bar Chart
            if (geoChartInst) geoChartInst.destroy();
            const ctxGeo = document.getElementById('geoChart').getContext('2d');
            const geoLabels = data.countries ? data.countries.map(c => c.country) : [];
            const geoData = data.countries ? data.countries.map(c => c.count) : [];

            // Create a gradient for the bar chart based on theme
            const currentTheme = document.documentElement.getAttribute('data-theme');
            let color1 = '#8b5cf6', color2 = '#3b82f6';
            if (currentTheme === 'saffron') {
                color1 = '#ea580c'; color2 = '#f97316';
            } else if (currentTheme === 'light') {
                color1 = '#7c3aed'; color2 = '#2563eb';
            }
            
            const gradient = ctxGeo.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, color1);
            gradient.addColorStop(1, color2);

            geoChartInst = new Chart(ctxGeo, {
                type: 'bar',
                data: {
                    labels: geoLabels.length ? geoLabels : ['No Data'],
                    datasets: [{
                        label: 'Visitors',
                        data: geoData.length ? geoData : [0],
                        backgroundColor: gradient,
                        borderRadius: 6,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            grid: { color: 'rgba(255,255,255,0.05)', drawBorder: false },
                            ticks: { precision: 0 }
                        },
                        x: {
                            grid: { display: false, drawBorder: false }
                        }
                    }
                }
            });
        }

        function formatTime(seconds) {
            if (!seconds) return '0s';
            const m = Math.floor(seconds / 60);
            const s = Math.round(seconds % 60);
            return m > 0 ? `${m}m ${s}s` : `${s}s`;
        }

        function escapeHtml(unsafe) {
            return (unsafe || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        // Init
        document.addEventListener('DOMContentLoaded', fetchStats);
    </script>
</body>
</html>
