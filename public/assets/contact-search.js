// Autocompletamento contatti: <input data-contact-search="any|registrant" [data-append="idTextarea"]>
// Cerca nella cache locale (ID, nome, organizzazione, email) e inserisce l'ID scelto.
(function () {
  const cfg = window.CONTACT_SEARCH || {};
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

  function attach(input) {
    const mode = input.dataset.contactSearch;
    const wrap = document.createElement('div');
    wrap.className = 'ac-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    const box = document.createElement('div');
    box.className = 'ac-box';
    box.hidden = true;
    wrap.appendChild(box);
    input.setAttribute('autocomplete', 'off');

    let items = [], sel = -1, timer = null, seq = 0;

    function render(q) {
      if (!items.length) {
        box.innerHTML = '<div class="ac-empty">Nessun contatto trovato per «' + esc(q) + '» tra quelli salvati.' +
          (cfg.createUrl ? ' <a href="' + esc(cfg.createUrl) + '" target="_blank">Crea contatto</a>' : '') +
          (cfg.importUrl ? ' · <a href="' + esc(cfg.importUrl) + '" target="_blank">Importa dal Registro</a>' : '') + '</div>';
      } else {
        box.innerHTML = items.map((c, i) =>
          '<div class="ac-item' + (i === sel ? ' on' : '') + '" data-i="' + i + '">' +
          '<span class="mono">' + esc(c.id) + '</span> <strong>' + esc(c.name) + '</strong>' +
          (c.org && c.org !== c.name ? ' <span class="mute">' + esc(c.org) + '</span>' : '') +
          (c.entityType === 1 ? ' <span class="badge">persona fisica</span>' : (c.registrant ? ' <span class="badge client">registrante</span>' : '')) +
          '<div class="small mute">' + esc([c.city, c.email].filter(Boolean).join(' · ')) + '</div></div>').join('');
      }
      box.hidden = false;
    }

    function pick(i) {
      const c = items[i];
      if (!c) return;
      if (input.dataset.append) {
        const ta = document.getElementById(input.dataset.append);
        const ids = ta.value.split(/[\s,;]+/).filter(Boolean);
        if (!ids.includes(c.id)) ta.value = ids.concat(c.id).join('\n');
        input.value = '';
      } else {
        input.value = c.id;
      }
      box.hidden = true;
      input.dispatchEvent(new CustomEvent('contact-picked', {detail: c, bubbles: true}));
    }

    async function search() {
      const q = input.value.trim();
      if (q.length < 2) { box.hidden = true; return; }
      const my = ++seq;
      const u = cfg.url + '&q=' + encodeURIComponent(q) + (mode === 'registrant' ? '&registrant=1' : '');
      try {
        const r = await fetch(u, {credentials: 'same-origin'});
        const data = await r.json();
        if (my !== seq) return; // risposta superata da una più recente
        items = data; sel = items.length ? 0 : -1;
        render(q);
      } catch (e) { box.hidden = true; }
    }

    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(search, 200); });
    input.addEventListener('focus', () => { if (input.value.trim().length >= 2 && !input.dataset.append) search(); });
    input.addEventListener('keydown', e => {
      if (e.key === 'Enter' && input.dataset.append && (box.hidden || sel < 0)) { e.preventDefault(); return; } // non inviare il form
      if (box.hidden) return;
      if (e.key === 'ArrowDown') { sel = Math.min(sel + 1, items.length - 1); render(input.value); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { sel = Math.max(sel - 1, 0); render(input.value); e.preventDefault(); }
      else if (e.key === 'Enter' && sel >= 0) { pick(sel); e.preventDefault(); }
      else if (e.key === 'Escape') { box.hidden = true; }
    });
    box.addEventListener('mousedown', e => {
      const it = e.target.closest('.ac-item');
      if (it) { e.preventDefault(); pick(+it.dataset.i); }
    });
    input.addEventListener('blur', () => setTimeout(() => { box.hidden = true; }, 150));
  }

  document.querySelectorAll('[data-contact-search]').forEach(attach);
})();
