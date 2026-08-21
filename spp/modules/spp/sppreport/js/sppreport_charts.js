/**
 * SPPReport Charts Adapter
 * A vanilla JS engine that reads visualization definitions from the report payload
 * and dynamically renders ECharts / Chart.js graphs inside the dashboard.
 */
document.addEventListener('DOMContentLoaded', () => {
    initSppCharts();
});

// Re-initialize charts after HTMX or Turbo Stream updates
document.addEventListener('htmx:afterSettle', initSppCharts);
document.addEventListener('turbo:load', initSppCharts);

function initSppCharts() {
    const chartContainers = document.querySelectorAll('.spp-report-chart-container:not(.initialized)');
    
    chartContainers.forEach(container => {
        try {
            const rawData = container.getAttribute('data-chart-payload');
            const rawConfig = container.getAttribute('data-chart-config');
            
            if (!rawData || !rawConfig) return;
            
            const data = JSON.parse(rawData);
            const visualizations = JSON.parse(rawConfig);
            
            if (!visualizations || visualizations.length === 0) return;
            
            // Assume ECharts is globally available. If not, fallback or load dynamically.
            if (typeof echarts !== 'undefined') {
                visualizations.forEach((viz, index) => {
                    const chartDiv = document.createElement('div');
                    chartDiv.style.width = '100%';
                    chartDiv.style.height = viz.height || '400px';
                    chartDiv.style.marginBottom = '20px';
                    container.appendChild(chartDiv);
                    
                    const myChart = echarts.init(chartDiv);
                    
                    // Extract axes data
                    const xAxisData = data.map(row => row[viz.x]);
                    const seriesData = data.map(row => row[viz.y]);
                    
                    let option = {
                        title: { text: viz.title || 'Chart' },
                        tooltip: { trigger: 'axis' },
                        xAxis: { type: 'category', data: xAxisData },
                        yAxis: { type: 'value' },
                        series: [{
                            data: seriesData,
                            type: viz.type || 'bar',
                            smooth: true,
                            itemStyle: { borderRadius: 4 }
                        }]
                    };
                    
                    if (viz.type === 'pie') {
                        option.xAxis = { show: false };
                        option.yAxis = { show: false };
                        option.series = [{
                            type: 'pie',
                            radius: '50%',
                            data: data.map(row => ({ name: row[viz.x], value: row[viz.y] }))
                        }];
                    }
                    
                    myChart.setOption(option);
                    window.addEventListener('resize', () => myChart.resize());
                });
            }
            container.classList.add('initialized');
        } catch (e) {
            console.error('SPPReport Charts Initialization Error:', e);
        }
    });
}
