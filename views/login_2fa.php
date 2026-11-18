<div class="card">
  <h1>Verifica in due passaggi</h1>
  <p>Ciao <strong><?= h($u['username']) ?></strong>, inserisci il codice a 6 cifre dell'app di autenticazione.</p>
  <form method="post" action="<?= url('login/2fa') ?>">
    <?= csrf_field() ?>
    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus required
           maxlength="11" placeholder="123456" style="font-size:22px;letter-spacing:4px;text-align:center">
    <?php if ($remember > 0): ?>
      <label style="font-weight:400"><input type="checkbox" name="remember" value="1"> Ricorda questo computer per <?= (int)$remember ?> giorni</label>
      <p class="help" style="margin-top:0">Non spuntarlo su computer condivisi.</p>
    <?php endif; ?>
    <p><button>Verifica</button></p>
  </form>
  <p class="help">Telefono non disponibile? Inserisci uno dei codici di recupero (formato XXXXX-XXXXX): ognuno vale una sola volta.
    In alternativa un amministratore può azzerare il tuo doppio fattore.</p>
  <p class="small"><a href="<?= url('login') ?>">← Torna al login</a></p>
</div>
