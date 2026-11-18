<h1><?= th('nav.users') ?></h1>
<div class="card">
  <table><tr><th><?= th('login.username') ?></th><th><?= th('users.role') ?></th><th><?= th('users.active') ?></th><th><?= th('eppw.new') ?></th><th>2FA</th><th><?= th('users.last_login') ?></th><th><?= th('users.language') ?></th><th></th></tr>
  <?php foreach ($rows as $u): ?>
    <?php $fid = 'u' . (int)$u['id']; ?>
    <tr>
      <td><?= h($u['username']) ?></td>
      <td><select name="role" form="<?= $fid ?>"><?php foreach (array_keys(ROLE_LEVEL) as $r): ?><option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= th('role.' . $r) ?></option><?php endforeach; ?></select></td>
      <td><input type="checkbox" name="active" value="1" form="<?= $fid ?>" <?= $u['active'] ? 'checked' : '' ?>></td>
      <td><input type="password" name="password" form="<?= $fid ?>" placeholder="<?= th('users.unchanged') ?>"></td>
      <td class="small"><?php if ($u['totp_enabled_at']): ?><span class="badge ok"><?= th('users.2fa_on') ?></span>
          <button class="small sec" form="r<?= (int)$u['id'] ?>"><?= th('users.reset') ?></button>
        <?php else: ?><span class="badge warn"><?= th('users.2fa_pending') ?></span><?php endif; ?></td>
      <td class="small"><?= fmt_date($u['last_login']) ?></td>
      <td class="small"><?= h(languages()[$u['lang'] ?? 'en'] ?? ($u['lang'] ?? 'en')) ?></td>
      <td><form method="post" action="<?= url('users') ?>" id="<?= $fid ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="small sec"><?= th('common.save') ?></button></form></td>
    </tr>
  <?php endforeach; ?>
  </table>
  <?php foreach ($rows as $u): if ($u['totp_enabled_at']): ?>
    <form method="post" action="<?= url('users') ?>" id="r<?= (int)$u['id'] ?>" data-confirm="<?= th('users.reset_confirm', ['user' => $u['username']]) ?>">
      <?= csrf_field() ?><input type="hidden" name="reset_2fa" value="<?= (int)$u['id'] ?>"></form>
  <?php endif; endforeach; ?>
</div>
<form method="post" action="<?= url('users') ?>" class="card" style="max-width:520px">
  <?= csrf_field() ?>
  <h2><?= th('users.new') ?></h2>
  <label><?= th('login.username') ?></label><input type="text" name="username" required>
  <label><?= th('users.password_min', ['n' => min_password()]) ?></label><input type="password" name="password" required minlength="<?= min_password() ?>">
  <label><?= th('users.role') ?></label><select name="role"><?php foreach (['operator', 'readonly', 'admin'] as $r): ?><option value="<?= $r ?>"><?= th('role.' . $r) ?></option><?php endforeach; ?></select>
  <label><?= th('users.language') ?></label><select name="lang"><?php foreach (languages() as $code => $name): ?><option value="<?= h($code) ?>" <?= $code === 'en' ? 'selected' : '' ?>><?= h($name) ?></option><?php endforeach; ?></select>
  <p class="help"><?= th('users.roles_help') ?></p>
  <p><button><?= th('users.create') ?></button></p>
</form>
