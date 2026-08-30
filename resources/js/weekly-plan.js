export function setWeeklyPlanPending(form, progress) {
    const button = form.querySelector('button[type="submit"]');

    if (button) {
        button.disabled = true;
        button.textContent = 'Plan wird erstellt …';
        button.setAttribute('aria-disabled', 'true');
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
    const form = document.querySelector('[data-weekly-plan-form]');
    const progress = document.querySelector('[data-weekly-plan-progress]');

    if (!form || !progress) {
        return;
    }

    form.addEventListener('submit', (event) => startWeeklyPlanSubmission(event, form, progress));
}

function scheduleAfterPaint(callback) {
    window.requestAnimationFrame(() => window.setTimeout(callback, 0));
}
