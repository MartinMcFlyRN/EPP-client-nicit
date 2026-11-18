<div class="card" style="max-width:440px">
  <h1><?= th('setup.title') ?></h1>
  <p><?= t('setup.intro_html', ['user' => '<strong>' . h($u['username']) . '</strong>']) ?></p>
  <ol class="small" style="padding-left:18px">
    <li><?= th('setup.step1') ?></li>
    <li><?= th('setup.step2') ?></li>
  </ol>
  <div id="qr" style="display:flex;justify-content:center;margin:14px 0"></div>
  <details><summary><?= th('setup.manual') ?></summary>
    <p class="mono" style="word-break:break-all;font-size:15px;letter-spacing:1px"><?= h(trim(chunk_split($secret, 4, ' '))) ?></p>
    <p class="help"><?= th('setup.manual_type') ?></p>
  </details>
  <form method="post" action="<?= url('login/setup') ?>">
    <?= csrf_field() ?>
    <label><?= th('setup.code') ?></label>
    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="7"
           placeholder="123456" style="font-size:22px;letter-spacing:4px;text-align:center">
    <p><button><?= th('setup.submit') ?></button></p>
  </form>
  <p class="small"><a href="<?= url('login') ?>"><?= th('setup.cancel') ?></a></p>
</div>
<script src="<?= h(rtrim((string)cfg('app.base_url', ''), '/')) ?>/assets/vendor/qrcode.js"></script>
<script>
  (function () {
    const q = qrcode(0, 'M');
    q.addData(<?= json_encode($uri) ?>);
    q.make();
    document.getElementById('qr').innerHTML = q.createSvgTag({cellSize: 5, margin: 4, scalable: false});
  })();
</script>
