<h1><?= th('eppw.title') ?></h1>
<div class="card" style="max-width:520px">
  <p class="help"><?= t('eppw.help_html', ['link' => '<a href="' . url('settings') . '">' . th('nav.settings') . '</a>']) ?></p>
  <form method="post" action="<?= url('session/password') ?>">
    <?= csrf_field() ?>
    <label><?= th('eppw.new') ?></label><input type="password" name="new_pw" required minlength="8" maxlength="16">
    <label><?= th('common.repeat') ?></label><input type="password" name="new_pw2" required minlength="8" maxlength="16">
    <p><button><?= th('eppw.submit') ?></button></p>
  </form>
</div>
