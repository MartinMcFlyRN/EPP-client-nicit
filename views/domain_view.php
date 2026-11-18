<?php
use NicEpp\Epp\Commands;
$op = can('operator') && $ours;
?>
<h1><?= h($name) ?></h1>

<?php if (!$d): ?>
  <div class="card">
    <p><?= th('dv.unreadable') ?></p>
    <form method="get" action="<?= url() ?>" class="row"><input type="hidden" name="r" value="domains/info"><input type="hidden" name="name" value="<?= h($name) ?>">
      <input type="text" name="auth" placeholder="AuthInfo"><button class="shrink"><?= th('dv.info_with_auth') ?></button></form>
    <?php require __DIR__ . '/partials_response.php'; ?>
  </div>
  <?php return; ?>
<?php endif; ?>

<?php if (!$ours): ?><div class="flash err"><?= th('dv.foreign', ['clid' => $d['clID']]) ?> <a href="<?= url('domains/transfer', ['name' => $name]) ?>"><?= th('dv.transfer') ?></a></div><?php endif; ?>

<div class="grid g2">
  <div class="card">
    <h2><?= th('dv.data') ?></h2>
    <dl class="kv">
      <dt><?= th('account.status') ?></dt><dd><?php foreach ($d['status'] as $s) echo status_badge($s); foreach ($d['rgp'] as $s) echo status_badge($s); ?></dd>
      <dt><?= th('dom.registrant') ?></dt><dd class="mono"><a href="<?= url('contacts/info', ['id' => $d['registrant']]) ?>"><?= h($d['registrant']) ?></a></dd>
      <?php foreach ($d['contacts'] as $c): ?>
        <dt><?= h($c['type']) ?></dt><dd class="mono"><a href="<?= url('contacts/info', ['id' => $c['id']]) ?>"><?= h($c['id']) ?></a></dd>
      <?php endforeach; ?>
      <dt><?= th('dash.registrar') ?></dt><dd class="mono"><?= h($d['clID']) ?></dd>
      <dt><?= th('common.created') ?></dt><dd><?= fmt_date($d['crDate']) ?> <span class="mute small"><?= h($d['crID']) ?></span></dd>
      <dt><?= th('common.updated') ?></dt><dd><?= fmt_date($d['upDate']) ?></dd>
      <dt><?= th('dom.expiry') ?></dt><dd><strong><?= fmt_date($d['exDate']) ?></strong></dd>
      <?php if ($d['trDate']): ?><dt><?= th('dv.transferred') ?></dt><dd><?= fmt_date($d['trDate']) ?></dd><?php endif; ?>
      <dt>ROID</dt><dd class="mono small"><?= h($d['roid']) ?></dd>
      <?php if ($d['authInfo']): ?><dt>AuthInfo</dt><dd class="mono"><?= h($d['authInfo']) ?></dd><?php endif; ?>
    </dl>
  </div>
  <div class="card">
    <h2><?= th('dv.nameservers') ?></h2>
    <table><?php foreach ($d['ns'] as $n): ?><tr><td class="mono"><?= h($n['host']) ?></td><td class="mono small"><?= h(implode(' ', $n['ips'])) ?></td></tr><?php endforeach; ?></table>
    <h2 style="margin-top:14px">DNSSEC</h2>
    <?php if (!$d['ds']): ?><p class="mute"><?= th('dv.no_ds') ?></p><?php else: ?>
      <table><tr><th>KeyTag</th><th>Alg</th><th>DigestType</th><th>Digest</th></tr>
      <?php foreach ($d['ds'] as $x): ?><tr class="mono small"><td><?= $x['keyTag'] ?></td><td><?= $x['alg'] ?></td><td><?= $x['digestType'] ?></td><td style="word-break:break-all"><?= h($x['digest']) ?></td></tr><?php endforeach; ?></table>
    <?php endif; ?>
    <?php if ($d['extension']): ?><details><summary><?= th('dv.extensions') ?></summary><pre class="xml"><?= h($d['extension']) ?></pre></details><?php endif; ?>
    <?php require __DIR__ . '/partials_response.php'; ?>
  </div>
</div>

<?php if ($op): ?>
<?php
  $def = epp_defaults();
  $admins = implode(' ', array_column(array_filter($d['contacts'], fn($c) => $c['type'] === 'admin'), 'id'));
  $techs = implode("\n", array_column(array_filter($d['contacts'], fn($c) => $c['type'] === 'tech'), 'id'));
  $form = fn(string $action) => '<form method="post" action="' . url('domains/update') . '">' . csrf_field()
      . '<input type="hidden" name="name" value="' . h($name) . '"><input type="hidden" name="action" value="' . $action . '">';
