import ApexCharts from 'apexcharts';

/*
| Gráficos com ApexCharts. Cada gráfico recebe as séries prontas do Livewire
| e se atualiza quando o componente dispara o evento informado em `event`.
*/

const PALETTE = {
    navy: '#0b234b',
    blue: '#2f6fd0',
    sky: '#86a4d4',
    gold: '#e9a83a',
    green: '#009a66',
    red: '#dc2626',
    zinc: '#a1a1aa',
};

const brl = (v) => 'R$ ' + Number(v ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

function baseOptions() {
    return {
        chart: { fontFamily: 'Inter, system-ui, sans-serif', toolbar: { show: false }, zoom: { enabled: false }, animations: { speed: 300 } },
        grid: { borderColor: '#e4e4e7', strokeDashArray: 4 },
        dataLabels: { enabled: false },
        legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px' },
        tooltip: { theme: 'light' },
    };
}

const builders = {
    /** Barras de custo e receita com linha de margem. data: {labels, costs, revenue, margin} */
    costRevenue(data) {
        return {
            ...baseOptions(),
            chart: { ...baseOptions().chart, type: 'line', height: 300 },
            series: [
                { name: 'Custos', type: 'column', data: data.costs },
                { name: 'Receita contratada', type: 'column', data: data.revenue },
                { name: 'Margem estimada', type: 'line', data: data.margin },
            ],
            colors: [PALETTE.navy, PALETTE.gold, PALETTE.green],
            stroke: { width: [0, 0, 3], curve: 'smooth' },
            plotOptions: { bar: { columnWidth: '45%', borderRadius: 3 } },
            markers: { size: [0, 0, 4] },
            xaxis: { categories: data.labels },
            yaxis: { labels: { formatter: brl } },
            tooltip: { y: { formatter: (v) => 'R$ ' + Number(v ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) } },
        };
    },

    /** Rosca. data: {labels, values, total_label} */
    donut(data) {
        return {
            ...baseOptions(),
            chart: { ...baseOptions().chart, type: 'donut', height: 260 },
            series: data.values,
            labels: data.labels,
            colors: [PALETTE.navy, PALETTE.blue, PALETTE.sky, PALETTE.gold, PALETTE.green, PALETTE.zinc, '#1c3a70', '#a8701c', '#567dbe', '#fdbe50', '#71717a'],
            legend: { position: 'bottom', fontSize: '12px' },
            stroke: { width: 2 },
            plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: data.total_label ?? 'Total', formatter: (w) => brl(w.globals.seriesTotals.reduce((a, b) => a + b, 0)) } } } } },
            tooltip: { y: { formatter: (v) => 'R$ ' + Number(v ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) } },
        };
    },
};

function apexChart(kind, initial, event) {
    return {
        chart: null,
        init() {
            this.chart = new ApexCharts(this.$refs.canvas, builders[kind](initial));
            this.chart.render();
            if (event) {
                window.addEventListener(event, (e) => {
                    const data = e.detail.data ?? e.detail[0]?.data;
                    if (data) this.chart.updateOptions(builders[kind](data), true, true);
                });
            }
        },
        destroy() {
            this.chart?.destroy();
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('apexChart', apexChart);
});
