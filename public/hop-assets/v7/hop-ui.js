/*
 * HOP dialogs and reminder cards, one copy for every HOP app (served from hel.ink/hop-assets).
 *
 *   <link rel="stylesheet" href="https://hel.ink/hop-assets/v5/hop-ui.css" crossorigin>
 *   <script src="https://hel.ink/hop-assets/v5/hop-ui.js" crossorigin defer></script>
 *
 *   HopUI.alert({ title, message })                               → Promise<void>
 *   HopUI.confirm({ title, message, ok, cancel, danger, subject }) → Promise<boolean>
 *   HopUI.prompt({ title, message, label, value, type, validate }) → Promise<string|null>
 *
 * Replaces window.alert/confirm/prompt in the apps: one look, keyboard first (Escape cancels,
 * Enter confirms, focus stays inside and returns to the trigger), nine languages picked from
 * <html lang>, a red button for a delete that names what goes ("Delete “Work links”"), and no
 * motion under prefers-reduced-motion. Uses <dialog> and CSSOM only (no inline style attributes,
 * no eval), so it runs under a strict CSP.
 *
 * Reminder cards: any element with data-hop-remind="<key>" gets a "Hide" button. Hiding posts
 * {key} to the URL in data-hop-remind-url (same origin, credentials included, X-CSRF-TOKEN from
 * <meta name="csrf-token"> when present) so the app stores dismissed_at per account and shows the
 * card again after seven days unless the task is done. The text "Shows again next week" is
 * part of the button, so nothing has to be written per app.
 *
 *   <section data-hop-remind="setup-2fa" data-hop-remind-url="/reminders/dismiss">…</section>
 *
 * Events: the card fires "hop:remind-dismiss" (cancelable) before the request, and
 * "hop:remind-hidden" after it is gone. HopUI.lang("de") switches the language at run time.
 */
