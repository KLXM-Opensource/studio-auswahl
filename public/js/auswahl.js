/* Bildauswahl (Erweiterung auswahl): Auswählen per Herz, Filter, Lightbox mit Tastatur und Wischen, Notizen, Abschicken.
   Ohne Abhängigkeiten, CSP-fest (keine Inline-Stile – Fortschritt per el.style.setProperty). */
{
const root = document.querySelector('[data-aw]');
if (root) {
  const $ = (s, el = root) => el.querySelector(s);
  const items = () => [...root.querySelectorAll('[data-aw-item]')];
  const editable = root.dataset.editable === '1';
  const max = +root.dataset.package || 0;
  const T = k => root.dataset['t' + k[0].toUpperCase() + k.slice(1)] || '';
  const fill = (s, v) => s.replace(/\{(\w+)\}/g, (_, k) => v[k] ?? '');
  let filter = 'all';

  // ---------------------------------------------------------- Server
  const post = async (url, data) => {
    const body = new FormData();
    body.set('_csrf', root.dataset.csrf);
    for (const [k, v] of Object.entries(data)) body.set(k, v);
    const r = await fetch(url, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const j = await r.json().catch(() => ({}));
    if (!r.ok || !j.ok) throw new Error(j.error || T('error'));
    return j;
  };

  // ---------------------------------------------------------- Zähler, Fortschritt, Filter
  const counts = () => {
    const all = items();
    const picked = all.filter(li => li.classList.contains('is-picked')).length;
    const notes = all.filter(li => li.classList.contains('has-note')).length;
    $('[data-aw-count]').textContent = picked;
    const np = $('[data-aw-n-picked]'); if (np) np.textContent = picked;
    const nn = $('[data-aw-n-note]'); if (nn) nn.textContent = notes;
    const bar = $('[data-aw-bar]');
    if (bar && max) bar.style.setProperty('--p', Math.min(100, Math.round(picked / max * 100)) + '%');
    root.classList.toggle('is-full', max > 0 && picked >= max);
    const sum = document.querySelector('[data-aw-sendsum]');
    if (sum) sum.textContent = sum.textContent.replace(/\d+/, picked);
    return picked;
  };
  const applyFilter = () => {
    let shown = 0;
    for (const li of items()) {
      const ok = filter === 'all' || (filter === 'picked' && li.classList.contains('is-picked')) || (filter === 'note' && li.classList.contains('has-note'));
      li.hidden = !ok;
      if (ok) shown++;
    }
    $('[data-aw-empty]').hidden = shown > 0;
  };
  root.querySelectorAll('[data-aw-filter]').forEach(b => b.addEventListener('click', () => {
    filter = b.dataset.awFilter;
    root.querySelectorAll('[data-aw-filter]').forEach(x => x.setAttribute('aria-pressed', String(x === b)));
    applyFilter();
  }));

  // ---------------------------------------------------------- Auswählen
  const toast = (msg, bad = false) => {
    const s = lb.open ? $('[data-aw-status]', lb) : null;
    if (s) { s.textContent = msg; s.classList.toggle('is-bad', bad); clearTimeout(s._t); s._t = setTimeout(() => { s.textContent = ''; }, 3500); return; }
    let t = document.querySelector('.aw-toast');
    if (!t) { t = document.createElement('p'); t.className = 'aw-toast'; t.setAttribute('role', 'status'); document.body.append(t); }
    t.textContent = msg; t.classList.toggle('is-bad', bad); t.classList.add('is-on');
    clearTimeout(t._t); t._t = setTimeout(() => t.classList.remove('is-on'), 3500);
  };
  const setPicked = (li, on) => {
    li.classList.toggle('is-picked', on);
    $('[data-aw-pick]', li).setAttribute('aria-pressed', String(on));
    if (lb.open && current === li) syncLb();
  };
  const toggle = async li => {
    if (!editable) return;
    const on = !li.classList.contains('is-picked');
    setPicked(li, on); counts();   // sofort zeigen, bei Fehler zurück
    if (on) li.classList.add('is-pop'), setTimeout(() => li.classList.remove('is-pop'), 400);
    try {
      const j = await post(root.dataset.pick, { media: li.dataset.id, picked: on ? '1' : '0' });
      $('[data-aw-count]').textContent = j.count;
      if (max && on && j.count >= max) toast(fill(T('count'), { n: j.count, max }));
    } catch (e) {
      setPicked(li, !on); counts(); toast(e.message, true);
    }
    if (filter !== 'all') applyFilter();
  };
  root.addEventListener('click', e => {
    const b = e.target.closest('[data-aw-pick]');
    if (b) toggle(b.closest('[data-aw-item]'));
  });

  // ---------------------------------------------------------- Lightbox
  const lb = $('[data-aw-lb]');
  const stage = $('[data-aw-stage]', lb);
  let current = null, opener = null;
  const visible = () => items().filter(li => !li.hidden);
  const syncLb = () => {
    const on = current.classList.contains('is-picked');
    const b = $('[data-aw-lbpick]', lb);
    b.setAttribute('aria-pressed', String(on));
    $('[data-aw-lbpick-l]', lb).textContent = on ? T('picked') : T('pick');
  };
  const show = li => {
    current = li;
    const list = visible();
    stage.replaceChildren();
    let el;
    if (li.dataset.kind === 'video') {
      el = document.createElement('video');
      el.src = li.dataset.full; el.controls = true; el.playsInline = true; el.preload = 'metadata';
      if (li.dataset.poster) el.poster = li.dataset.poster;
    } else {
      el = document.createElement('img');
      el.src = li.dataset.full; el.alt = li.dataset.alt || li.dataset.name; el.decoding = 'async';
      el.width = +li.dataset.w; el.height = +li.dataset.h;
    }
    stage.append(el);
    $('[data-aw-pos]', lb).textContent = fill(T('of'), { i: list.indexOf(li) + 1, n: list.length });
    $('[data-aw-name]', lb).textContent = li.dataset.name;
    const note = $('#aw-note', lb);
    if (note) note.value = li.dataset.note || '';
    const dl = $('[data-aw-dl]', lb);
    if (li.dataset.download) { dl.href = li.dataset.download; dl.hidden = false; } else dl.hidden = true;
    $('[data-aw-status]', lb).textContent = '';
    syncLb();
    // Nachbarn vorladen
    const i = list.indexOf(li);
    for (const n of [list[i + 1], list[i - 1]]) if (n && n.dataset.kind === 'image') { const p = new Image(); p.src = n.dataset.full; }
  };
  const step = d => {
    const list = visible();
    if (!list.length) return;
    const i = list.indexOf(current);
    show(list[(i + d + list.length) % list.length]);
  };
  const open = li => { opener = document.activeElement; show(li); lb.showModal(); };
  root.addEventListener('click', e => {
    const a = e.target.closest('[data-aw-open]');
    if (!a) return;
    e.preventDefault();
    open(a.closest('[data-aw-item]'));
  });
  $('[data-aw-close]', lb).addEventListener('click', () => lb.close());
  $('[data-aw-prev]', lb).addEventListener('click', () => step(-1));
  $('[data-aw-next]', lb).addEventListener('click', () => step(1));
  $('[data-aw-lbpick]', lb).addEventListener('click', () => current && toggle(current));
  lb.addEventListener('close', () => {
    stage.querySelector('video')?.pause();
    stage.replaceChildren();
    (current?.querySelector('[data-aw-open]') || opener)?.focus({ preventScroll: false });
  });
  lb.addEventListener('click', e => { if (e.target === lb || e.target === stage) lb.close(); });
  lb.addEventListener('keydown', e => {
    if (e.target.matches('input, textarea')) return;
    if (e.key === 'ArrowRight') { e.preventDefault(); step(1); }
    else if (e.key === 'ArrowLeft') { e.preventDefault(); step(-1); }
    else if ((e.key === 'p' || e.key === 'P') && current) { e.preventDefault(); toggle(current); }
  });
  // Wischen
  let x0 = null;
  stage.addEventListener('pointerdown', e => { x0 = e.clientX; });
  stage.addEventListener('pointerup', e => {
    if (x0 === null) return;
    const dx = e.clientX - x0; x0 = null;
    if (Math.abs(dx) > 50) step(dx < 0 ? 1 : -1);
  });

  // ---------------------------------------------------------- Notizen
  const nf = $('[data-aw-noteform]', lb);
  if (nf && editable) nf.addEventListener('submit', async e => {
    e.preventDefault();
    const input = $('#aw-note', lb);
    const li = current;
    try {
      const j = await post(root.dataset.note, { media: li.dataset.id, note: input.value });
      li.dataset.note = j.note;
      li.classList.toggle('has-note', j.note !== '');
      counts(); toast(T('saved'));
    } catch (err) { toast(err.message, true); }
  });

  // ---------------------------------------------------------- Abschicken
  const sd = document.querySelector('[data-aw-senddlg]');
  root.querySelector('[data-aw-send]')?.addEventListener('click', () => { counts(); sd?.showModal(); });
  sd?.querySelector('[data-aw-sendclose]')?.addEventListener('click', () => sd.close());

  // Leiste unter einem festen bzw. mitlaufenden Seitenkopf des Kits halten
  const head = [...document.querySelectorAll('header')].find(h => !root.contains(h) && /sticky|fixed/.test(getComputedStyle(h).position));
  const top = () => root.style.setProperty('--aw-top', head ? Math.max(0, Math.round(head.getBoundingClientRect().bottom)) + 'px' : '0px');
  if (head) { top(); addEventListener('resize', top, { passive: true }); addEventListener('scroll', top, { passive: true }); }

  counts();
}
}
