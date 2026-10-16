/* Bildauswahl – Upload in Stücken (2 MB) in den geschützten Ordner eines Shootings; nacheinander, mit Fortschritt; danach neu laden */
{
const box = document.querySelector('[data-aw-upload]');
if (box) {
  const CH = 2 * 1024 * 1024;
  const queue = box.querySelector('[data-aw-queue]');
  const input = box.querySelector('[data-aw-input]');
  const fill = (s, v) => s.replace(/\{(\w+)\}/g, (_, k) => v[k] ?? '');
  const post = async (url, data) => {
    const fd = new FormData();
    fd.set('_csrf', box.dataset.csrf);
    for (const [k, v] of Object.entries(data)) fd.set(k, v);
    const r = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': box.dataset.csrf } });
    const j = await r.json().catch(() => ({}));
    if (!r.ok || j.ok === false) throw new Error(j.error || r.statusText);
    return j;
  };
  let busy = false;
  const run = async files => {
    if (busy) return;
    busy = true;
    let done = 0;
    for (const f of files) {
      const li = document.createElement('li');
      const name = document.createElement('span'); name.textContent = f.name;
      const pct = document.createElement('span'); pct.textContent = '0 %';
      const bar = document.createElement('progress'); bar.max = 100; bar.value = 0;
      li.append(name, pct, bar); queue.append(li);
      if (f.size > +box.dataset.limit) { pct.textContent = fill(box.dataset.tBig, { name: f.name }); li.classList.add('is-bad'); continue; }
      const id = (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(16).slice(2)).replace(/[^a-zA-Z0-9-]/g, '').slice(0, 40);
      const total = Math.max(1, Math.ceil(f.size / CH));
      try {
        for (let i = 0; i < total; i++) {
          await post(box.dataset.chunk, { chunk: f.slice(i * CH, (i + 1) * CH), upload_id: id, index: i, total, size: f.size });
          bar.value = Math.round((i + 1) / total * 95); pct.textContent = bar.value + ' %';
        }
        await post(box.dataset.finish, { upload_id: id, name: f.name });
        bar.value = 100; pct.textContent = '100 %'; done++;
      } catch (e) {
        pct.textContent = fill(box.dataset.tErr, { name: f.name, err: e.message }); li.classList.add('is-bad');
      }
    }
    busy = false;
    if (done) location.reload();
  };
  input.addEventListener('change', () => run([...input.files]));
  for (const ev of ['dragenter', 'dragover']) box.addEventListener(ev, e => { e.preventDefault(); box.classList.add('is-over'); });
  for (const ev of ['dragleave', 'drop']) box.addEventListener(ev, e => { e.preventDefault(); box.classList.remove('is-over'); });
  box.addEventListener('drop', e => run([...e.dataTransfer.files].filter(f => /^(image|video)\//.test(f.type))));
}
}
