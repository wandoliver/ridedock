export function setProviderSyncPending(form, progress) {
    const button = form.querySelector('[data-provider-sync-button]');

    if (button) {
        button.disabled = true;
        button.textContent = 'Synchronisiere …';
        button.setAttribute('aria-disabled', 'true');
    }

    progress.hidden = false;
}

export function startProviderSyncSubmission(event, form, progress, schedule = scheduleAfterPaint) {
    if (form.dataset.submitting === 'true') {
        event.preventDefault();

        return;
    }

    event.preventDefault();
    form.dataset.submitting = 'true';
    setProviderSyncPending(form, progress);
    schedule(() => form.submit());
}

export function initializeProviderSync() {
    const card = document.querySelector('[data-provider-connection-card]');
    const form = card?.querySelector('[data-provider-sync-form]');
    const progress = card?.querySelector('[data-provider-sync-progress]');

    if (!form || !progress) {
        return;
    }

    form.addEventListener('submit', (event) => startProviderSyncSubmission(event, form, progress));
}

function scheduleAfterPaint(callback) {
    window.requestAnimationFrame(() => window.setTimeout(callback, 0));
}
