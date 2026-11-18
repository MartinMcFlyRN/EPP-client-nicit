<div class="card">
  <h1><?= h(cfg('app.name', 'EPP .it')) ?></h1>
  <form method="post" action="<?= url('login') ?>">
    <?= csrf_field() ?>
    <label><?= th('login.username') ?></label><input type="text" name="username" autofocus required autocomplete="username">
    <label><?= th('login.password') ?></label><input type="password" name="password" required autocomplete="current-password">
    <p><button><?= th('login.submit') ?></button></p>
  </form>
  <p class="small mute"><?php foreach (languages() as $code => $name): ?>
    <?= $code === current_lang() ? '<strong>' . h($name) . '</strong>' : '<a href="' . url('lang', ['l' => $code]) . '">' . h($name) . '</a>' ?>
  <?php endforeach; ?></p>
</div>
