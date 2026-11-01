/* Home "Built in the open": tab switching for the repo panels and an optional bar-grow.
   The section is complete without this file; with it, only one repo panel shows at a time. */
(function () {
    'use strict';
    var root = document.querySelector('[data-home-open]');
    if (!root) return;
    var list = root.querySelector('[role="tablist"]');
    var tabs = list ? Array.prototype.slice.call(list.querySelectorAll('[role="tab"]')) : [];
    var panels = Array.prototype.slice.call(root.querySelectorAll('[role="tabpanel"]'));
    var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var seen = false;

    function pop(panel) {
        if (calm || !panel) return;
        panel.classList.remove('ho-pop');
        void panel.offsetWidth;
        panel.classList.add('ho-pop');
    }

    function select(index, focus) {
        tabs.forEach(function (tab, i) {
            var on = i === index;
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
            tab.tabIndex = on ? 0 : -1;
            if (panels[i]) panels[i].hidden = !on;
        });
        if (focus) tabs[index].focus();
        if (root.hasAttribute('data-motion')) pop(panels[index]);
    }

    if (tabs.length && tabs.length === panels.length) {
        root.classList.add('ho-js');
        list.hidden = false;
        var current = Math.max(0, tabs.findIndex(function (t) { return t.getAttribute('aria-selected') === 'true'; }));
        select(current, false);
        tabs.forEach(function (tab, i) {
            tab.addEventListener('click', function () { select(i, false); });
            tab.addEventListener('keydown', function (e) {
                var rtl = getComputedStyle(list).direction === 'rtl';
                var next = null;
                if (e.key === 'ArrowRight') next = rtl ? i - 1 : i + 1;
                else if (e.key === 'ArrowLeft') next = rtl ? i + 1 : i - 1;
                else if (e.key === 'Home') next = 0;
                else if (e.key === 'End') next = tabs.length - 1;
                if (next === null) return;
                e.preventDefault();
                select((next + tabs.length) % tabs.length, true);
            });
        });
    }

    // The bars grow once, when the section scrolls into view. Skipped for reduced motion;
    // without IntersectionObserver the bars simply stay as drawn.
    if (!calm && 'IntersectionObserver' in window) {
        root.setAttribute('data-motion', '');
        var visible = function () {
            if (seen) return;
            seen = true;
            pop(root.querySelector('[role="tabpanel"]:not([hidden])'));
        };
        new IntersectionObserver(function (entries, observer) {
            if (entries.some(function (e) { return e.isIntersecting; })) { visible(); observer.disconnect(); }
        }, { threshold: 0.15 }).observe(root);
        setTimeout(visible, 6000);
    }
})();
