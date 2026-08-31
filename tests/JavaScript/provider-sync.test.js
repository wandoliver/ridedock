import assert from 'node:assert/strict';
import test from 'node:test';
import {
    setProviderSyncPending,
    startProviderSyncSubmission,
} from '../../resources/js/provider-sync.js';

test('shows synchronization progress and disables the button', () => {
    const button = fakeButton();
    const form = fakeForm(button);
    const progress = { hidden: true };

    setProviderSyncPending(form, progress);

    assert.equal(progress.hidden, false);
    assert.equal(button.disabled, true);
    assert.equal(button.textContent, 'Synchronisiere …');
    assert.equal(button.attributes['aria-disabled'], 'true');
});

test('paints synchronization progress before submitting the request', () => {
    const form = fakeForm(fakeButton());
    const progress = { hidden: true };
    const event = fakeEvent();
    let scheduledSubmission;

    startProviderSyncSubmission(event, form, progress, (callback) => {
        scheduledSubmission = callback;
    });

    assert.equal(event.prevented, true);
    assert.equal(progress.hidden, false);
    assert.equal(form.submitted, false);

    scheduledSubmission();

    assert.equal(form.submitted, true);
});

function fakeButton() {
    return {
        attributes: {},
        disabled: false,
        textContent: 'Jetzt synchronisieren',
        setAttribute(name, value) {
            this.attributes[name] = value;
        },
    };
}

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
