import assert from 'node:assert/strict';
import test from 'node:test';
import {
    setActivityAnalysisPending,
    startActivityAnalysisSubmission,
} from '../../resources/js/activity-analysis.js';

test('shows progress and prevents a second analysis submission', () => {
    const button = fakeButton();
    const form = {
        querySelector(selector) {
            return selector === 'button[type="submit"]' ? button : null;
        },
    };
    const progress = { hidden: true };

    setActivityAnalysisPending(form, progress);

    assert.equal(progress.hidden, false);
    assert.equal(button.disabled, true);
    assert.equal(button.textContent, 'Analyse läuft …');
    assert.equal(button.attributes['aria-disabled'], 'true');
});

function fakeButton() {
    return {
        attributes: {},
        disabled: false,
        textContent: 'Analyse starten',
        setAttribute(name, value) {
            this.attributes[name] = value;
        },
    };
}

test('paints progress before submitting the activity analysis request', () => {
    const button = fakeButton();
    const form = {
        dataset: {},
        submitted: false,
        querySelector() {
            return button;
        },
        submit() {
            this.submitted = true;
        },
    };
    const progress = { hidden: true };
    const event = {
        prevented: false,
        preventDefault() {
            this.prevented = true;
        },
    };
    let scheduledSubmission;

    startActivityAnalysisSubmission(event, form, progress, (callback) => {
        scheduledSubmission = callback;
    });

    assert.equal(event.prevented, true);
    assert.equal(progress.hidden, false);
    assert.equal(form.submitted, false);

    scheduledSubmission();

    assert.equal(form.submitted, true);
});
