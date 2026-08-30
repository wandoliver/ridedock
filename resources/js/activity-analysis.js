export function setActivityAnalysisPending(form, progress) {
    const button = form.querySelector('button[type="submit"]');

    if (button) {
        button.disabled = true;
        button.textContent = 'Analyse läuft …';
        button.setAttribute('aria-disabled', 'true');
    }

    progress.hidden = false;
}

export function startActivityAnalysisSubmission(event, form, progress, schedule = scheduleAfterPaint) {
    if (form.dataset.submitting === 'true') {
        event.preventDefault();

        return;
    }

    event.preventDefault();
    form.dataset.submitting = 'true';
    setActivityAnalysisPending(form, progress);
    schedule(() => form.submit());
}

export function initializeActivityAnalysis() {
    const form = document.querySelector('[data-activity-analysis-form]');
    const progress = document.querySelector('[data-activity-analysis-progress]');

    if (!form || !progress) {
        return;
    }

    form.addEventListener('submit', (event) => startActivityAnalysisSubmission(event, form, progress));
}

function scheduleAfterPaint(callback) {
    window.requestAnimationFrame(() => window.setTimeout(callback, 0));
}
