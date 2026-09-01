export function decodePolyline(encoded) {
    const points = [];
    let latitude = 0;
    let longitude = 0;
    let index = 0;

    while (index < encoded.length) {
        const latitudeDelta = decodeValue(encoded, index);
        index = latitudeDelta.nextIndex;
        const longitudeDelta = decodeValue(encoded, index);
        index = longitudeDelta.nextIndex;
        latitude += latitudeDelta.value;
        longitude += longitudeDelta.value;
        points.push([latitude / 100_000, longitude / 100_000]);
    }

    return points;
}

export function heatPointsFromRoutes(routes) {
    return routes.flatMap((route) => route.map(([latitude, longitude]) => [latitude, longitude, 0.55]));
}

export function setHeatmapPanelExpanded(frame, button, label, expanded) {
    frame.classList.toggle('min-h-[28rem]', !expanded);
    frame.classList.toggle('min-h-[42rem]', expanded);
    button.setAttribute('aria-pressed', String(expanded));
    label.textContent = expanded
        ? (button.dataset?.collapseLabel ?? 'Verkleinern')
        : (button.dataset?.expandLabel ?? 'Vergrößern');
}

function decodeValue(encoded, startIndex) {
    let result = 0;
    let shift = 0;
    let index = startIndex;
    let byte;

    do {
        if (index >= encoded.length) {
            throw new Error('Invalid encoded polyline.');
        }

        byte = encoded.charCodeAt(index) - 63;
        result |= (byte & 0x1f) << shift;
        shift += 5;
        index += 1;
    } while (byte >= 0x20);

    return {
        nextIndex: index,
        value: result & 1 ? ~(result >> 1) : result >> 1,
    };
}
