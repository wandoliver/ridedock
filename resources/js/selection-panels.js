export function selectDetail(group, value) {
    const panels = Array.from(group.querySelectorAll('[data-detail]'));
    if (!panels.some((panel) => panel.dataset.detail === value)) return false;
    panels.forEach((panel) => { panel.hidden = panel.dataset.detail !== value; });
    group.querySelectorAll('[data-select]').forEach((button) => {
        button.setAttribute('aria-pressed', String(button.dataset.select === value));
    });
    return true;
}

export function initializeSelectionPanels() {
    document.querySelectorAll('[data-selection-group]').forEach((group) => {
        const initial = group.querySelector('[data-select][aria-pressed="true"]')?.dataset.select ?? 'overview';
        const restore = () => {
            const url = new window.URL(window.location.href);
            selectDetail(group, url.searchParams.get(group.dataset.selectionGroup) ?? initial);
        };
        group.querySelectorAll('[data-select]').forEach((button) => {
            button.addEventListener('click', () => {
                if (!selectDetail(group, button.dataset.select)) return;
                const url = new window.URL(window.location.href);
                url.searchParams.set(group.dataset.selectionGroup, button.dataset.select);
                if (url.href !== window.location.href) window.history.pushState(null, '', url);
                if (window.matchMedia('(max-width: 1199px)').matches) {
                    const panel = Array.from(group.querySelectorAll('[data-detail]')).find((item) => item.dataset.detail === button.dataset.select);
                    const target = button.dataset.select === 'overview' ? group.querySelector('.rd-agenda') ?? panel : panel;
                    target?.setAttribute('tabindex', '-1');
                    target?.focus({ preventScroll: true });
                    target?.scrollIntoView({ block: 'start', behavior: 'instant' });
                }
            });
        });
        window.addEventListener('popstate', restore);
        restore();
    });
}