?>
<h2><?= th('dv.operations') ?></h2>
<div class="grid g2">
  <div class="card">
    <h2><?= th('dv.nameservers') ?></h2>
    <?= $form('ns') ?>
      <textarea name="ns" id="ns_cur"><?= h(ns_to_lines($d['ns'])) ?></textarea>
      <p class="row"><?= fill_button('ns_cur', $def['ns'], t('def.use_ns')) ?>
        <?= fill_button('ns_cur', ns_to_lines($d['ns']), t('dv.restore_current')) ?></p>
      <p class="help"><?= t('dv.ns_help_html') ?></p>
      <button><?= th('dv.update_ns') ?></button></form>
  </div>
  <div class="card">
    <h2><?= th('dv.contacts') ?></h2>
    <?= $form('contacts') ?>
      <label>Admin</label><input type="text" name="admin" id="adm_cur" value="<?= h($admins) ?>" data-contact-search="any" placeholder="<?= th('con.search_short_ph') ?>">
      <label><?= th('dv.techs') ?></label><textarea name="techs" id="tech_cur" style="min-height:60px"><?= h($techs) ?></textarea>
      <input type="text" data-contact-search="any" data-append="tech_cur" placeholder="<?= th('con.search_add_tech') ?>" style="margin-top:4px">
        <p class="row">
          <?= fill_button('adm_cur', $def['admin'], t('def.use_admin')) ?>
          <?= fill_button('tech_cur', $def['techs'], t('def.use_techs')) ?>
          <?= fill_button('adm_cur', $d['registrant'], t('dv.admin_is_registrant')) ?>
        </p>
      <p><button><?= th('dv.update_contacts') ?></button></p></form>
  </div>
  <div class="card">
    <h2><?= th('dv.client_status') ?></h2>
    <?= $form('status') ?>
      <?php foreach (Commands::DOMAIN_CLIENT_STATUSES as $s): ?>
        <label style="font-weight:400"><input type="checkbox" name="status[]" value="<?= $s ?>" <?= in_array($s, $d['status'], true) ? 'checked' : '' ?>> <?= $s ?></label>
      <?php endforeach; ?>
      <p class="help"><?= th('dv.status_help') ?></p>
      <button><?= th('dv.update_status') ?></button></form>
  </div>
  <div class="card">
    <h2>AuthInfo</h2>
    <?= $form('authinfo') ?>
      <div class="row"><input type="text" id="ai1" name="authinfo" placeholder="<?= th('common.empty_generated') ?>"><button class="sec shrink" data-gen="ai1"><?= th('common.generate') ?></button></div>
      <p class="help"><?= th('dv.authinfo_help') ?></p>
      <button><?= th('dv.change_authinfo') ?></button></form>
  </div>
  <div class="card">
    <h2><?= th('dv.change_registrant') ?></h2>
    <?= $form('registrant') ?>
      <label><?= th('dv.new_registrant') ?></label><input type="text" name="registrant" required data-contact-search="registrant" placeholder="<?= th('con.search_ph') ?>">
      <label><?= th('dv.new_admin') ?></label><input type="text" name="admin" data-contact-search="any" placeholder="<?= th('dv.new_admin_ph') ?>">
      <label><?= th('dv.new_authinfo') ?></label>
      <div class="row"><input type="text" id="ai2" name="authinfo" placeholder="<?= th('common.empty_generated') ?>"><button class="sec shrink" data-gen="ai2"><?= th('common.generate') ?></button></div>
      <p class="help"><?= th('dv.registrant_help') ?></p>
      <button><?= th('dv.change_registrant') ?></button></form>
  </div>
<?php if (dnssec_enabled()): ?>
  <div class="card">
    <h2><?= th('dv.dnssec') ?></h2>
    <?= $form('dnssec') ?>
      <textarea name="ds"><?= h(ds_to_lines($d['ds'])) ?></textarea>
      <p class="help"><?= t('dv.ds_help_html') ?></p>
      <button><?= th('dv.update_ds') ?></button></form>
  </div>
<?php endif; ?>
  <div class="card danger-zone">
    <h2><?= th('dv.delete_restore') ?></h2>
    <form method="post" action="<?= url('domains/delete') ?>" data-confirm="<?= th('dv.delete_confirm', ['name' => $name]) ?>">
      <?= csrf_field() ?><input type="hidden" name="name" value="<?= h($name) ?>">
      <label><?= th('dv.type_name') ?></label>
      <div class="row"><input type="text" name="confirm" autocomplete="off"><button class="danger shrink"><?= th('dv.delete') ?></button></div>
    </form>
    <?php if (in_array('pendingDelete', $d['status'], true) || in_array('redemptionPeriod', $d['rgp'], true)): ?>
      <form method="post" action="<?= url('domains/restore') ?>" style="margin-top:12px" data-confirm="<?= th('dv.restore_confirm', ['name' => $name]) ?>">
        <?= csrf_field() ?><input type="hidden" name="name" value="<?= h($name) ?>"><button class="sec"><?= th('dv.restore') ?></button></form>
    <?php endif; ?>
  </div>
  <?php if (in_array('pendingTransfer', $d['status'], true)): ?>
  <div class="card">
    <h2><?= th('dv.pending_transfer') ?></h2>
    <p><?= th('dv.pending_transfer_text') ?></p>
    <?php foreach (['query' => t('account.status'), 'approve' => t('dv.approve'), 'reject' => t('dv.reject')] as $o => $l): ?>
      <form method="post" action="<?= url('domains/transfer') ?>" class="inline"><?= csrf_field() ?>
        <input type="hidden" name="op" value="<?= $o ?>"><input type="hidden" name="name" value="<?= h($name) ?>">
        <input type="hidden" name="authinfo" value="<?= h($d['authInfo']) ?>">
        <button class="<?= $o === 'reject' ? 'danger' : 'sec' ?>"><?= h($l) ?></button></form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
