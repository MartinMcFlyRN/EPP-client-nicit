<?php
$edit = $mode === 'edit';
$reg = $c['registrant'] ?? null;
$st = $c['street'] ?? [];
?>
<h1><?= $edit ? th('con.edit_title', ['id' => $c['id']]) : th('con.new_title') ?></h1>
<form method="post" action="<?= url($edit ? 'contacts/edit' : 'contacts/create') ?>" class="card" style="max-width:860px">
  <?= csrf_field() ?>
  <div class="row">
    <div><label><?= th('con.id') ?></label>
      <?php if ($edit): ?>
        <input type="text" name="id" value="<?= h($c['id'] ?? '') ?>" readonly>
      <?php else:
        $pref = contact_prefix();
        $suf = (isset($c['id']) && str_starts_with((string)$c['id'], $pref)) ? substr((string)$c['id'], strlen($pref)) : '';
      ?>
        <div class="idfield"><?php if ($pref !== ''): ?><span class="mono"><?= h($pref) ?></span><?php endif; ?>
          <input type="text" name="id_suffix" id="id_suffix" value="<?= h($suf) ?>" maxlength="<?= 16 - strlen($pref) ?>"
                 pattern="[A-Za-z0-9-]*" placeholder="<?= th('con.id_auto_ph') ?>" style="text-transform:uppercase"></div>
        <p class="help"><?= th('con.id_help', ['n' => 16 - strlen($pref)]) ?>
          <?php if (can('admin')): ?><?= t('con.id_prefix_where_html', ['link' => '<a href="' . url('settings') . '#defaults">' . th('nav.settings') . '</a>']) ?><?php endif; ?></p>
      <?php endif; ?></div>
    <div><label><?= th('con.full_name') ?></label><input type="text" name="name" value="<?= h($c['name'] ?? '') ?>" required></div>
  </div>
  <label><?= th('con.org') ?></label><input type="text" name="org" value="<?= h($c['org'] ?? '') ?>">
  <label><?= th('con.address') ?></label>
  <input type="text" name="street1" value="<?= h($st[0] ?? '') ?>" required placeholder="<?= th('con.street_ph') ?>">
  <input type="text" name="street2" value="<?= h($st[1] ?? '') ?>" style="margin-top:4px">
  <input type="text" name="street3" value="<?= h($st[2] ?? '') ?>" style="margin-top:4px">
  <div class="row">
    <div><label><?= th('con.postcode') ?></label><input type="text" name="pc" value="<?= h($c['pc'] ?? '') ?>"></div>
    <div><label><?= th('con.city') ?></label><input type="text" name="city" value="<?= h($c['city'] ?? '') ?>" required></div>
    <div><label><?= th('con.province') ?></label><input type="text" name="sp" value="<?= h($c['sp'] ?? '') ?>" maxlength="2" placeholder="GE"></div>
    <div><label><?= th('con.country') ?></label><input type="text" name="cc" value="<?= h($c['cc'] ?? 'IT') ?>" maxlength="2" required></div>
  </div>
  <div class="row">
    <div><label><?= th('con.phone') ?></label><input type="text" name="voice" value="<?= h($c['voice'] ?? '') ?>" placeholder="+39.0101234567"></div>
    <div><label>Fax</label><input type="text" name="fax" value="<?= h($c['fax'] ?? '') ?>" placeholder="+39.0101234567"></div>
    <div><label>Email</label><input type="email" name="email" value="<?= h($c['email'] ?? '') ?>" required></div>
  </div>
  <label style="font-weight:400;margin-top:14px"><input type="checkbox" name="consent" value="1" <?= !empty($c['consent']) ? 'checked' : '' ?>> <?= th('con.consent') ?></label>

  <label style="font-weight:400;margin-top:10px"><input type="checkbox" name="is_registrant" value="1" data-toggle="regbox" <?= $reg ? 'checked' : '' ?>> <?= th('con.is_registrant') ?></label>
  <div id="regbox" class="card" style="background:#fafbfc;margin-top:8px">
    <div class="row">
      <div><label><?= th('con.nationality') ?></label><input type="text" name="nationality" value="<?= h($reg['nationality'] ?? 'IT') ?>" maxlength="2"></div>
      <div><label><?= th('con.regcode') ?></label><input type="text" name="regCode" value="<?= h($reg['regCode'] ?? '') ?>"></div>
    </div>
    <label><?= th('con.entity_type_long') ?></label>
    <select name="entityType">
      <?php foreach (entity_types() as $k => $l): ?><option value="<?= $k ?>" <?= (int)($reg['entityType'] ?? 1) === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?>
    </select>
    <p class="help"><?= th('con.registrant_help') ?></p>
  </div>
  <p><button><?= th($edit ? 'con.save_changes' : 'con.create') ?></button></p>
  <?php require __DIR__ . '/partials_response.php'; ?>
</form>
