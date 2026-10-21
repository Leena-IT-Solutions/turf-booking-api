import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
});

window.L = L;

// ApexCharts is loaded on demand (own chunk) so pages without charts stay light.
let apexPromise = null;
window.loadApexCharts = () => {
    if (!apexPromise) {
        apexPromise = import('apexcharts').then((m) => {
            window.ApexCharts = m.default;
            return m.default;
        });
    }
    return apexPromise;
};

/* ------------------------------------------------------------------
 * Reports → Historic Data chart (Alpine component, used with wire:ignore)
 * Count metrics = columns on the left axis, ₹ metrics = lines on the right axis.
 * ------------------------------------------------------------------ */
const formatInr = (value) => {
    const v = Number(value) || 0;
    const abs = Math.abs(v);
    if (abs >= 10000000) return '₹' + (v / 10000000).toFixed(1).replace(/\.0$/, '') + 'Cr';
    if (abs >= 100000) return '₹' + (v / 100000).toFixed(1).replace(/\.0$/, '') + 'L';
    if (abs >= 1000) return '₹' + (v / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
    return '₹' + Math.round(v);
};
const formatInrFull = (value) =>
    '₹' + (Number(value) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const buildHistoricOptions = (payload, isMobile) => {
    const series = payload.series || [];
    const hasCount = series.some((s) => s.unit === 'count');
    const hasInr = series.some((s) => s.unit === 'inr');

    const axisFor = (unit, opposite) => ({
        seriesName: series.filter((s) => s.unit === unit).map((s) => s.name),
        opposite,
        min: 0,
        forceNiceScale: true,
        decimalsInFloat: 0,
        labels: {
            style: { colors: '#6b7280', fontSize: '11px' },
            formatter: (v) => (unit === 'inr' ? formatInr(v) : Math.round(v)),
        },
    });
    const yaxis = [];
    if (hasCount) yaxis.push(axisFor('count', false));
    if (hasInr) yaxis.push(axisFor('inr', hasCount));

    return {
        chart: {
            type: 'line',
            height: isMobile ? 280 : 380,
            fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: true, speed: 450 },
            parentHeightOffset: 0,
        },
        series: series.map((s) => ({ name: s.name, type: s.type, data: s.data })),
        colors: series.map((s) => s.color),
        stroke: { width: series.map((s) => (s.type === 'line' ? 3 : 0)), curve: 'smooth' },
        markers: { size: series.map((s) => (s.type === 'line' ? 3 : 0)), strokeWidth: 0, hover: { size: 5 } },
        plotOptions: { bar: { borderRadius: 4, columnWidth: series.filter((s) => s.type === 'column').length > 1 ? '70%' : '55%' } },
        fill: { opacity: series.map((s) => (s.type === 'column' ? 0.9 : 1)) },
        dataLabels: { enabled: false },
        grid: { borderColor: '#eef0f4', strokeDashArray: 4, padding: { left: 6, right: 6 } },
        xaxis: {
            categories: payload.categories || [],
            tickAmount: isMobile ? 6 : undefined,
            tickPlacement: 'on',
            labels: { rotate: 0, hideOverlappingLabels: true, trim: false, style: { colors: '#6b7280', fontSize: '11px' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
            tooltip: { enabled: false },
        },
        yaxis: hasCount || hasInr ? yaxis : [],
        legend: {
            show: true,
            position: isMobile ? 'bottom' : 'top',
            horizontalAlign: isMobile ? 'center' : 'right',
            fontSize: '12px',
            markers: { size: 6 },
            onItemClick: { toggleDataSeries: false },
        },
        tooltip: {
            shared: true,
            intersect: false,
            theme: 'light',
            y: {
                formatter: (value, { seriesIndex, dataPointIndex }) => {
                    const s = series[seriesIndex];
                    if (!s) return value;
                    if (s.unit === 'inr') return formatInrFull(value);
                    let out = String(Math.round(value));
                    if (s.key === 'cancellations' && payload.cancellationValues) {
                        out += ' (' + formatInrFull(payload.cancellationValues[dataPointIndex] || 0) + ' cancelled)';
                    }
                    return out;
                },
            },
        },
        noData: { text: '' },
    };
};

const registerHistoricChart = (Alpine) => {
    Alpine.data('historicChart', (initialPayload) => ({
        chart: null,
        payload: initialPayload,
        isMobile: window.matchMedia('(max-width: 639px)').matches,
        async init() {
            this._onUpdate = (e) => {
                const detail = e.detail || {};
                const next = detail.payload || (Array.isArray(detail) ? detail[0]?.payload : null);
                if (!next) return;
                const sig = JSON.stringify(next);
                if (sig === this._sig) return; // nothing changed (e.g. the dispatch from the first render)
                this._sig = sig;
                this.payload = next;
                this.redraw();
            };
            this._sig = JSON.stringify(this.payload);
            window.addEventListener('historic-chart-updated', this._onUpdate);

            const ApexCharts = await window.loadApexCharts();
            if (!this.$refs.canvas || this._destroyed) return;
            this.chart = new ApexCharts(this.$refs.canvas, buildHistoricOptions(this.payload, this.isMobile));
            this.chart.render();

            this._mq = window.matchMedia('(max-width: 639px)');
            this._onMq = (ev) => {
                this.isMobile = ev.matches;
                this.redraw();
            };
            this._mq.addEventListener('change', this._onMq);
        },
        redraw() {
            if (!this.chart) return;
            // Replace series/axes in one go (series count can change when pills toggle).
            this.chart.updateOptions(buildHistoricOptions(this.payload, this.isMobile), true, true);
        },
        destroy() {
            this._destroyed = true;
            window.removeEventListener('historic-chart-updated', this._onUpdate);
            this._mq?.removeEventListener('change', this._onMq);
            this.chart?.destroy();
            this.chart = null;
        },
    }));
};

if (window.Alpine) {
    registerHistoricChart(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registerHistoricChart(window.Alpine));
}
