<h1>Account — <?= h($u['username']) ?></h1>
<div class="grid g2">
  <div class="card">
    <h2>Doppio fattore</h2>
    <dl class="kv">
      <dt>Stato</dt><dd><span class="badge ok">attivo</span> dal <?= fmt_date($u['totp_enabled_at']) ?></dd>
      <dt>Codici di recupero</dt><dd><?= (int)$left ?> rimasti <?= $left <= 2 ? '<span class="badge warn">rigenerali</span>' : '' ?></dd>
    </dl>
    <form method="post" action="<?= url('account') ?>" style="margin-top:10px">
      <?= csrf_field() ?><input type="hidden" name="action" value="recovery">
      <label>Codice attuale dell'app</label>
      <div class="row"><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="7">
        <button class="shrink">Rigenera codici di recupero</button></div>
    </form>
    <p class="help">Hai cambiato telefono? Chiedi a un amministratore di azzerare il doppio fattore: al prossimo accesso ti verrà mostrato un nuovo QR code.</p>
  </div>
  <div class="card">
    <h2>Cambia password</h2>
    <form method="post" action="<?= url('account') ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <label>Nuova password (min 10)</label><input type="password" name="new_pw" required minlength="10" autocomplete="new-password">
      <label>Ripeti</label><input type="password" name="new_pw2" required minlength="10" autocomplete="new-password">
      <label>Codice attuale dell'app</label><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="7">
      <p><button>Cambia password</button></p>
    </form>
  </div>
</div>
