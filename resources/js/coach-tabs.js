const activeClasses = ['bg-slate-950', 'text-white', 'shadow-sm'];
const inactiveClasses = ['text-slate-600', 'hover:bg-slate-100', 'hover:text-slate-950'];

export function activateCoachSection(section, tabs, sections) {
    const availableSections = new Set(Array.from(tabs, (tab) => tab.dataset.coachTab));
    const activeSection = availableSections.has(section) ? section : 'goal-plan';

    tabs.forEach((tab) => {
        const active = tab.dataset.coachTab === activeSection;

        activeClasses.forEach((className) => tab.classList.toggle(className, active));
        inactiveClasses.forEach((className) => tab.classList.toggle(className, !active));

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
            window.history.replaceState(null, '', `#${section}`);
        });
    });

    window.addEventListener('hashchange', activateFromHash);
    activateFromHash();
}
