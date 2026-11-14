import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/*
| Mapas com Leaflet + OpenStreetMap, sem API paga.
| Os marcadores são divIcons (HTML), para não depender de imagens externas.
*/

const DEFAULT_CENTER = [-7.2131, -39.3153]; // região do Cariri

function tileLayer(el) {
    return L.tileLayer(el.dataset.tiles, {
        maxZoom: 19,
        attribution: el.dataset.attribution,
    });
}

function numberedIcon(label, color, ring = false) {
    return L.divIcon({
        className: '',
        html: `<span style="display:flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:9999px;background:${color};color:#fff;font:600 12px Inter,system-ui,sans-serif;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35);${ring ? 'outline:2px dashed ' + color + ';outline-offset:2px;' : ''}">${label}</span>`,
        iconSize: [28, 28],
        iconAnchor: [14, 14],
        popupAnchor: [0, -14],
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

/**
 * Mapa da rota do dia.
 * data: { stops: [{lat,lng,seq,color,title,subtitle,approximate,executive}], base: {lat,lng,label}|null,
 *         checkins: [{lat,lng,title,subtitle}] }
 */
function routeMap(initial) {
    return {
        map: null,
        layer: null,
        init() {
            this.map = L.map(this.$refs.canvas, { scrollWheelZoom: false });
            tileLayer(this.$refs.canvas).addTo(this.map);
            this.layer = L.layerGroup().addTo(this.map);
            this.render(initial);
            window.addEventListener('agenda-map', (e) => this.render(e.detail.data ?? e.detail[0]?.data ?? e.detail));
        },
        render(data) {
            if (!this.map || !data) return;
            this.layer.clearLayers();
            const bounds = [];
            const byExecutive = {};

            if (data.base) {
                L.circleMarker([data.base.lat, data.base.lng], { radius: 6, color: '#0b234b', fillColor: '#fff', fillOpacity: 1, weight: 3 })
                    .bindPopup(`<strong>Base</strong><br>${escapeHtml(data.base.label)}`)
                    .addTo(this.layer);
                bounds.push([data.base.lat, data.base.lng]);
            }

            for (const stop of data.stops ?? []) {
                const key = stop.executive ?? 'all';
                (byExecutive[key] ??= []).push([stop.lat, stop.lng]);
                L.marker([stop.lat, stop.lng], { icon: numberedIcon(stop.seq, stop.color, stop.approximate) })
                    .bindPopup(`<strong>${escapeHtml(stop.title)}</strong><br>${escapeHtml(stop.subtitle)}${stop.approximate ? '<br><em>Local aproximado (centro da cidade)</em>' : ''}`)
                    .addTo(this.layer);
                bounds.push([stop.lat, stop.lng]);
            }

            for (const points of Object.values(byExecutive)) {
                const path = data.base && Object.keys(byExecutive).length === 1 ? [[data.base.lat, data.base.lng], ...points] : points;
                if (path.length > 1) {
                    L.polyline(path, { color: '#2f6fd0', weight: 3, opacity: 0.8, dashArray: '6 6' }).addTo(this.layer);
                }
            }

            for (const c of data.checkins ?? []) {
                L.circleMarker([c.lat, c.lng], { radius: 7, color: '#fff', fillColor: '#0b234b', fillOpacity: 1, weight: 2 })
                    .bindPopup(`<strong>${escapeHtml(c.title)}</strong><br>${escapeHtml(c.subtitle)}`)
                    .addTo(this.layer);
                bounds.push([c.lat, c.lng]);
            }

            if (bounds.length > 1) {
                this.map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
            } else if (bounds.length === 1) {
                this.map.setView(bounds[0], 14);
            } else {
                this.map.setView(DEFAULT_CENTER, 10);
            }
            setTimeout(() => this.map.invalidateSize(), 50);
        },
    };
}

/**
 * Seletor de ponto para o formulário de visita: clique no mapa para marcar o local.
 * Grava em $wire[latField] / $wire[lngField].
 */
function pinPicker(latField, lngField) {
    return {
        map: null,
        marker: null,
        init() {
            this.map = L.map(this.$refs.canvas, { scrollWheelZoom: false });
            tileLayer(this.$refs.canvas).addTo(this.map);
            this.sync();
            this.map.on('click', (e) => {
                this.$wire.set(latField, e.latlng.lat.toFixed(7), false);
                this.$wire.set(lngField, e.latlng.lng.toFixed(7), false);
                this.place([e.latlng.lat, e.latlng.lng]);
            });
            this.$watch('$wire.' + latField, () => this.sync());
            window.addEventListener('pin-picker-center', (e) => {
                const d = e.detail.center ?? e.detail[0]?.center;
                if (d && !this.marker) this.map.setView([d.lat, d.lng], 13);
            });
            setTimeout(() => this.map.invalidateSize(), 200);
        },
        sync() {
            const lat = parseFloat(this.$wire.get(latField));
            const lng = parseFloat(this.$wire.get(lngField));
            if (!isNaN(lat) && !isNaN(lng)) {
                this.place([lat, lng]);
                this.map.setView([lat, lng], 15);
            } else {
                if (this.marker) { this.marker.remove(); this.marker = null; }
                this.map.setView(DEFAULT_CENTER, 10);
            }
        },
        place(latlng) {
            if (this.marker) this.marker.setLatLng(latlng);
            else this.marker = L.marker(latlng, { icon: numberedIcon('•', '#e9a83a') }).addTo(this.map);
        },
        clear() {
            this.$wire.set(latField, null, false);
            this.$wire.set(lngField, null, false);
            if (this.marker) { this.marker.remove(); this.marker = null; }
        },
        refresh() {
            setTimeout(() => this.map.invalidateSize(), 50);
        },
    };
}

/**
 * Mapa de concentração de indicações por município: círculos no centro de
 * cada cidade, com tamanho e cor pela quantidade de indicações.
 * points: [{lat,lng,name,total,signed}]
 */
function heatMap(initial) {
    const color = (n) => (n > 25 ? '#0b234b' : n >= 16 ? '#1c3a70' : n >= 8 ? '#2f6fd0' : '#86a4d4');

    return {
        map: null,
        layer: null,
        init() {
            this.map = L.map(this.$refs.canvas, { scrollWheelZoom: false });
            tileLayer(this.$refs.canvas).addTo(this.map);
            this.layer = L.layerGroup().addTo(this.map);
            this.render(initial);
            window.addEventListener('referral-map', (e) => this.render(e.detail.points ?? e.detail[0]?.points ?? []));
        },
        render(points) {
            this.layer.clearLayers();
            const bounds = [];
            const max = Math.max(1, ...points.map((p) => p.total));

            for (const p of points) {
                const radius = 8 + Math.sqrt(p.total / max) * 22;
                L.circleMarker([p.lat, p.lng], { radius, color: '#fff', weight: 2, fillColor: color(p.total), fillOpacity: 0.85 })
                    .bindTooltip(`<strong>${escapeHtml(p.name)}</strong><br>${p.total} indicação(ões) · ${p.signed} contrato(s)`)
                    .addTo(this.layer);
                bounds.push([p.lat, p.lng]);
            }

            if (bounds.length > 1) this.map.fitBounds(bounds, { padding: [40, 40], maxZoom: 11 });
            else if (bounds.length === 1) this.map.setView(bounds[0], 10);
            else this.map.setView(DEFAULT_CENTER, 9);
            setTimeout(() => this.map.invalidateSize(), 50);
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('routeMap', routeMap);
    window.Alpine.data('pinPicker', pinPicker);
    window.Alpine.data('heatMap', heatMap);
});
