const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';
const DEFAULT_CHART_INSETS = Object.freeze({ top: 6, right: 6, bottom: 22, left: 42 });

export function routePointsFromStream(samples) {
    return samples.flatMap((sample) => {
        const value = sample && typeof sample === 'object' && 'value' in sample ? sample.value : sample;

        if (!Array.isArray(value) || value.length < 2) {
            return [];
        }

        const latitude = Number(value[0]);
        const longitude = Number(value[1]);

        if (!Number.isFinite(latitude)
            || !Number.isFinite(longitude)
            || latitude < -90
            || latitude > 90
            || longitude < -180
            || longitude > 180) {
            return [];
        }

        return [[latitude, longitude]];
    });
}

export function numericSeriesFromStream(samples, multiplier = 1) {
    return samples.flatMap((sample, index) => {
        const rawValue = sample && typeof sample === 'object' && 'value' in sample ? sample.value : sample;

        if (rawValue === null || rawValue === '' || typeof rawValue === 'boolean') {
            return [];
        }

        const value = Number(rawValue) * multiplier;
        const elapsedSeconds = sample && typeof sample === 'object'
            ? Number(sample.elapsed_seconds ?? index)
            : index;

        if (!Number.isFinite(value) || !Number.isFinite(elapsedSeconds)) {
            return [];
        }

        return [{ elapsedSeconds, value }];
    });
}

export function chartPath(series, width = 640, height = 160, insets = DEFAULT_CHART_INSETS) {
    if (series.length === 0) {
        return '';
    }

    const bounds = chartBounds(width, height, insets);
    const elapsedValues = series.map(({ elapsedSeconds }) => elapsedSeconds);
    const values = series.map(({ value }) => value);
    const minimumElapsed = Math.min(...elapsedValues);
    const maximumElapsed = Math.max(...elapsedValues);
    const minimumValue = Math.min(...values);
    const maximumValue = Math.max(...values);
    const elapsedRange = maximumElapsed - minimumElapsed || 1;
    const valueRange = maximumValue - minimumValue || 1;

    return series.map(({ elapsedSeconds, value }, index) => {
        const x = bounds.left + ((elapsedSeconds - minimumElapsed) / elapsedRange) * bounds.width;
        const y = bounds.bottom - ((value - minimumValue) / valueRange) * bounds.height;

        return `${index === 0 ? 'M' : 'L'}${x.toFixed(2)},${y.toFixed(2)}`;
    }).join(' ');
}

export function chartGridPath(
    width = 640,
    height = 160,
    insets = DEFAULT_CHART_INSETS,
    columns = 6,
    rows = 4,
) {
    const bounds = chartBounds(width, height, insets);
    const segments = [];

    for (let row = 0; row <= rows; row++) {
        const y = bounds.top + (row / rows) * bounds.height;
        segments.push(`M${bounds.left.toFixed(2)},${y.toFixed(2)} H${bounds.right.toFixed(2)}`);
    }

    for (let column = 0; column <= columns; column++) {
        const x = bounds.left + (column / columns) * bounds.width;
        segments.push(`M${x.toFixed(2)},${bounds.top.toFixed(2)} V${bounds.bottom.toFixed(2)}`);
    }

    return segments.join(' ');
}

export function chartAxisTicks(series, columns = 6, rows = 4) {
    const elapsedValues = series.map(({ elapsedSeconds }) => elapsedSeconds);
    const values = series.map(({ value }) => value);
    const minimumElapsed = Math.min(...elapsedValues);
    const maximumElapsed = Math.max(...elapsedValues);
    const minimumValue = Math.min(...values);
    const maximumValue = Math.max(...values);

    return {
        elapsedSeconds: Array.from(
            { length: columns + 1 },
            (_, index) => minimumElapsed + (index / columns) * (maximumElapsed - minimumElapsed),
        ),
        values: Array.from(
            { length: rows + 1 },
            (_, index) => maximumValue - (index / rows) * (maximumValue - minimumValue),
        ),
    };
}

