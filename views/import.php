<h1>Importa domini dal Registro</h1>
<?php if (!$job): ?>
<form method="post" action="<?= url('import') ?>" enctype="multipart/form-data" class="card" style="max-width:860px">
  <?= csrf_field() ?>
  <p>EPP non ha un comando che elenca i domini del registrar: esporta l'elenco dal portale <strong>RAIN</strong> del Registro
    (o da un vostro gestionale) e caricalo qui. Per ogni nome viene eseguito un <code>domain:info</code> e il risultato finisce nella cache locale.</p>
  <label>File (CSV o testo)</label>
  <input type="file" name="file" accept=".csv,.txt,text/csv,text/plain">
  <label>Oppure incolla i nomi</label>
  <textarea name="names" placeholder="dominio1.it&#10;dominio2.it"></textarea>
  <p class="help">Da ogni riga viene preso il primo campo che è un nome .it: le altre colonne del CSV vengono ignorate.</p>
  <label style="font-weight:400"><input type="checkbox" name="contacts" value="1" checked> Importa anche registranti e contatti admin/tech (un <code>contact:info</code> per ogni contatto, una volta sola)</label>
  <label style="font-weight:400"><input type="checkbox" name="skip_recent" value="1"> Salta i domini già aggiornati nelle ultime 24 ore (per riprendere un import)</label>
  <p><button>Avvia import</button></p>
</form>
<?php else:
  $total = count($job['names']);
?>
<div class="card" style="max-width:860px" id="job">
  <p>Avviato il <?= fmt_date($job['started_at']) ?> da <?= h($job['by']) ?> — <span id="st"><?= $job['finished_at'] ? 'completato il ' . fmt_date($job['finished_at']) : 'in corso…' ?></span></p>
  <progress id="bar" max="<?= $total ?>" value="<?= (int)$job['pos'] ?>" style="width:100%;height:18px"></progress>
  <dl class="kv" style="margin-top:12px">
    <dt>Elaborati</dt><dd><span id="pos"><?= (int)$job['pos'] ?></span> / <?= $total ?></dd>
    <dt>Domini importati</dt><dd id="ok"><?= (int)$job['ok'] ?></dd>
    <dt>Di altri registrar</dt><dd id="foreign"><?= (int)$job['foreign'] ?></dd>
    <dt>Saltati (recenti)</dt><dd id="skipped"><?= (int)$job['skipped'] ?></dd>
    <dt>Contatti importati</dt><dd id="contacts"><?= (int)$job['contacts_ok'] ?></dd>
    <dt>Errori</dt><dd id="errors"><?= count($job['errors']) ?></dd>
  </dl>
  <div id="lasterr" class="flash err" <?= empty($job['last_error']) ? 'hidden' : '' ?>><?= h($job['last_error'] ?? '') ?></div>
  <p class="row">
    <?php if (!$job['finished_at']): ?>
      <button class="shrink" id="go" type="button">Riprendi</button>
    <?php endif; ?>
    <form method="post" action="<?= url('import') ?>" class="shrink" data-confirm="<?= $job['finished_at'] ? 'Chiudere il riepilogo?' : 'Annullare l\'import? I domini già importati restano.' ?>">
      <?= csrf_field() ?><input type="hidden" name="cancel" value="1">
      <button class="sec"><?= $job['finished_at'] ? 'Nuovo import' : 'Annulla' ?></button></form>
    <a class="btn sec shrink" href="<?= url('domains') ?>">Vai ai domini</a>
  </p>
  <p class="help">L'import procede a blocchi di circa 20 secondi finché questa pagina resta aperta. Se la chiudi, riaprila e premi «Riprendi».
    Per portafogli molto grandi conviene lo script <code>php bin/import-domains.php elenco.csv</code>.</p>
  <?php if ($job['errors']): ?>
    <details><summary>Dettaglio errori</summary>
      <table><?php foreach ($job['errors'] as $e): ?><tr><td class="mono small"><?= h($e['what']) ?></td><td class="small"><?= h($e['msg']) ?></td></tr><?php endforeach; ?></table>
    </details>
  <?php endif; ?>
</div>
<?php if (!$job['finished_at']): ?>
<script>
(() => {
  const csrf = <?= json_encode(csrf_token()) ?>, url = <?= json_encode(url('import/step')) ?>;
  const $ = id => document.getElementById(id), go = $('go');
  let running = false;
  async function loop() {
    if (running) return; running = true; go.disabled = true; $('lasterr').hidden = true;
    try {
      while (true) {
        const r = await fetch(url, {method: 'POST', body: new URLSearchParams({_csrf: csrf}), credentials: 'same-origin'});
        const j = await r.json().catch(() => ({last_error: 'Risposta non valida dal server (HTTP ' + r.status + ')'}));
        if (j.pos !== undefined) {
          $('bar').value = j.pos; $('pos').textContent = j.pos; $('ok').textContent = j.ok; $('foreign').textContent = j.foreign;
          $('skipped').textContent = j.skipped; $('contacts').textContent = j.contacts_ok; $('errors').textContent = j.errors;
        }
        if (j.last_error) { $('lasterr').textContent = 'Import in pausa: ' + j.last_error; $('lasterr').hidden = false; break; }
        if (j.done) { location.reload(); return; }
      }
    } catch (e) { $('lasterr').textContent = 'Import in pausa: ' + e; $('lasterr').hidden = false; }
    running = false; go.disabled = false;
  }
  go.addEventListener('click', loop);
  loop();
})();
</script>
<?php endif; ?>
<?php endif; ?>
