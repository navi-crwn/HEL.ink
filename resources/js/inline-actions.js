// Delegated replacements for inline on* attributes, so pages work under a CSP
// without 'unsafe-inline' for event handlers (script-src-attr 'none').
//
//   data-confirm="Question?"         on a form (submit) or a button/link (click); asked through
//                                    ./dialogs.js (the shared HOP dialog, or the browser's own).
//                                    Optional: data-confirm-title, data-confirm-ok, data-danger
//                                    (a delete: red button) and data-subject (what is deleted)
//   data-autosubmit                 on a form control: submit its form on change
//   data-nav="?period={value}"       on a select: go to the URL with its value
//   data-call="fnName"               on a clickable: call window.fnName(...data-args)
//   data-args='["a", 1]'             JSON array of arguments for data-call
//   data-show / data-hide="#id"      on a clickable: show or hide an element
//   data-hide-self-on-backdrop       on an overlay: hide it when its backdrop is clicked
//   data-toggle-class="name"         on a clickable: toggle a class on itself
//   data-copy-from="#id"             on a button: copy that input's value
//   data-toggle-dark                 on a button: toggle dark mode and remember it
//   data-prompt-password="Question"  on a button in a form: ask for the password (one dialog, with the
//                                    form's data-confirm as its text), then submit
//   data-reload                      on a button: reload the page
//   data-fallback="hide"             on an img: hide it when it fails to load
//   data-fallback-src="url"          on an img: swap to this image when it fails to load

import { confirmDialog, confirmOptions, promptDialog } from './dialogs';

// A dialog answers later, so the first submit or click is stopped and, on "yes", repeated with
// this mark set; the repeat passes through once.
const CONFIRMED = 'confirmed';

function handleImageError(img) {
    if (!(img instanceof HTMLImageElement) || img.dataset.fallbackDone) return;
    if (img.dataset.fallbackSrc) {
        img.dataset.fallbackDone = '1';
        img.src = img.dataset.fallbackSrc;
    } else if (img.dataset.fallback === 'hide') {
        img.style.display = 'none';
    }
}

function copyText(button, text) {
    const original = button.textContent;
    navigator.clipboard?.writeText(text).then(() => {
        button.textContent = button.dataset.copiedText || 'Copied!';
        setTimeout(() => (button.textContent = original), 2000);
    });
}

export default function installInlineActions(root = document) {
    // Image errors do not bubble, but they can be caught on the way down.
    root.addEventListener('error', (e) => handleImageError(e.target), true);
    // Images that failed before this script loaded.
    root.querySelectorAll('img[data-fallback], img[data-fallback-src]').forEach((img) => {
        if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) handleImageError(img);
    });

    root.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
        if (form.dataset[CONFIRMED]) {
            delete form.dataset[CONFIRMED];
            return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        const submitter = e.submitter instanceof HTMLElement && e.submitter.form === form ? e.submitter : null;
        confirmDialog(confirmOptions(form, submitter || form)).then((yes) => {
            if (!yes) return;
            form.dataset[CONFIRMED] = '1';
            // With the button that was pressed, so its name/value and formaction still count.
            if (typeof form.requestSubmit === 'function') form.requestSubmit(submitter && submitter.type === 'submit' ? submitter : undefined);
            else form.submit();
        });
    }, true);

    root.addEventListener('change', (e) => {
        const el = e.target;
        if (!(el instanceof HTMLElement)) return;
        if (el.hasAttribute('data-autosubmit') && el.form) {
            el.form.submit();
        } else if (el.dataset.nav) {
            window.location.href = el.dataset.nav.replace('{value}', encodeURIComponent(el.value));
        }
    });

    root.addEventListener('click', (e) => {
        if (!(e.target instanceof Element)) return;

        const backdrop = e.target.closest('[data-hide-self-on-backdrop]');
        if (backdrop && e.target === backdrop) {
            backdrop.classList.add('hidden');
            return;
        }

        const confirmEl = e.target.closest('[data-confirm]:not(form)');
        if (confirmEl instanceof HTMLElement) {
            if (confirmEl.dataset[CONFIRMED]) {
                delete confirmEl.dataset[CONFIRMED];
            } else {
                e.preventDefault();
                e.stopImmediatePropagation();
                confirmDialog(confirmOptions(confirmEl)).then((yes) => {
                    if (!yes) return;
                    confirmEl.dataset[CONFIRMED] = '1';
                    confirmEl.click();
                });

                return;
            }
        }

        const el = e.target.closest('[data-call], [data-show], [data-hide], [data-toggle-class], [data-copy-from], [data-toggle-dark], [data-prompt-password], [data-reload]');
        if (!el) return;

        if (el.hasAttribute('data-reload')) window.location.reload();
        if (el.dataset.promptPassword && el.form) {
            const form = el.form;
            const field = form.querySelector('input[name="password"]');
            // One dialog: the form's question as the text, the password as the answer. Typing the
            // password is the confirmation, so the submit that follows is not asked a second time.
            promptDialog({
                title: el.dataset.promptTitle || el.textContent.trim(),
                message: form.dataset.confirm || '',
                label: el.dataset.promptPassword,
                type: 'password',
                anchor: el,
            }).then((password) => {
                if (!password || !field) return;
                field.value = password;
                if (form.dataset.confirm) form.dataset[CONFIRMED] = '1';
                form.requestSubmit();
            });
        }

        if (el.dataset.show) document.querySelector(el.dataset.show)?.classList.remove('hidden');
        if (el.dataset.hide) document.querySelector(el.dataset.hide)?.classList.add('hidden');
        if (el.dataset.toggleClass) el.classList.toggle(el.dataset.toggleClass);
        if (el.dataset.copyFrom) copyText(el, document.querySelector(el.dataset.copyFrom)?.value ?? '');
        if (el.hasAttribute('data-toggle-dark')) {
            const dark = document.documentElement.classList.toggle('dark');
            try { localStorage.setItem('helink-theme', dark ? 'dark' : 'light'); } catch (err) { /* storage blocked */ }
        }
        if (el.dataset.call) {
            const fn = window[el.dataset.call];
            if (typeof fn === 'function') {
                let args = [];
                try { args = el.dataset.args ? JSON.parse(el.dataset.args) : []; } catch (err) { args = []; }
                fn.apply(el, args);
            } else if (el.dataset.fallbackHref) {
                window.location.href = el.dataset.fallbackHref;
            }
        }
    });
}
