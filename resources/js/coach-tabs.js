export function activateCoachSection(section, tabs, sections) {
    const aliases = { roadmap: 'goal-plan', block: 'training-block', week: 'weekly-plan' };
    section = aliases[section] ?? section;
    const availableSections = new Set(Array.from(tabs, (tab) => tab.dataset.coachTab));
    const activeSection = availableSections.has(section) ? section : (availableSections.has('weekly-plan') ? 'weekly-plan' : 'goal-plan');

    tabs.forEach((tab) => {
        const active = tab.dataset.coachTab === activeSection;

        if (active) {
            tab.setAttribute('aria-current', 'page');
        } else {
            tab.removeAttribute('aria-current');
        }
    });

    sections.forEach((panel) => {
        panel.hidden = panel.dataset.coachSection !== activeSection;
    });

    return activeSection;
}

export function initializeCoachTabs() {
    const navigation = document.querySelector('[data-coach-tabs]');

    if (!navigation) {
        return;
    }

    const tabs = Array.from(navigation.querySelectorAll('[data-coach-tab]'));
    const sections = Array.from(document.querySelectorAll('[data-coach-section]'));

    if (tabs.length === 0 || sections.length === 0) {
        return;
    }

    const activateFromHash = () => activateCoachSection(window.location.hash.slice(1), tabs, sections);

    tabs.forEach((tab) => {
        tab.addEventListener('click', (event) => {
            event.preventDefault();
            const section = activateCoachSection(tab.dataset.coachTab, tabs, sections);
            window.history.pushState(null, '', `#${({ 'goal-plan': 'roadmap', 'training-block': 'block', 'weekly-plan': 'week' })[section] ?? section}`);
        });
    });

    window.addEventListener('hashchange', activateFromHash);
    window.addEventListener('popstate', activateFromHash);
    activateFromHash();
}
