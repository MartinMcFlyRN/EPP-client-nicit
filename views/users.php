<h1>Utenti</h1>
<div class="card">
  <table><tr><th>Utente</th><th>Ruolo</th><th>Attivo</th><th>Nuova password</th><th>2FA</th><th>Ultimo accesso</th><th></th></tr>
  <?php foreach ($rows as $u): ?>
    <?php $fid = 'u' . (int)$u['id']; ?>
    <tr>
      <td><?= h($u['username']) ?></td>
      <td><select name="role" form="<?= $fid ?>"><?php foreach (array_keys(ROLE_LEVEL) as $r): ?><option <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></td>
      <td><input type="checkbox" name="active" value="1" form="<?= $fid ?>" <?= $u['active'] ? 'checked' : '' ?>></td>
      <td><input type="password" name="password" form="<?= $fid ?>" placeholder="invariata"></td>
      <td class="small"><?php if ($u['totp_enabled_at']): ?><span class="badge ok">attiva</span>
          <button class="small sec" form="r<?= (int)$u['id'] ?>">Azzera</button>
        <?php else: ?><span class="badge warn">da configurare</span><?php endif; ?></td>
      <td class="small"><?= fmt_date($u['last_login']) ?></td>
      <td><form method="post" action="<?= url('users') ?>" id="<?= $fid ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="small sec">Salva</button></form></td>
    </tr>
  <?php endforeach; ?>
  </table>
  <?php foreach ($rows as $u): if ($u['totp_enabled_at']): ?>
    <form method="post" action="<?= url('users') ?>" id="r<?= (int)$u['id'] ?>" data-confirm="Azzerare il doppio fattore di <?= h($u['username']) ?>? Dovrà riconfigurarlo al prossimo accesso.">
      <?= csrf_field() ?><input type="hidden" name="reset_2fa" value="<?= (int)$u['id'] ?>"></form>
  <?php endif; endforeach; ?>
</div>
<form method="post" action="<?= url('users') ?>" class="card" style="max-width:520px">
  <?= csrf_field() ?>
  <h2>Nuovo utente</h2>
  <label>Utente</label><input type="text" name="username" required>
  <label>Password (min 10)</label><input type="password" name="password" required minlength="10">
  <label>Ruolo</label><select name="role"><option>operator</option><option>readonly</option><option>admin</option></select>
  <p class="help">readonly: check/info/log. operator: tutte le operazioni EPP. admin: anche console XML, utenti e cambio password EPP. Il doppio fattore è obbligatorio: ogni utente lo configura al primo accesso.</p>
  <p><button>Crea</button></p>
</form>
