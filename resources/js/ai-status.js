export async function hasFinishedAnalysis(elements, fetchStatus) {
    const statuses = await Promise.all(Array.from(elements, async (element) => {
        const response = await fetchStatus(element.dataset.aiStatusUrl, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });
        if (!response.ok) {
            throw new Error('Unable to read analysis status');
        }
        const data = await response.json();
        return ['completed', 'failed'].includes(data.status);
    }));
    return statuses.some(Boolean);
}

export function initializeAiStatus() {
    const elements = document.querySelectorAll('[data-ai-status-url]');
    if (elements.length === 0) {
        return;
    }
    let delay = 3000;
    const poll = async () => {
        if (!document.hidden) {
            try {
                if (await hasFinishedAnalysis(elements, window.fetch.bind(window))) {
                    window.location.reload();
                    return;
                }
                delay = 3000;
            } catch {
                delay = Math.min(delay * 2, 30000);
            }
        }
        window.setTimeout(poll, delay);
    };
    window.setTimeout(poll, delay);
}
