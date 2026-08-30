import assert from 'node:assert/strict';
import test from 'node:test';
import {
    setWeeklyPlanPending,
    startWeeklyPlanSubmission,
} from '../../resources/js/weekly-plan.js';

test('shows weekly plan progress and prevents a second submission', () => {
    const button = fakeButton();
    const form = {
        querySelector(selector) {
            return selector === 'button[type="submit"]' ? button : null;
        },
    };
    const progress = { hidden: true };

    setWeeklyPlanPending(form, progress);

    assert.equal(progress.hidden, false);
    assert.equal(button.disabled, true);
    assert.equal(button.textContent, 'Plan wird erstellt …');
    assert.equal(button.attributes['aria-disabled'], 'true');
});

function fakeButton() {
    return {
        attributes: {},
        disabled: false,
        textContent: 'Plan erstellen',
        setAttribute(name, value) {
            this.attributes[name] = value;
        },
    };
}

test('paints progress before submitting the weekly plan request', () => {
    const button = fakeButton();
    const form = fakeForm(button);
    const progress = { hidden: true };
    const event = fakeEvent();
    let scheduledSubmission;

    startWeeklyPlanSubmission(event, form, progress, (callback) => {
        scheduledSubmission = callback;
    });

    assert.equal(event.prevented, true);
    assert.equal(progress.hidden, false);
    assert.equal(form.submitted, false);
    assert.equal(typeof scheduledSubmission, 'function');

    scheduledSubmission();

    assert.equal(form.submitted, true);
});

function fakeForm(button) {
    return {
        dataset: {},
        submitted: false,
        querySelector() {
            return button;
        },
        submit() {
            this.submitted = true;
        },
    };
}

function fakeEvent() {
    return {
        prevented: false,
        preventDefault() {
            this.prevented = true;
        },
    };
}
