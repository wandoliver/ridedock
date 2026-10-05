import { decodePolyline } from './heatmap.js';

export function initializePrivacySettings(leaflet) {
    const element = document.getElementById('privacy-zone-map');
    const dataElement = document.getElementById('privacy-zone-map-data');
    if (!element || !dataElement) return;
    const data = JSON.parse(dataElement.textContent);
    const map = leaflet.map(element, { scrollWheelZoom: false });
    leaflet.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
    }).addTo(map);
    const bounds = leaflet.latLngBounds([]);
    for (const route of data.routes) {
        const points = decodePolyline(route.polyline);
        leaflet.polyline(points, { color: '#1f9a6a', weight: 2, smoothFactor: 0 }).addTo(map);
        for (const point of points) bounds.extend(point);
    }
    for (const zone of data.zones) {
        const circle = leaflet.circle([zone.latitude, zone.longitude], { radius: zone.radius_m, color: '#647080' }).addTo(map);
        bounds.extend(circle.getBounds());
    }
    if (bounds.isValid()) map.fitBounds(bounds, { padding: [24, 24], maxZoom: 15 });
    else map.setView([0, 0], 2);
    const latitude = document.getElementById('privacy-zone-latitude');
    const longitude = document.getElementById('privacy-zone-longitude');
    const radius = document.getElementById('privacy-zone-radius');
    let selected = null;
    const paint = () => {
        if (latitude.value === '' || longitude.value === '') return;
        const point = [Number(latitude.value), Number(longitude.value)];
        if (!point.every(Number.isFinite)) return;
        selected?.remove();
        selected = leaflet.circle(point, { radius: Number(radius.value) || 400, color: '#1f9a6a' }).addTo(map);
    };
    map.on('click', ({ latlng }) => {
        latitude.value = latlng.lat.toFixed(7);
        longitude.value = (((latlng.lng + 180) % 360 + 360) % 360 - 180).toFixed(7);
        paint();
    });
    for (const input of [latitude, longitude, radius]) input.addEventListener('input', paint);
    for (const input of [latitude, longitude]) input.addEventListener('invalid', () => {
        input.closest('details').open = true;
    });
    paint();
    new window.ResizeObserver(() => map.invalidateSize({ pan: false })).observe(element);
}
