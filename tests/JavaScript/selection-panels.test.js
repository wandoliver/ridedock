import assert from 'node:assert/strict';
import test from 'node:test';
import { selectDetail } from '../../resources/js/selection-panels.js';

test('selection opens the matching details and an invalid selection preserves the current panel', () => {
    const panels = ['first', 'second'].map((detail) => ({ dataset: { detail }, hidden: detail !== 'first' }));
    const buttons = ['first', 'second'].map((select) => ({ dataset: { select }, attributes: {}, setAttribute(key, value) { this.attributes[key] = value; } }));
    const group = { querySelectorAll: (selector) => selector === '[data-detail]' ? panels : buttons };
    assert.equal(selectDetail(group, 'second'), true);
    assert.deepEqual(panels.map((panel) => panel.hidden), [true, false]);
    assert.equal(buttons[1].attributes['aria-pressed'], 'true');
    assert.equal(buttons[0].attributes['aria-pressed'], 'false');
    assert.equal(selectDetail(group, 'unknown'), false);
    assert.deepEqual(panels.map((panel) => panel.hidden), [true, false]);
});
