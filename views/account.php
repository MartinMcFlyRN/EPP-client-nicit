<h1><?= th('account.title', ['user' => $u['username']]) ?></h1>
<div class="grid g2">
  <div class="card">
    <h2><?= th('account.language') ?></h2>
    <form method="post" action="<?= url('account') ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="lang">
      <div class="row">
        <select name="lang">
          <?php foreach (languages() as $code => $name): ?>
            <option value="<?= h($code) ?>" <?= current_lang() === $code ? 'selected' : '' ?>><?= h($name) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="shrink"><?= th('common.save') ?></button>
      </div>
    </form>
    <h2 style="margin-top:16px"><?= th('account.2fa') ?></h2>
    <dl class="kv">
      <dt><?= th('account.status') ?></dt><dd><span class="badge ok"><?= th('account.active') ?></span> <?= th('account.since', ['date' => fmt_date($u['totp_enabled_at'])]) ?></dd>
      <dt><?= th('account.recovery_codes') ?></dt><dd><?= th('account.left', ['n' => (int)$left]) ?> <?= $left <= 2 ? '<span class="badge warn">' . th('account.regenerate_them') . '</span>' : '' ?></dd>
    </dl>
    <form method="post" action="<?= url('account') ?>" style="margin-top:10px">
      <?= csrf_field() ?><input type="hidden" name="action" value="recovery">
      <label><?= th('account.current_code') ?></label>
      <div class="row"><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="7">
        <button class="shrink"><?= th('account.regenerate') ?></button></div>
    </form>
    <p class="help"><?= th('account.new_phone') ?></p>
  </div>
  <div class="card">
    <h2><?= th('account.change_password') ?></h2>
    <form method="post" action="<?= url('account') ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <label><?= th('account.new_password', ['n' => min_password()]) ?></label><input type="password" name="new_pw" required minlength="<?= min_password() ?>" autocomplete="new-password">
      <label><?= th('common.repeat') ?></label><input type="password" name="new_pw2" required minlength="<?= min_password() ?>" autocomplete="new-password">
      <label><?= th('account.current_code') ?></label><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="7">
      <p><button><?= th('account.change_password') ?></button></p>
    </form>
  </div>
</div>

<?php if ($remember > 0 || $devices): ?>
<div class="card">
  <h2><?= th('account.devices') ?></h2>
  <?php if (!$devices): ?><p class="mute"><?= th('account.no_devices') ?></p><?php else: ?>
  <table><tr><th><?= th('account.last_used') ?></th><th><?= th('common.created') ?></th><th><?= th('account.expires') ?></th><th>IP</th><th><?= th('account.browser') ?></th><th></th></tr>
    <?php foreach ($devices as $d): ?>
      <tr><td class="small"><?= fmt_date($d['last_used']) ?></td><td class="small"><?= fmt_date($d['created_at']) ?></td>
        <td class="small"><?= fmt_date($d['expires_at']) ?></td><td class="mono small"><?= h($d['ip']) ?></td>
        <td class="small" style="max-width:320px"><?= h($d['user_agent']) ?></td>
        <td><form method="post" action="<?= url('account') ?>"><?= csrf_field() ?><input type="hidden" name="revoke" value="<?= (int)$d['id'] ?>"><button class="small sec"><?= th('account.revoke') ?></button></form></td></tr>
    <?php endforeach; ?></table>
  <form method="post" action="<?= url('account') ?>" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="revoke" value="all"><button class="sec"><?= th('account.revoke_all') ?></button></form>
  <?php endif; ?>
  <p class="help"><?= th('account.devices_help') ?></p>
</div>
<?php endif; ?>
