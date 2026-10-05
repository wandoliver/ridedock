export function activatePageTab(value, tabs, panels, fallback) {
    const names = new Set(tabs.map((tab) => tab.dataset.pageTab));
    const active = names.has(value) ? value : fallback;
    tabs.forEach((tab) => {
        if (tab.dataset.pageTab === active) tab.setAttribute('aria-current', 'page');
        else tab.removeAttribute('aria-current');
    });
    panels.forEach((panel) => { panel.hidden = panel.dataset.pagePanel !== active; });
    return active;
}

export function initializePageTabs() {
    const navigation = document.querySelector('[data-page-tabs]');
    if (!navigation) return;
    const tabs = Array.from(navigation.querySelectorAll('[data-page-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-page-panel]'));
    const activate = () => {
        const hash = window.location.hash.slice(1);
        if (hash === 'page-content') return;
        const target = document.getElementById(hash);
        const value = hash === 'ai-analysis-heading' ? 'coach' : (target?.closest('[data-page-panel]')?.dataset.pagePanel ?? hash);
        const active = activatePageTab(value, tabs, panels, navigation.dataset.defaultTab);
        document.querySelectorAll('[data-page-section]').forEach((input) => { input.value = active; });
        document.querySelectorAll('[data-page-actions]').forEach((actions) => {
            actions.hidden = !actions.dataset.pageActions.split(' ').includes(active);
        });
        window.dispatchEvent(new window.Event('rd:view-change'));
    };
    tabs.forEach((tab) => tab.addEventListener('click', (event) => {
        event.preventDefault();
        if (window.location.hash !== tab.getAttribute('href')) window.history.pushState(null, '', tab.getAttribute('href'));
        activate();
    }));
    window.addEventListener('hashchange', activate);
    window.addEventListener('popstate', activate);
    let invalidField = null;
    let reporting = false;
    document.addEventListener('invalid', (event) => {
        if (reporting || !event.target.closest('[data-page-panel]')) return;
        event.preventDefault();
        if (invalidField) return;
        invalidField = event.target;
        window.requestAnimationFrame(() => {
            const panel = invalidField.closest('[data-page-panel]');
            window.history.replaceState(null, '', `#${panel.dataset.pagePanel}`);
            activate();
            invalidField.focus();
            reporting = true;
            invalidField.reportValidity();
            reporting = false;
            invalidField = null;
        });
    }, true);
    if (navigation.hasAttribute('data-tab-errors')) {
        window.history.replaceState(null, '', `#${navigation.dataset.defaultTab}`);
    }
    activate();
}
