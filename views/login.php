<div class="card">
  <h1><?= h(cfg('app.name', 'EPP .it')) ?></h1>
  <form method="post" action="<?= url('login') ?>">
    <?= csrf_field() ?>
    <label>Utente</label><input type="text" name="username" autofocus required autocomplete="username">
    <label>Password</label><input type="password" name="password" required autocomplete="current-password">
    <p><button>Accedi</button></p>
  </form>
</div>
