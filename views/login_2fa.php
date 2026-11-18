<div class="card">
  <h1><?= th('2fa.title') ?></h1>
  <p><?= t('2fa.intro_html', ['user' => '<strong>' . h($u['username']) . '</strong>']) ?></p>
  <form method="post" action="<?= url('login/2fa') ?>">
    <?= csrf_field() ?>
    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus required
           maxlength="11" placeholder="123456" style="font-size:22px;letter-spacing:4px;text-align:center">
    <?php if ($remember > 0): ?>
      <label style="font-weight:400"><input type="checkbox" name="remember" value="1"> <?= th('2fa.remember', ['days' => (int)$remember]) ?></label>
      <p class="help" style="margin-top:0"><?= th('2fa.remember_help') ?></p>
    <?php endif; ?>
    <p><button><?= th('2fa.verify') ?></button></p>
  </form>
  <p class="help"><?= th('2fa.recovery_help') ?></p>
  <p class="small"><a href="<?= url('login') ?>"><?= th('2fa.back') ?></a></p>
</div>
