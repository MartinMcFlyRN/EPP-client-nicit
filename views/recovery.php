<h1><?= th('recovery.title') ?></h1>
<div class="card" style="max-width:560px">
  <div class="flash ok"><?= th('recovery.save_now') ?></div>
  <p><?= t('recovery.intro_html') ?></p>
  <pre class="xml" id="codes" style="font-size:16px;line-height:1.8;text-align:center"><?= h(implode("\n", $codes)) ?></pre>
  <p class="row">
    <button type="button" class="sec shrink" onclick="navigator.clipboard.writeText(document.getElementById('codes').textContent).then(()=>this.textContent=I18N.copied)"><?= th('common.copy') ?></button>
    <a class="btn shrink" href="<?= url('dashboard') ?>"><?= th('recovery.continue') ?></a>
  </p>
  <p class="help"><?= th('recovery.old_invalid') ?></p>
</div>
