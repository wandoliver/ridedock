import assert from 'node:assert/strict';
import test from 'node:test';
import { activateCoachSection } from '../../resources/js/coach-tabs.js';

test('shows only the selected coach section and updates the active tab', () => {
    const tabs = ['goal-plan', 'training-block', 'weekly-plan', 'rides'].map(fakeTab);
    const sections = [
        fakeSection('goal-plan'),
        fakeSection('goal-plan'),
        fakeSection('training-block'),
        fakeSection('weekly-plan'),
        fakeSection('rides'),
    ];

    const active = activateCoachSection('training-block', tabs, sections);

    assert.equal(active, 'training-block');
    assert.equal(tabs[1].attributes['aria-current'], 'page');
    assert.equal(tabs[0].attributes['aria-current'], undefined);
    assert.equal(sections[0].hidden, true);
    assert.equal(sections[1].hidden, true);
    assert.equal(sections[2].hidden, false);
    assert.equal(sections[3].hidden, true);
    assert.equal(sections[4].hidden, true);
});

test('falls back to the roadmap for an unknown section', () => {
    const tabs = ['goal-plan', 'training-block'].map(fakeTab);
    const sections = [fakeSection('goal-plan'), fakeSection('training-block')];

    const active = activateCoachSection('unknown', tabs, sections);

    assert.equal(active, 'goal-plan');
    assert.equal(sections[0].hidden, false);
    assert.equal(sections[1].hidden, true);
});

function fakeTab(section) {
    return {
        attributes: {},
        dataset: { coachTab: section },
        classList: fakeClassList(),
        setAttribute(name, value) {
            this.attributes[name] = value;
        },
        removeAttribute(name) {
            delete this.attributes[name];
        },
    };
}

function fakeSection(section) {
    return {
        dataset: { coachSection: section },
        hidden: false,
    };
}

function fakeClassList() {
    const classes = new Set();

    return {
        toggle(...args) {
            const force = args.pop();

            args.forEach((className) => {
                if (force) {
                    classes.add(className);
                } else {
                    classes.delete(className);
                }
            });
        },
    };
}
