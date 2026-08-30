import assert from 'node:assert/strict';
import test from 'node:test';
import {
    chartAxisTicks,
    chartGridPath,
    chartPath,
    formatElapsedTime,
    numericSeriesFromStream,
    routePointsFromStream,
} from '../../resources/js/activity-detail.js';

test('builds an evenly spaced chart grid inside the plot area', () => {
    assert.equal(
        chartGridPath(100, 50, 5, 2, 2),
        'M5.00,5.00 H95.00 M5.00,25.00 H95.00 M5.00,45.00 H95.00 '
        + 'M5.00,5.00 V45.00 M50.00,5.00 V45.00 M95.00,5.00 V45.00',
    );
});

test('builds value and elapsed-time ticks for the chart axes', () => {
    const ticks = chartAxisTicks([
        { elapsedSeconds: 0, value: 100 },
        { elapsedSeconds: 10, value: 200 },
    ], 2, 2);

    assert.deepEqual(ticks, {
        elapsedSeconds: [0, 5, 10],
        values: [200, 150, 100],
    });
    assert.equal(formatElapsedTime(0), '0:00');
    assert.equal(formatElapsedTime(754), '12:34');
    assert.equal(formatElapsedTime(5400), '1:30');
});

test('extracts valid route points from normalized and plain samples', () => {
    assert.deepEqual(routePointsFromStream([
        { elapsed_seconds: 0, value: [46.4983, 11.3548] },
        [46.499, 11.3555],
        { value: [91, 11] },
        { value: ['invalid', 11] },
    ]), [
        [46.4983, 11.3548],
        [46.499, 11.3555],
    ]);
});

test('normalizes numeric samples and converts units', () => {
    assert.deepEqual(numericSeriesFromStream([
        { elapsed_seconds: 0, value: 7.5 },
        { elapsed_seconds: 10, value: 8 },
        { elapsed_seconds: 20, value: null },
    ], 3.6), [
        { elapsedSeconds: 0, value: 27 },
        { elapsedSeconds: 10, value: 28.8 },
    ]);
});

test('builds a stable chart path for changing and constant series', () => {
    assert.equal(chartPath([
        { elapsedSeconds: 0, value: 100 },
        { elapsedSeconds: 10, value: 200 },
    ], 100, 50, 5), 'M5.00,45.00 L95.00,5.00');
    assert.equal(chartPath([
        { elapsedSeconds: 0, value: 150 },
        { elapsedSeconds: 10, value: 150 },
    ], 100, 50, 5), 'M5.00,45.00 L95.00,45.00');
    assert.equal(chartPath([]), '');
});
