<h1><?= th('imp.title') ?></h1>
<?php if (!$job): ?>
<form method="post" action="<?= url('import') ?>" enctype="multipart/form-data" class="card" style="max-width:860px">
  <?= csrf_field() ?>
  <p><?= t('imp.intro_html') ?></p>
  <label><?= th('imp.file') ?></label>
  <input type="file" name="file" accept=".csv,.txt,text/csv,text/plain">
  <label><?= th('imp.paste') ?></label>
  <textarea name="names" placeholder="domain1.it&#10;domain2.it"></textarea>
  <p class="help"><?= th('imp.parse_help') ?></p>
  <label style="font-weight:400"><input type="checkbox" name="contacts" value="1" checked> <?= t('imp.with_contacts_html') ?></label>
  <label style="font-weight:400"><input type="checkbox" name="skip_recent" value="1"> <?= th('imp.skip_recent') ?></label>
  <p><button><?= th('imp.start') ?></button></p>
</form>
<?php else:
  $total = count($job['names']);
?>
<div class="card" style="max-width:860px" id="job">
  <p><?= th('imp.started', ['date' => fmt_date($job['started_at']), 'user' => $job['by']]) ?> — <span id="st"><?= $job['finished_at'] ? th('imp.completed', ['date' => fmt_date($job['finished_at'])]) : th('imp.running') ?></span></p>
  <progress id="bar" max="<?= $total ?>" value="<?= (int)$job['pos'] ?>" style="width:100%;height:18px"></progress>
  <dl class="kv" style="margin-top:12px">
    <dt><?= th('imp.processed') ?></dt><dd><span id="pos"><?= (int)$job['pos'] ?></span> / <?= $total ?></dd>
    <dt><?= th('imp.domains_ok') ?></dt><dd id="ok"><?= (int)$job['ok'] ?></dd>
    <dt><?= th('imp.foreign') ?></dt><dd id="foreign"><?= (int)$job['foreign'] ?></dd>
    <dt><?= th('imp.skipped') ?></dt><dd id="skipped"><?= (int)$job['skipped'] ?></dd>
    <dt><?= th('imp.contacts_ok') ?></dt><dd id="contacts"><?= (int)$job['contacts_ok'] ?></dd>
    <dt><?= th('imp.errors') ?></dt><dd id="errors"><?= count($job['errors']) ?></dd>
  </dl>
  <div id="lasterr" class="flash err" <?= empty($job['last_error']) ? 'hidden' : '' ?>><?= h($job['last_error'] ?? '') ?></div>
  <p class="row">
    <?php if (!$job['finished_at']): ?>
      <button class="shrink" id="go" type="button"><?= th('imp.resume') ?></button>
    <?php endif; ?>
    <form method="post" action="<?= url('import') ?>" class="shrink" data-confirm="<?= th($job['finished_at'] ? 'imp.close_confirm' : 'imp.cancel_confirm') ?>">
      <?= csrf_field() ?><input type="hidden" name="cancel" value="1">
      <button class="sec"><?= th($job['finished_at'] ? 'imp.new' : 'common.cancel') ?></button></form>
    <a class="btn sec shrink" href="<?= url('domains') ?>"><?= th('imp.go_domains') ?></a>
  </p>
  <p class="help"><?= t('imp.progress_help_html') ?></p>
  <?php if ($job['errors']): ?>
    <details><summary><?= th('imp.error_details') ?></summary>
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
        const j = await r.json().catch(() => ({last_error: I18N.bad_response.replace('{status}', r.status)}));
        if (j.pos !== undefined) {
          $('bar').value = j.pos; $('pos').textContent = j.pos; $('ok').textContent = j.ok; $('foreign').textContent = j.foreign;
          $('skipped').textContent = j.skipped; $('contacts').textContent = j.contacts_ok; $('errors').textContent = j.errors;
        }
        if (j.last_error) { $('lasterr').textContent = I18N.import_paused + ' ' + j.last_error; $('lasterr').hidden = false; break; }
        if (j.done) { location.reload(); return; }
      }
    } catch (e) { $('lasterr').textContent = I18N.import_paused + ' ' + e; $('lasterr').hidden = false; }
    running = false; go.disabled = false;
  }
  go.addEventListener('click', loop);
  loop();
})();
</script>
<?php endif; ?>
<?php endif; ?>
