import assert from 'node:assert/strict';
import test from 'node:test';
import {
    decodePolyline,
    heatPointsFromRoutes,
    setHeatmapPanelExpanded,
} from '../../resources/js/heatmap.js';

test('decodes provider-neutral route polylines', () => {
    assert.deepEqual(decodePolyline('_p~iF~ps|U_ulLnnqC_mqNvxq`@'), [
        [38.5, -120.2],
        [40.7, -120.95],
        [43.252, -126.453],
    ]);
});

test('creates weighted heat points without changing coordinates', () => {
    assert.deepEqual(heatPointsFromRoutes([
        [[46.4983, 11.3548], [46.499, 11.3555]],
        [[46.5, 11.36]],
    ]), [
        [46.4983, 11.3548, 0.55],
        [46.499, 11.3555, 0.55],
        [46.5, 11.36, 0.55],
    ]);
});

test('rejects truncated polylines', () => {
    assert.throws(() => decodePolyline('_p~iF~ps|U_'), /Invalid encoded polyline/);
});

test('updates the inline heatmap panel expansion state', () => {
    const frame = fakeElement(['min-h-[28rem]']);
    const button = fakeElement();
    const label = fakeElement();

    setHeatmapPanelExpanded(frame, button, label, true);

    assert.deepEqual([...frame.classes], ['min-h-[42rem]']);
    assert.equal(button.attributes['aria-pressed'], 'true');
    assert.equal(label.textContent, 'Verkleinern');

    setHeatmapPanelExpanded(frame, button, label, false);

    assert.deepEqual([...frame.classes], ['min-h-[28rem]']);
    assert.equal(button.attributes['aria-pressed'], 'false');
    assert.equal(label.textContent, 'Vergrößern');
});

function fakeElement(classes = []) {
    const element = {
        attributes: {},
        classes: new Set(classes),
        textContent: '',
        setAttribute(name, value) {
            this.attributes[name] = value;
        },
    };
    element.classList = {
        toggle(name, enabled) {
            if (enabled) {
                element.classes.add(name);
            } else {
                element.classes.delete(name);
            }
        },
    };

    return element;
}
