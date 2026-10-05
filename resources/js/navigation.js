export function initializeNavigation() {
    const sidebar = document.querySelector('.rd-sidebar');
    const toggle = document.querySelector('[data-navigation-toggle]');
    const setOpen = (open) => {
        sidebar?.toggleAttribute('data-navigation-open', open);
        toggle?.setAttribute('aria-expanded', String(open));
    };
    toggle?.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar?.hasAttribute('data-navigation-open')) {
            setOpen(false);
            toggle?.focus();
        }
    });

    // Optional session storage keeps list position without blocking navigation when storage is unavailable.
    const save = (key, value) => {
        try { window.sessionStorage.setItem(key, value); } catch { /* Navigation still works without storage. */ }
    };
    const read = (key) => {
        try { return window.sessionStorage.getItem(key); } catch { return null; }
    };
    const scrollKey = () => `ridedock:scroll:${window.location.pathname}${window.location.search}`;
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const url = new window.URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) return;
        if (/^\/activities\/[^/]+$/.test(url.pathname)) {
            const source = window.location.pathname + window.location.search + window.location.hash;
            if (['/', '/activities', '/coach', '/insights'].includes(window.location.pathname)) {
                url.searchParams.set('return_to', source);
                link.href = url.href;
                save(scrollKey(), String(window.scrollY));
            }
        }
        if (link.hasAttribute('data-context-back')) {
            save('ridedock:restore', url.pathname + url.search);
        }
    });
    window.addEventListener('pagehide', () => save(scrollKey(), String(window.scrollY)));
    const restore = () => {
        const path = window.location.pathname + window.location.search;
        if (read('ridedock:restore') !== path) return;
        const y = Number(read(scrollKey()));
        save('ridedock:restore', '');
        if (Number.isFinite(y)) window.requestAnimationFrame(() => window.scrollTo(0, y));
    };
    if (document.readyState === 'complete') restore();
    else window.addEventListener('load', restore, { once: true });
}