(function () {
  'use strict';

  var STRINGS = {
    en: { ok: 'OK', cancel: 'Cancel', close: 'Close', confirm: 'Confirm', remove: 'Delete', remove_subject: 'Delete “{name}”', hide: 'Hide', again: 'Shows again next week', required: 'This field is required' },
    id: { ok: 'OK', cancel: 'Batal', close: 'Tutup', confirm: 'Konfirmasi', remove: 'Hapus', remove_subject: 'Hapus “{name}”', hide: 'Sembunyikan', again: 'Muncul lagi minggu depan', required: 'Kolom ini wajib diisi' },
    de: { ok: 'OK', cancel: 'Abbrechen', close: 'Schließen', confirm: 'Bestätigen', remove: 'Löschen', remove_subject: '„{name}“ löschen', hide: 'Ausblenden', again: 'Erscheint nächste Woche wieder', required: 'Dieses Feld ist erforderlich' },
    es: { ok: 'Aceptar', cancel: 'Cancelar', close: 'Cerrar', confirm: 'Confirmar', remove: 'Eliminar', remove_subject: 'Eliminar «{name}»', hide: 'Ocultar', again: 'Vuelve a aparecer la próxima semana', required: 'Este campo es obligatorio' },
    fr: { ok: 'OK', cancel: 'Annuler', close: 'Fermer', confirm: 'Confirmer', remove: 'Supprimer', remove_subject: 'Supprimer « {name} »', hide: 'Masquer', again: 'Réapparaît la semaine prochaine', required: 'Ce champ est obligatoire' },
    ja: { ok: 'OK', cancel: 'キャンセル', close: '閉じる', confirm: '確認', remove: '削除', remove_subject: '「{name}」を削除', hide: '非表示', again: '来週また表示されます', required: 'この項目は必須です' },
    'pt-BR': { ok: 'OK', cancel: 'Cancelar', close: 'Fechar', confirm: 'Confirmar', remove: 'Excluir', remove_subject: 'Excluir “{name}”', hide: 'Ocultar', again: 'Aparece de novo na próxima semana', required: 'Este campo é obrigatório' },
    ru: { ok: 'ОК', cancel: 'Отмена', close: 'Закрыть', confirm: 'Подтвердить', remove: 'Удалить', remove_subject: 'Удалить «{name}»', hide: 'Скрыть', again: 'Появится снова через неделю', required: 'Это поле обязательно' },
    'zh-CN': { ok: '确定', cancel: '取消', close: '关闭', confirm: '确认', remove: '删除', remove_subject: '删除“{name}”', hide: '隐藏', again: '下周再次显示', required: '此项为必填' }
  };

  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var lang = null;

  function pick(code) {
    code = String(code || '').trim();
    if (STRINGS[code]) return code;
    var short = code.split(/[-_]/)[0].toLowerCase();
    if (short === 'zh') return 'zh-CN';
    if (short === 'pt') return 'pt-BR';
    return STRINGS[short] ? short : 'en';
  }
  function current() { return lang || pick(document.documentElement.lang); }
  function t(key, vars) {
    var s = (STRINGS[current()] || STRINGS.en)[key] || STRINGS.en[key] || key;
    return s.replace(/\{(\w+)\}/g, function (m, k) { return vars && k in vars ? String(vars[k]) : m; });
  }

  // Dark when the page says so (.dark, data-theme), otherwise when the page background is dark:
  // LinkHop and BioHop toggle a class, PixelHop and FileHop set data-theme or follow the system,
  // and the dialog must match whichever the page is showing right now.
  function isDark(near) {
    var root = document.documentElement, body = document.body;
    var theme = root.getAttribute('data-theme') || (body && body.getAttribute('data-theme'));
    if (theme === 'dark' || root.classList.contains('dark') || (body && body.classList.contains('dark'))) return true;
    if (theme === 'light' || root.classList.contains('light')) return false;
    var node = near || body;
    while (node && node !== root.parentNode) {
      var bg = getComputedStyle(node).backgroundColor || '';
      var m = /rgba?\(\s*(\d+)[ ,]+(\d+)[ ,]+(\d+)(?:[ ,/]+([\d.]+))?/.exec(bg);
      if (m && (m[4] === undefined || parseFloat(m[4]) > 0.5)) {
        return (0.2126 * m[1] + 0.7152 * m[2] + 0.0722 * m[3]) / 255 < 0.5;
      }
      node = node.parentElement;
    }
    return !!(window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
  }

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }
  var seq = 0;

  // ---- dialogs -------------------------------------------------------------------------------

  function open(kind, o) {
    o = o || {};
    return new Promise(function (resolve) {
      var trigger = document.activeElement;
      var id = 'hop-dlg-' + (++seq);
      var dlg = el('dialog', 'hop-dialog hop-dialog--' + kind + (isDark() ? ' hop-ui--dark' : ''));
      dlg.setAttribute('aria-labelledby', id + '-t');
      if (o.message) dlg.setAttribute('aria-describedby', id + '-d');
      if (o.anchor && o.anchor.getBoundingClientRect) {
        // Enters from and exits to the control that opened it.
        var r = o.anchor.getBoundingClientRect();
        dlg.style.setProperty('--hop-ox', (r.left + r.width / 2) + 'px');
        dlg.style.setProperty('--hop-oy', (r.top + r.height / 2) + 'px');
        dlg.classList.add('hop-dialog--anchored');
      }

      var box = el('div', 'hop-dialog__box');
      var body = el('div', 'hop-dialog__body');
      var title = el('h2', 'hop-dialog__title', o.title || (kind === 'alert' ? '' : t(o.danger ? 'remove' : 'confirm')));
      title.id = id + '-t';
      if (!title.textContent) title.hidden = true;
      body.appendChild(title);
      if (o.message) {
        var msg = el('p', 'hop-dialog__msg', o.message);
        msg.id = id + '-d';
        body.appendChild(msg);
      }
      var input = null, err = null;
      var form = el('form', 'hop-dialog__form');
      form.setAttribute('method', 'dialog');
      if (kind === 'prompt') {
        var field = el('label', 'hop-dialog__field');
        var lab = el('span', 'hop-dialog__label', o.label || '');
        if (!o.label) lab.hidden = true;
        input = el('input', 'hop-dialog__input');
        input.type = o.type === 'password' ? 'password' : 'text';
        input.value = o.value || '';
        if (o.placeholder) input.placeholder = o.placeholder;
        input.autocomplete = o.type === 'password' ? 'current-password' : 'off';
        input.spellcheck = false;
        if (o.maxlength) input.maxLength = o.maxlength;
        field.appendChild(lab);
        field.appendChild(input);
        err = el('p', 'hop-dialog__error');
        err.setAttribute('role', 'alert');
        err.hidden = true;
        field.appendChild(err);
        form.appendChild(field);
      }
      var actions = el('div', 'hop-dialog__actions');
      var cancel = null;
      if (kind !== 'alert') {
        cancel = el('button', 'hop-btn hop-btn--ghost', o.cancel || t('cancel'));
        cancel.type = 'button';
        actions.appendChild(cancel);
      }
      // A long name is shortened in the middle, so the button still names the thing and still fits.
      var subject = o.subject ? String(o.subject) : '';
      if (subject.length > 40) subject = subject.slice(0, 22).trim() + '…' + subject.slice(-14).trim();
      var okLabel = o.ok || (kind === 'alert' ? t('ok') : o.danger ? (subject ? t('remove_subject', { name: subject }) : t('remove')) : t('ok'));
      var ok = el('button', 'hop-btn ' + (o.danger ? 'hop-btn--danger' : 'hop-btn--primary'), okLabel);
      ok.type = 'submit';
      actions.appendChild(ok);
      form.appendChild(actions);
      body.appendChild(form);
      box.appendChild(body);
      dlg.appendChild(box);
      document.body.appendChild(dlg);

      var settled = false;
      function finish(value) {
        if (settled) return;
        settled = true;
        dlg.classList.add('is-closing');
        var done = function () {
          if (dlg.open) dlg.close();
          dlg.remove();
          if (trigger && trigger.focus && document.contains(trigger)) trigger.focus();
          resolve(value);
        };
        if (reduce) done();
        else {
          var ended = false;
          dlg.addEventListener('animationend', function () { if (!ended) { ended = true; done(); } }, { once: true });
          setTimeout(function () { if (!ended) { ended = true; done(); } }, 200);
        }
      }
      var cancelValue = kind === 'confirm' ? false : kind === 'prompt' ? null : undefined;
      dlg.addEventListener('cancel', function (e) { e.preventDefault(); finish(cancelValue); });
      dlg.addEventListener('click', function (e) { if (e.target === dlg) finish(cancelValue); });
      if (cancel) cancel.addEventListener('click', function () { finish(cancelValue); });
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (kind === 'prompt') {
          var v = input.value;
          var problem = o.required !== false && v.trim() === '' ? t('required') : (o.validate ? o.validate(v) : null);
          if (problem) {
            err.textContent = problem;
            err.hidden = false;
            input.setAttribute('aria-invalid', 'true');
            input.focus();
            return;
          }
          finish(v);
        } else finish(kind === 'confirm' ? true : undefined);
      });
      if (input) input.addEventListener('input', function () { err.hidden = true; input.removeAttribute('aria-invalid'); });

      if (typeof dlg.showModal === 'function') dlg.showModal();
      else { dlg.setAttribute('open', ''); dlg.classList.add('hop-dialog--fallback'); }
      // A destructive question starts on Cancel, so Enter does not delete by habit.
      (input || (o.danger && cancel) || ok).focus();
      if (input && input.value) input.select();
    });
  }

  // ---- reminder cards -----------------------------------------------------------------------

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : null;
  }

  function hideCard(card) {
    card.classList.add('is-hiding');
    var done = function () {
      card.hidden = true;
      card.dispatchEvent(new CustomEvent('hop:remind-hidden', { bubbles: true }));
    };
    if (reduce) done();
    else {
      card.style.height = card.getBoundingClientRect().height + 'px';
      requestAnimationFrame(function () { requestAnimationFrame(function () { card.style.height = '0px'; }); });
      card.addEventListener('transitionend', done, { once: true });
      setTimeout(done, 300);
    }
  }

  function remind(card) {
    if (card.__hopRemind) return;
    card.__hopRemind = true;
    card.classList.add('hop-remind');
    if (isDark(card)) card.classList.add('hop-ui--dark');
    var key = card.getAttribute('data-hop-remind');
    var btn = el('button', 'hop-remind__hide');
    btn.type = 'button';
    btn.appendChild(el('span', 'hop-remind__x', '×'));
    var txt = el('span', 'hop-remind__txt');
    txt.appendChild(el('strong', null, t('hide')));
    txt.appendChild(el('small', null, t('again')));
    btn.appendChild(txt);
    btn.setAttribute('aria-label', t('hide') + '. ' + t('again'));
    card.insertBefore(btn, card.firstChild);
    btn.addEventListener('click', function () {
      var ev = new CustomEvent('hop:remind-dismiss', { bubbles: true, cancelable: true, detail: { key: key } });
      if (!card.dispatchEvent(ev)) return;
      btn.disabled = true;
      var url = card.getAttribute('data-hop-remind-url');
      hideCard(card);
      if (!url) return;
      var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
      var token = card.getAttribute('data-hop-remind-csrf') || csrf();
      if (token) headers['X-CSRF-TOKEN'] = token;
      fetch(url, { method: 'POST', credentials: 'same-origin', headers: headers, body: JSON.stringify({ key: key }) }).catch(function () {});
    });
  }

  function scan(root) {
    var list = (root || document).querySelectorAll('[data-hop-remind]');
    for (var i = 0; i < list.length; i++) remind(list[i]);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { scan(); });
  else scan();
  if (window.MutationObserver) {
    new MutationObserver(function (muts) {
      for (var i = 0; i < muts.length; i++) {
        var added = muts[i].addedNodes;
        for (var j = 0; j < added.length; j++) if (added[j].nodeType === 1) scan(added[j]);
      }
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  window.HopUI = {
    alert: function (o) { return open('alert', typeof o === 'string' ? { message: o } : o); },
    confirm: function (o) { return open('confirm', typeof o === 'string' ? { message: o } : o); },
    prompt: function (o) { return open('prompt', typeof o === 'string' ? { message: o } : o); },
    remind: scan,
    lang: function (code) { lang = code ? pick(code) : null; return current(); },
    t: t
  };
})();
