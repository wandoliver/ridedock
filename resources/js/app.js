import L from 'leaflet';
import { initializePrivacySettings } from './privacy-settings.js';
import { initializeAiStatus } from './ai-status.js';
import { initializeSelectionPanels } from './selection-panels.js';
import { initializeActivityAnalysis } from './activity-analysis.js';
import { initializeActivityDetail } from './activity-detail.js';
import { initializeCoachTabs } from './coach-tabs.js';
import { decodePolyline, heatPointsFromRoutes, setHeatmapPanelExpanded } from './heatmap.js';
import { initializeProviderSync } from './provider-sync.js';
import { initializeWeeklyPlan } from './weekly-plan.js';
import { initializePageTabs } from './page-tabs.js';
import { initializeNavigation } from './navigation.js';

initializeNavigation();
initializePrivacySettings(L);
initializePageTabs();
initializeCoachTabs();

const heatmapElement = document.getElementById('ride-heatmap');
const heatmapDataElement = document.getElementById('ride-heatmap-data');

if (heatmapElement && heatmapDataElement) {
    window.L = L;
    await import('leaflet.heat');

    const routes = JSON.parse(heatmapDataElement.textContent);
    const decodedRoutes = routes.map((route) => decodePolyline(route.polyline));
    const map = L.map(heatmapElement, {
        attributionControl: true,
        scrollWheelZoom: true,
        zoomControl: false,
        trackResize: false,
    });

    L.control.zoom({ position: 'bottomright' }).addTo(map);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    let heatLayer = null;
    if (decodedRoutes.length > 0) {
        const routeLayer = L.layerGroup();

        for (const points of decodedRoutes) {
            L.polyline(points, {
                smoothFactor: 0,
                color: '#1f9a6a',
                opacity: 0.34,
                weight: 1.25,
            }).addTo(routeLayer);
        }

        routeLayer.addTo(map);
        heatLayer = L.heatLayer(heatPointsFromRoutes(decodedRoutes), {
            blur: 11,
            gradient: {
                0.15: '#177a54',
                0.45: '#1f9a6a',
                0.75: '#1f9a6a',
                1: '#177a54',
            },
            minOpacity: 0.2,
            radius: 8,
        });
    }

    let mapReady = false;
    const refreshHeatmap = () => {
        if (!heatmapElement.offsetWidth || !heatmapElement.offsetHeight) return;
        map.invalidateSize({ pan: false });
        if (!mapReady) {
            if (decodedRoutes.length) map.fitBounds(L.latLngBounds(decodedRoutes.flat()), { padding: [24, 24] });
            else map.setView([46.4983, 11.3548], 9);
            heatLayer?.addTo(map);
            mapReady = true;
        }
    };
    window.addEventListener('rd:view-change', refreshHeatmap);
    new window.ResizeObserver(refreshHeatmap).observe(heatmapElement);
    refreshHeatmap();

    const frame = document.querySelector('[data-heatmap-frame]');
    const expandButton = document.querySelector('[data-heatmap-expand]');
    const expandLabel = document.querySelector('[data-heatmap-expand-label]');

    if (frame && expandButton && expandLabel) {
        let expanded = false;

        expandButton.addEventListener('click', () => {
            expanded = !expanded;
            setHeatmapPanelExpanded(frame, expandButton, expandLabel, expanded);
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => map.invalidateSize({ pan: false }));
            });
            window.setTimeout(() => map.invalidateSize({ pan: false }), 150);
        });
    }
}

initializeActivityDetail(L);
initializeActivityAnalysis();
initializeAiStatus();
initializeWeeklyPlan();
initializeProviderSync();

initializeSelectionPanels();
