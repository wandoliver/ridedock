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
        group.querySelectorAll('[data-select]').forEach((button) => {
            button.addEventListener('click', () => {
                if (!selectDetail(group, button.dataset.select)) return;
                const url = new window.URL(window.location.href);
                url.searchParams.set(group.dataset.selectionGroup, button.dataset.select);
                window.history.replaceState(null, '', url);
            });
        });
    });
}
