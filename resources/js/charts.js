import { registerAlpineData } from './alpine-register';
let apexPromise = null;
const loadApexCharts = () => (apexPromise ??= import('apexcharts').then((m) => m.default));

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

    /** Funil horizontal. data: {labels, values} */
    funnelBars(data) {
        return {
            ...baseOptions(),
            chart: { ...baseOptions().chart, type: 'bar', height: 260 },
            series: [{ name: 'Quantidade', data: data.values }],
            colors: [PALETTE.navy, '#1c3a70', PALETTE.blue, PALETTE.sky, PALETTE.gold],
            plotOptions: { bar: { horizontal: true, distributed: true, borderRadius: 4, barHeight: '70%' } },
            dataLabels: { enabled: true, style: { fontSize: '12px', fontWeight: 600 } },
            legend: { show: false },
            xaxis: { categories: data.labels, labels: { formatter: (v) => Math.round(v) } },
        };
    },

    /** Duas linhas (visitas e contratos). data: {labels, visits, contracts} */
    visitsContracts(data) {
        return {
            ...baseOptions(),
            chart: { ...baseOptions().chart, type: 'line', height: 260 },
            series: [{ name: 'Visitas', data: data.visits }, { name: 'Contratos', data: data.contracts }],
            colors: [PALETTE.blue, PALETTE.gold],
            stroke: { width: 3, curve: 'smooth' },
            markers: { size: 3 },
            xaxis: { categories: data.labels, tickAmount: Math.min(10, data.labels.length) },
            yaxis: { labels: { formatter: (v) => Math.round(v) }, min: 0 },
        };
    },

    /** Realizado x meta por mês. data: {labels, visits, visits_target, contracts, contracts_target} */
    targets(data) {
        return {
            ...baseOptions(),
            chart: { ...baseOptions().chart, type: 'line', height: 260 },
            series: [
                { name: 'Visitas', type: 'column', data: data.visits },
                { name: 'Meta de visitas', type: 'line', data: data.visits_target },
                { name: 'Contratos', type: 'column', data: data.contracts },
                { name: 'Meta de contratos', type: 'line', data: data.contracts_target },
            ],
            colors: [PALETTE.blue, PALETTE.navy, PALETTE.gold, '#a8701c'],
            stroke: { width: [0, 2, 0, 2], dashArray: [0, 5, 0, 5] },
            plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } },
            xaxis: { categories: data.labels },
            yaxis: { labels: { formatter: (v) => Math.round(v) }, min: 0 },
        };
    },

    /** Ranking horizontal. data: {labels, values, name} */
    ranking(data) {
        return {
            ...baseOptions(),
            chart: { ...baseOptions().chart, type: 'bar', height: Math.max(160, data.labels.length * 38) },
            series: [{ name: data.name ?? 'Total', data: data.values }],
            colors: [PALETTE.blue],
            plotOptions: { bar: { horizontal: true, borderRadius: 3, barHeight: '60%' } },
            dataLabels: { enabled: true, style: { fontSize: '12px' } },
            xaxis: { categories: data.labels, labels: { formatter: (v) => Math.round(v) } },
            legend: { show: false },
        };
    },

    /** Rosca. data: {labels, values, total_label, money?} */
    donut(data) {
        if (data.money === false) {
            return {
                ...baseOptions(),
                chart: { ...baseOptions().chart, type: 'donut', height: 240 },
                series: data.values,
                labels: data.labels,
                colors: [PALETTE.navy, PALETTE.blue, PALETTE.gold, PALETTE.sky, PALETTE.zinc, PALETTE.green],
                legend: { position: 'bottom', fontSize: '12px' },
                stroke: { width: 2 },
                plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: data.total_label ?? 'Total' } } } } },
            };
        }
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
        async init() {
            // A biblioteca de gráficos é baixada só nas telas que têm gráfico.
            const ApexCharts = await loadApexCharts();
            this.chart = new ApexCharts(this.$refs.canvas, builders[kind](initial));
            this.chart.render();
            if (event) {
                window.addEventListener(event, (e) => {
                    const data = e.detail.data ?? e.detail[0]?.data;
                    if (data) this.chart?.updateOptions(builders[kind](data), true, true);
                });
            }
        },
        destroy() {
            this.chart?.destroy();
        },
    };
}

registerAlpineData('apexChart', apexChart);
