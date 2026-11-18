<h1>Cambio password EPP</h1>
<div class="card" style="max-width:520px">
  <p class="help">Esegue un nuovo login con &lt;newPW&gt;. Se va a buon fine la nuova password viene salvata in <code>storage/epp-password</code> e usata da qui in avanti al posto di quella in config.php.</p>
  <form method="post" action="<?= url('session/password') ?>">
    <?= csrf_field() ?>
    <label>Nuova password</label><input type="password" name="new_pw" required minlength="8" maxlength="16">
    <label>Ripeti</label><input type="password" name="new_pw2" required minlength="8" maxlength="16">
    <p><button>Cambia password</button></p>
  </form>
</div>