export function formatElapsedTime(seconds) {
    const totalSeconds = Math.max(0, Math.round(seconds));
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);

    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}`;
    }

    return `${minutes}:${String(totalSeconds % 60).padStart(2, '0')}`;
}

export function initializeActivityDetail(leaflet) {
    const streamDataElement = document.getElementById('activity-streams-data');

    if (!streamDataElement) {
        return;
    }

    const streams = JSON.parse(streamDataElement.textContent);
    initializeRoute(leaflet, streams.latlng ?? []);
    initializeCombinedChart(streams);
    initializeRpe();

    for (const chartElement of document.querySelectorAll('[data-stream-chart]')) {
        initializeChart(chartElement, streams[chartElement.dataset.streamChart] ?? []);
    }
}

function initializeRoute(leaflet, samples) {
    const routeElement = document.getElementById('ride-route');

    if (!routeElement) {
        return;
    }

    const points = routePointsFromStream(samples);
    const map = leaflet.map(routeElement, {
        attributionControl: true,
        scrollWheelZoom: false,
        zoomControl: false,
    });

    leaflet.control.zoom({ position: 'bottomright' }).addTo(map);
    leaflet.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    const refresh = () => {
        if (routeElement.offsetWidth === 0) return;
        map.invalidateSize({ pan: false });
        if (points.length > 1) map.fitBounds(leaflet.latLngBounds(points), { padding: [32, 32] });
    };
    routeElement.closest('details')?.addEventListener('toggle', refresh);
    window.addEventListener('rd:view-change', refresh);

    if (points.length < 2) {
        map.setView(points[0] ?? [46.4983, 11.3548], 13);

        return;
    }

    const route = leaflet.polyline(points, {
        color: '#1f9a6a',
        lineCap: 'round',
        lineJoin: 'round',
        opacity: 0.95,
        weight: 3,
    }).addTo(map);

    map.fitBounds(route.getBounds(), { padding: [32, 32] });
}

function initializeChart(chartElement, samples) {
    const multiplier = Number(chartElement.dataset.multiplier ?? 1);
    const series = numericSeriesFromStream(samples, multiplier);
    const canvas = chartElement.querySelector('[data-chart-canvas]');
    const range = chartElement.querySelector('[data-chart-range]');

    if (!canvas || !range || series.length === 0) {
        if (canvas) {
            canvas.textContent = 'Keine verwertbaren Werte';
            canvas.classList.add('grid', 'place-items-center', 'text-sm', 'text-slate-400');
        }

        return;
    }

    const values = series.map(({ value }) => value);
    const minimum = Math.min(...values);
    const maximum = Math.max(...values);
    const unit = chartElement.dataset.unit ?? '';
    range.textContent = `${Math.round(minimum)}–${Math.round(maximum)} ${unit}`;

    const svg = document.createElementNS(SVG_NAMESPACE, 'svg');
    svg.setAttribute('viewBox', '0 0 640 160');
    svg.setAttribute('preserveAspectRatio', 'none');
    svg.setAttribute('class', 'size-full overflow-visible');
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', `${chartElement.dataset.streamChart}: ${range.textContent}`);

    const grid = document.createElementNS(SVG_NAMESPACE, 'path');
    grid.setAttribute('d', chartGridPath());
    grid.setAttribute('fill', 'none');
    grid.setAttribute('stroke', '#e2e8f0');
    grid.setAttribute('stroke-opacity', '0.8');
    grid.setAttribute('stroke-width', '1');
    grid.setAttribute('vector-effect', 'non-scaling-stroke');
    grid.setAttribute('shape-rendering', 'crispEdges');
    grid.setAttribute('aria-hidden', 'true');

    const line = document.createElementNS(SVG_NAMESPACE, 'path');
    line.setAttribute('d', chartPath(series));
    line.setAttribute('fill', 'none');
    line.setAttribute('stroke', chartElement.dataset.color ?? '#1f9a6a');
    line.setAttribute('stroke-linecap', 'round');
    line.setAttribute('stroke-linejoin', 'round');
    line.setAttribute('stroke-width', '3');
    line.setAttribute('vector-effect', 'non-scaling-stroke');

    const render = () => {
        const width = canvas.clientWidth;
        const height = canvas.clientHeight;
        if (!width || !height) return;
        const columns = width < 460 ? 3 : 6;
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        grid.setAttribute('d', chartGridPath(width, height, DEFAULT_CHART_INSETS, columns));
        line.setAttribute('d', chartPath(series, width, height));
        const area = document.createElementNS(SVG_NAMESPACE, 'path');
        const bounds = chartBounds(width, height, DEFAULT_CHART_INSETS);
        area.setAttribute('d', `${line.getAttribute('d')} L${bounds.right},${bounds.bottom} L${bounds.left},${bounds.bottom} Z`);
        area.setAttribute('fill', chartElement.dataset.streamChart === 'altitude' ? '#1f9a6a' : 'none');
        area.setAttribute('fill-opacity', '0.08');
        area.setAttribute('aria-hidden', 'true');
        svg.replaceChildren(grid, area, chartLabels(series, width, height, columns), line);
        canvas.replaceChildren(svg);
    };
    new window.ResizeObserver(render).observe(canvas);
    render();
}

function chartBounds(width, height, insets) {
    const normalizedInsets = typeof insets === 'number'
        ? { top: insets, right: insets, bottom: insets, left: insets }
        : insets;

    return {
        top: normalizedInsets.top,
        right: width - normalizedInsets.right,
        bottom: height - normalizedInsets.bottom,
        left: normalizedInsets.left,
        width: width - normalizedInsets.left - normalizedInsets.right,
        height: height - normalizedInsets.top - normalizedInsets.bottom,
    };
}

function chartLabels(series, width = 640, height = 160, columns = 6) {
    const group = document.createElementNS(SVG_NAMESPACE, 'g');
    const bounds = chartBounds(width, height, DEFAULT_CHART_INSETS);
    const ticks = chartAxisTicks(series, columns);

    group.setAttribute('fill', '#647080');
    group.setAttribute('font-size', '12');
    group.setAttribute('font-family', 'Hanken Grotesk, system-ui, sans-serif');
    group.setAttribute('aria-hidden', 'true');

    ticks.values.forEach((value, index) => {
        const y = bounds.top + (index / (ticks.values.length - 1)) * bounds.height;
        group.append(svgText(Math.round(value), bounds.left - 7, y + 3.5, 'end'));
    });

    ticks.elapsedSeconds.forEach((seconds, index) => {
        const x = bounds.left + (index / (ticks.elapsedSeconds.length - 1)) * bounds.width;
        const anchor = index === 0 ? 'start' : index === ticks.elapsedSeconds.length - 1 ? 'end' : 'middle';
        group.append(svgText(formatElapsedTime(seconds), x, height - 4, anchor));
    });

    return group;
}

function svgText(content, x, y, anchor) {
    const text = document.createElementNS(SVG_NAMESPACE, 'text');
    text.textContent = String(content);
    text.setAttribute('x', x);
    text.setAttribute('y', y);
    text.setAttribute('text-anchor', anchor);

    return text;
}

function initializeCombinedChart(streams) {
    const canvas = document.querySelector('[data-combined-chart]');
    if (!canvas) return;
    const series = ['altitude', 'heart_rate', 'speed'].map((type) => ({
        type, points: numericSeriesFromStream(streams[type] ?? [], type === 'speed' ? 3.6 : 1),
    })).filter(({ points }) => points.length > 0);
    if (!series.length) { canvas.hidden = true; return; }
    const maxTime = series.reduce((max, { points }) => points.reduce((time, point) => Math.max(time, point.elapsedSeconds), max), 1);
    const render = () => {
        const width = canvas.clientWidth;
        const height = canvas.clientHeight;
        if (!width || !height) return;
        const columns = width < 460 ? 3 : 6;
        const bounds = chartBounds(width, height, DEFAULT_CHART_INSETS);
        const svg = document.createElementNS(SVG_NAMESPACE, 'svg');
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        svg.setAttribute('class', 'size-full');
        svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', canvas.getAttribute('aria-label'));
        const grid = document.createElementNS(SVG_NAMESPACE, 'path');
        grid.setAttribute('d', chartGridPath(width, height, DEFAULT_CHART_INSETS, columns));
        grid.setAttribute('fill', 'none');
        grid.setAttribute('stroke', 'rgba(23,26,31,.08)');
        svg.append(grid);
        series.forEach(({ type, points }) => {
            const values = points.map((point) => point.value);
            const min = Math.min(...values);
            const range = Math.max(...values) - min || 1;
            const coordinates = points.map((point) => [bounds.left + point.elapsedSeconds / maxTime * bounds.width, bounds.bottom - (point.value - min) / range * bounds.height]);
            const path = document.createElementNS(SVG_NAMESPACE, 'path');
            let d = coordinates.map(([x, y], i) => `${i ? 'L' : 'M'}${x.toFixed(2)},${y.toFixed(2)}`).join(' ');
            if (type === 'altitude') d += ` L${coordinates.at(-1)[0]},${bounds.bottom} L${coordinates[0][0]},${bounds.bottom} Z`;
            path.setAttribute('d', d);
            path.setAttribute('fill', type === 'altitude' ? 'rgba(23,26,31,.05)' : 'none');
            path.setAttribute('stroke', type === 'speed' ? '#1f9a6a' : type === 'altitude' ? 'none' : '#171a1f');
            path.setAttribute('stroke-width', '1.5');
            path.setAttribute('vector-effect', 'non-scaling-stroke');
            svg.append(path);
        });
        for (let i = 0; i <= columns; i++) {
            const text = svgText(formatElapsedTime(maxTime * i / columns), bounds.left + i / columns * bounds.width, height - 4, i === columns ? 'end' : i === 0 ? 'start' : 'middle');
            text.setAttribute('font-size', '12');
            text.setAttribute('fill', '#647080');
            svg.append(text);
        }
        canvas.replaceChildren(svg);
    };
    new window.ResizeObserver(render).observe(canvas);
    render();
}

function initializeRpe() {
    const ratings = Array.from(document.querySelectorAll('input[name="rpe"]'));
    const paint = () => {
        const value = Number(ratings.find((input) => input.checked)?.value ?? 0);
        ratings.forEach((input) => {
            const segment = input.nextElementSibling;
            if (!segment || !input.value) return;
            segment.classList.toggle('rd-rpe-filled', Number(input.value) <= value);
        });
    };
    ratings.forEach((input) => input.addEventListener('change', () => {
        paint();
    }));
    paint();
}
