// The hub's questions and messages: the shared HOP dialog when it is on the page (window.HopUI,
// served from hel.ink/hop-assets from v5 on), the browser's own alert/confirm/prompt otherwise.
// Every call returns a promise, so a caller is written once and works with either.
//
//   confirmDialog({ title, message, ok, danger, subject, anchor }) → Promise<boolean>
//   alertDialog({ title, message })                                → Promise<void>
//   promptDialog({ title, message, label, type, anchor })          → Promise<string|null>
//
// `danger` gives a red button, `subject` names what goes ("Delete “Work links”"), `anchor` is the
// control the dialog opens from. The browser's own dialogs ignore all three and show the message.

const scope = () => (typeof window !== 'undefined' ? window : globalThis);

function shared() {
    const ui = scope().HopUI;

    return ui && typeof ui.confirm === 'function' && typeof ui.alert === 'function' && typeof ui.prompt === 'function' ? ui : null;
}

const options = (o) => (typeof o === 'string' ? { message: o } : { ...(o || {}) });

// The browser's dialogs have one text: the message, or the title when that is all there is.
const plain = (o, ...more) => [o.message || o.title || '', ...more].filter(Boolean).join('\n\n');

export function confirmDialog(o) {
    o = options(o);
    const ui = shared();

    return ui ? ui.confirm(o) : Promise.resolve(Boolean(scope().confirm(plain(o))));
}

export function alertDialog(o) {
    o = options(o);
    const ui = shared();
    if (ui) return ui.alert(o);
    scope().alert(plain(o));

    return Promise.resolve();
}

export function promptDialog(o) {
    o = options(o);
    const ui = shared();
    if (ui) return ui.prompt(o);
    const value = scope().prompt(plain(o, o.label));

    return Promise.resolve(value === null || value === '' ? null : value);
}

// What a `data-confirm` element asks: its text, and optionally data-confirm-title, data-confirm-ok,
// data-danger (a delete) and data-subject (the name of what is deleted).
export function confirmOptions(el, anchor) {
    const d = el.dataset;

    return {
        title: d.confirmTitle || undefined,
        message: d.confirm,
        ok: d.confirmOk || undefined,
        danger: d.danger !== undefined,
        subject: d.subject || undefined,
        anchor: anchor || el,
    };
}

export default function installDialogs() {
    // For the few inline page scripts (Settings → API tokens).
    scope().HopDialogs = { confirm: confirmDialog, alert: alertDialog, prompt: promptDialog };
}
