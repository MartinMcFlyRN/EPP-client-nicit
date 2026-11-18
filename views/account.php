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
      <label>Nuova password (min <?= min_password() ?>)</label><input type="password" name="new_pw" required minlength="<?= min_password() ?>" autocomplete="new-password">
      <label>Ripeti</label><input type="password" name="new_pw2" required minlength="<?= min_password() ?>" autocomplete="new-password">
      <label>Codice attuale dell'app</label><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="7">
      <p><button>Cambia password</button></p>
    </form>
  </div>
</div>

<?php if ($remember > 0 || $devices): ?>
<div class="card">
  <h2>Computer ricordati</h2>
  <?php if (!$devices): ?><p class="mute">Nessun computer ricordato. Puoi farlo spuntando «Ricorda questo computer» quando inserisci il codice 2FA.</p><?php else: ?>
  <table><tr><th>Ultimo uso</th><th>Creato</th><th>Scade</th><th>IP</th><th>Browser</th><th></th></tr>
    <?php foreach ($devices as $d): ?>
      <tr><td class="small"><?= fmt_date($d['last_used']) ?></td><td class="small"><?= fmt_date($d['created_at']) ?></td>
        <td class="small"><?= fmt_date($d['expires_at']) ?></td><td class="mono small"><?= h($d['ip']) ?></td>
        <td class="small" style="max-width:320px"><?= h($d['user_agent']) ?></td>
        <td><form method="post" action="<?= url('account') ?>"><?= csrf_field() ?><input type="hidden" name="revoke" value="<?= (int)$d['id'] ?>"><button class="small sec">Revoca</button></form></td></tr>
    <?php endforeach; ?></table>
  <form method="post" action="<?= url('account') ?>" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="revoke" value="all"><button class="sec">Revoca tutti</button></form>
  <?php endif; ?>
  <p class="help">Vengono revocati anche quando cambi password o un admin azzera il tuo 2FA.</p>
</div>
<?php endif; ?>
