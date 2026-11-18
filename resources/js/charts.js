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

    /** Minigráfico dos cartões. data: {values, color} */
    spark(data) {
        return {
            chart: { type: 'area', height: 46, sparkline: { enabled: true }, animations: { enabled: true, speed: 600 } },
            series: [{ name: '', data: data.values }],
            colors: [data.color ?? PALETTE.blue],
            stroke: { width: 2, curve: 'smooth' },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0, stops: [0, 100] } },
            tooltip: { enabled: false },
        };
    },

    /** Realizado (área) e projeção (linha tracejada). data: {labels, actual, projection, money, title} */
    trend(data) {
        const fmt = data.money ? brl : (v) => (v === null || v === undefined ? '' : Math.round(v).toLocaleString('pt-BR'));
        return {
            ...baseOptions(),
            chart: { ...baseOptions().chart, type: 'line', height: 320, animations: { enabled: true, speed: 700 } },
            series: [
                { name: data.title, type: 'area', data: data.actual },
                { name: 'Projeção', type: 'line', data: data.projection },
            ],
            colors: [PALETTE.blue, PALETTE.gold],
            stroke: { width: [3, 3], curve: 'smooth', dashArray: [0, 6] },
            fill: { type: ['gradient', 'solid'], gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.02, stops: [0, 95] } },
            markers: { size: [3, 4], strokeWidth: 0, hover: { size: 6 } },
            xaxis: { categories: data.labels },
            yaxis: { labels: { formatter: fmt }, min: 0 },
            tooltip: { shared: true, y: { formatter: (v) => (v === null || v === undefined ? '—' : fmt(v)) } },
        };
    },

    /** Medidor de meta. data: {pct, expected, label} */
    radial(data) {
        const value = Math.min(100, Number(data.pct ?? 0));
        return {
            chart: { type: 'radialBar', height: 210, fontFamily: 'Inter, system-ui, sans-serif', animations: { enabled: true, speed: 900 } },
            series: [value],
            labels: [data.label],
            colors: [value >= Number(data.expected ?? 0) ? PALETTE.green : PALETTE.gold],
            plotOptions: {
                radialBar: {
                    hollow: { size: '62%' },
                    track: { background: '#eef3fa', strokeWidth: '100%' },
                    startAngle: -120,
                    endAngle: 120,
                    dataLabels: {
                        name: { offsetY: 22, fontSize: '12px', color: '#71717a' },
                        value: { offsetY: -12, fontSize: '26px', fontWeight: 700, color: PALETTE.navy, formatter: () => Number(data.pct ?? 0).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%' },
                    },
                },
            },
            stroke: { lineCap: 'round' },
        };
    },

    /** Funil por etapa. data: {labels, values} */
    funnel(data) {
        return {
            ...baseOptions(),
            chart: { ...baseOptions().chart, type: 'bar', height: 300 },
            series: [{ name: 'Oportunidades', data: data.values }],
            plotOptions: { bar: { horizontal: true, isFunnel: true, borderRadius: 4, barHeight: '78%', distributed: true } },
            colors: ['#0b234b', '#1c3a70', '#2f6fd0', '#567dbe', '#e9a83a'],
            dataLabels: { enabled: true, formatter: (v) => Number(v).toLocaleString('pt-BR'), style: { fontSize: '12px', fontWeight: 700 }, dropShadow: { enabled: false } },
            xaxis: { categories: data.labels },
            legend: { show: true, position: 'bottom', horizontalAlign: 'center', fontSize: '12px' },
            tooltip: { y: { formatter: (v) => Number(v).toLocaleString('pt-BR') + ' oportunidade(s)' } },
        };
    },

    /** Executivos: contratos (barras) e receita (linha). data: {labels, contracts, revenue} */
    rankingDual(data) {
        return {
            ...baseOptions(),
            chart: { ...baseOptions().chart, type: 'line', height: 300 },
            series: [{ name: 'Contratos', type: 'column', data: data.contracts }, { name: 'Receita contratada', type: 'line', data: data.revenue }],
            colors: [PALETTE.navy, PALETTE.gold],
            stroke: { width: [0, 3], curve: 'smooth' },
            plotOptions: { bar: { columnWidth: '50%', borderRadius: 4 } },
            markers: { size: [0, 5] },
            xaxis: { categories: data.labels, labels: { hideOverlappingLabels: false, rotate: -30, style: { fontSize: '11px' } } },
            yaxis: [
                { title: { text: 'Contratos' }, labels: { formatter: (v) => Math.round(v) }, min: 0 },
                { opposite: true, title: { text: 'Receita' }, labels: { formatter: brl }, min: 0 },
            ],
        };
    },

    /** Mapa de calor. data: [{name, data: [{x, y}]}] */
    heatmap(data) {
        return {
            ...baseOptions(),
            chart: { ...baseOptions().chart, type: 'heatmap', height: 260 },
            series: data,
            colors: [PALETTE.blue],
            plotOptions: { heatmap: { radius: 4, shadeIntensity: 0.6, colorScale: { ranges: [{ from: 0, to: 0, color: '#f4f4f5', name: 'nenhuma' }] } } },
            dataLabels: { enabled: false },
            stroke: { width: 2, colors: ['#ffffff'] },
            legend: { show: false },
            tooltip: { y: { formatter: (v) => v + ' visita(s)' } },
        };
    },

    /** Interessados por semana. data: {labels, new, qualified, discarded} */
    prospects(data) {
        return {
            ...baseOptions(),
            chart: { ...baseOptions().chart, type: 'bar', height: 260 },
            series: [{ name: 'Chegaram', data: data.new }, { name: 'Qualificados', data: data.qualified }, { name: 'Descartados', data: data.discarded }],
            colors: [PALETTE.sky, PALETTE.green, PALETTE.zinc],
            plotOptions: { bar: { columnWidth: '70%', borderRadius: 3 } },
            xaxis: { categories: data.labels, labels: { style: { fontSize: '11px' } } },
            yaxis: { labels: { formatter: (v) => Math.round(v) }, min: 0 },
        };
    },

    /** Decisões do INSS por mês e taxa de deferimento. data: {labels, granted, denied, rate} */
    inss(data) {
        return {
            ...baseOptions(),
            chart: { ...baseOptions().chart, type: 'line', height: 280, stacked: true },
            series: [
                { name: 'Deferidos', type: 'column', data: data.granted },
                { name: 'Indeferidos', type: 'column', data: data.denied },
                { name: 'Taxa de deferimento', type: 'line', data: data.rate },
            ],
            colors: [PALETTE.green, PALETTE.red, PALETTE.navy],
            stroke: { width: [0, 0, 3], curve: 'smooth' },
            plotOptions: { bar: { columnWidth: '50%', borderRadius: 3 } },
            markers: { size: [0, 0, 4] },
            xaxis: { categories: data.labels },
            yaxis: [
                { seriesName: 'Deferidos', title: { text: 'Decisões' }, labels: { formatter: (v) => Math.round(v) }, min: 0 },
                { seriesName: 'Deferidos', show: false },
                { opposite: true, seriesName: 'Taxa de deferimento', max: 100, min: 0, labels: { formatter: (v) => Math.round(v) + '%' } },
            ],
        };
    },

    /** Escritório (C2): carteira a receber e prazos atrasados por dia. data: {labels, receivable, overdue} */
    office(data) {
        const series = [];
        const yaxis = [];
        if (data.receivable.length) {
            series.push({ name: 'A receber em aberto', type: 'area', data: data.receivable });
            yaxis.push({ seriesName: 'A receber em aberto', labels: { formatter: brl } });
        }
        series.push({ name: 'Prazos atrasados', type: 'line', data: data.overdue });
        yaxis.push({ seriesName: 'Prazos atrasados', opposite: data.receivable.length > 0, min: 0, labels: { formatter: (v) => Math.round(v) } });
        return {
            ...baseOptions(),
            chart: { ...baseOptions().chart, type: 'line', height: 220 },
            series,
            colors: data.receivable.length ? [PALETTE.navy, PALETTE.red] : [PALETTE.red],
            stroke: { width: data.receivable.length ? [2, 3] : [3], curve: 'smooth' },
            fill: { type: data.receivable.length ? ['gradient', 'solid'] : ['solid'], gradient: { opacityFrom: 0.3, opacityTo: 0.02 } },
            markers: { size: 3 },
            xaxis: { categories: data.labels },
            yaxis,
        };
    },

    /** Receita contratada, recebida e custos. data: {labels, costs, revenue, received} */
    moneyFlow(data) {
        return {
            ...baseOptions(),
            chart: { ...baseOptions().chart, type: 'line', height: 300 },
            series: [
                { name: 'Receita contratada', type: 'column', data: data.revenue },
                { name: 'Receita recebida', type: 'column', data: data.received },
                { name: 'Custos', type: 'line', data: data.costs },
            ],
            colors: [PALETTE.navy, PALETTE.gold, PALETTE.red],
            stroke: { width: [0, 0, 3], curve: 'smooth' },
            plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } },
            markers: { size: [0, 0, 4] },
            xaxis: { categories: data.labels },
            yaxis: { labels: { formatter: brl }, min: 0 },
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

/** Gráfico de tendência com troca de indicador sem nova consulta. sets: {chave: dados} */
function trendChart(sets, initialKey) {
    return {
        chart: null,
        key: initialKey,
        async init() {
            const ApexCharts = await loadApexCharts();
            this.chart = new ApexCharts(this.$refs.canvas, builders.trend(sets[this.key]));
            this.chart.render();
            this.$watch('key', (k) => this.chart?.updateOptions(builders.trend(sets[k]), true, true));
        },
        current() {
            return sets[this.key];
        },
        destroy() {
            this.chart?.destroy();
        },
    };
}

registerAlpineData('trendChart', trendChart);

/** Número que conta até o valor final ao aparecer. format: int | money | pct | days */
function countUp(target, format) {
    const fmt = (v) => {
        if (target === null) return '—';
        if (format === 'money') return 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        if (format === 'pct') return v.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
        if (format === 'days') return v.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' dias';
        return Math.round(v).toLocaleString('pt-BR');
    };
    return {
        text: fmt(target ?? 0),
        init() {
            if (target === null || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                this.text = fmt(target ?? 0);
                return;
            }
            const start = performance.now();
            const step = (t) => {
                const p = Math.min(1, (t - start) / 900);
                this.text = fmt(target * (1 - Math.pow(1 - p, 3)));
                if (p < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        },
    };
}

registerAlpineData('countUp', countUp);
