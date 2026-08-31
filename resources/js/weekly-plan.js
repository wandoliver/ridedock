export function setWeeklyPlanPending(form, progress) {
    const button = form.querySelector('button[type="submit"]');

    if (button) {
        button.disabled = true;
        button.textContent = form.dataset?.pendingLabel ?? 'Plan wird erstellt …';
        button.setAttribute('aria-disabled', 'true');
    }

    const title = progress.querySelector?.('[data-plan-progress-title]');
    const copy = progress.querySelector?.('[data-plan-progress-copy]');

    if (title && form.dataset?.progressTitle) {
        title.textContent = form.dataset.progressTitle;
    }

    if (copy && form.dataset?.progressCopy) {
        copy.textContent = form.dataset.progressCopy;
    }

    progress.hidden = false;
}

export function startWeeklyPlanSubmission(event, form, progress, schedule = scheduleAfterPaint) {
    if (form.dataset.submitting === 'true') {
        event.preventDefault();

        return;
    }

    event.preventDefault();
    form.dataset.submitting = 'true';
    setWeeklyPlanPending(form, progress);
    schedule(() => form.submit());
}

export function initializeWeeklyPlan() {
    const forms = document.querySelectorAll('[data-training-plan-form]');
    const progress = document.querySelector('[data-weekly-plan-progress]');

    if (forms.length === 0 || !progress) {
        return;
    }

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => startWeeklyPlanSubmission(event, form, progress));
    });
}

function scheduleAfterPaint(callback) {
    window.requestAnimationFrame(() => window.setTimeout(callback, 0));
}
