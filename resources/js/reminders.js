// Reminder cards ([data-hop-remind="key"]) get their "Hide" button from the shared hop-ui.js
// (hel.ink/hop-assets, v5 and later) when it is on the page. When it is not, the hub adds a plain
// one itself, with the same contract: the card fires "hop:remind-dismiss" (cancelable), {key} is
// posted to data-hop-remind-url with the CSRF token, the card is hidden and fires
// "hop:remind-hidden". The server keeps the card away for seven days.
//
// The words come from the card: data-hop-remind-hide ("Hide") and data-hop-remind-again
// ("Shows again next week"), already in the page's language.

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || null;
}

function addHideButton(card) {
    if (card.dataset.remindReady) return;
    card.dataset.remindReady = '1';
    const hide = card.dataset.hopRemindHide || 'Hide';
    const again = card.dataset.hopRemindAgain || 'Shows again next week';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'remind-hide';
    button.title = `${hide}. ${again}`;
    button.setAttribute('aria-label', `${hide}. ${again}`);
    const x = document.createElement('span');
    x.setAttribute('aria-hidden', 'true');
    x.textContent = '×';
    button.appendChild(x);
    card.classList.add('remind-card');
    card.insertBefore(button, card.firstChild);
    button.addEventListener('click', () => {
        const key = card.dataset.hopRemind;
        const event = new CustomEvent('hop:remind-dismiss', { bubbles: true, cancelable: true, detail: { key } });
        if (!card.dispatchEvent(event)) return;
        button.disabled = true;
        card.hidden = true;
        card.dispatchEvent(new CustomEvent('hop:remind-hidden', { bubbles: true }));
        const url = card.dataset.hopRemindUrl;
        if (!url) return;
        const headers = { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        const token = card.dataset.hopRemindCsrf || csrf();
        if (token) headers['X-CSRF-TOKEN'] = token;
        fetch(url, { method: 'POST', credentials: 'same-origin', headers, body: JSON.stringify({ key }) }).catch(() => {});
    });
}

export default function installReminders(root = document) {
    // hop-ui.js is a deferred script placed before this module, so it has run by now if it loaded at all.
    if (window.HopUI && typeof window.HopUI.remind === 'function') return;
    root.querySelectorAll('[data-hop-remind]').forEach(addHideButton);
}
