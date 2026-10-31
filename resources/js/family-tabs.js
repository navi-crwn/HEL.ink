// HOP home page: the app tabs (WAI-ARIA tabs pattern). Arrow keys, Home and End move
// between tabs; the hash (#linkhop, #biohop, …) opens a tab and stays shareable.
export default function installFamilyTabs() {
    const list = document.querySelector('[data-family-tabs]');
    if (!list) return;
    const tabs = Array.from(list.querySelectorAll('[role="tab"]'));
    const select = (tab, focus = false, push = false) => {
        tabs.forEach((t) => {
            const on = t === tab;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.tabIndex = on ? 0 : -1;
            document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
        });
        if (focus) tab.focus();
        if (push) history.replaceState(null, '', '#' + tab.dataset.app);
    };
    tabs.forEach((tab, i) => {
        tab.addEventListener('click', () => select(tab, false, true));
        tab.addEventListener('keydown', (e) => {
            const rtl = document.documentElement.dir === 'rtl';
            const step = { ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1 }[e.key];
            let next = null;
            if (step) next = tabs[(i + step + tabs.length) % tabs.length];
            if (e.key === 'Home') next = tabs[0];
            if (e.key === 'End') next = tabs[tabs.length - 1];
            if (next) { e.preventDefault(); select(next, true, true); }
        });
    });
    const fromHash = () => {
        const tab = tabs.find((t) => '#' + t.dataset.app === location.hash);
        if (tab) { select(tab); document.getElementById('apps').scrollIntoView(); }
    };
    window.addEventListener('hashchange', fromHash);
    fromHash();
}
