import assert from 'node:assert/strict';
import test from 'node:test';
import { activatePageTab } from '../../resources/js/page-tabs.js';

test('deep links expose all panels belonging to a section while hiding other form sections', () => {
    const tabs = ['profile', 'thresholds', 'ai'].map((name) => ({
        dataset: { pageTab: name }, attributes: {},
        setAttribute(key, value) { this.attributes[key] = value; },
        removeAttribute(key) { delete this.attributes[key]; },
    }));
    const panels = ['profile', 'thresholds', 'thresholds', 'ai'].map((name) => ({ dataset: { pagePanel: name }, hidden: false }));
    assert.equal(activatePageTab('thresholds', tabs, panels, 'profile'), 'thresholds');
    assert.deepEqual(panels.map((panel) => panel.hidden), [true, false, false, true]);
    assert.equal(tabs[1].attributes['aria-current'], 'page');
    assert.equal(activatePageTab('invalid', tabs, panels, 'profile'), 'profile');
    assert.deepEqual(panels.map((panel) => panel.hidden), [false, true, true, true]);
    assert.equal(tabs[1].attributes['aria-current'], undefined);
});
