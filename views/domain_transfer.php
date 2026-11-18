<h1><?= th('nav.transfers') ?></h1>
<div class="grid g2">
<form method="post" action="<?= url('domains/transfer') ?>" class="card">
  <?= csrf_field() ?>
  <label><?= th('tr.operation') ?></label>
  <select name="op">
    <?php foreach (['request', 'query', 'cancel', 'approve', 'reject'] as $k): ?>
      <option value="<?= $k ?>" <?= ($f['op'] ?? '') === $k ? 'selected' : '' ?>><?= th('tr.op_' . $k) ?></option>
    <?php endforeach; ?>
  </select>
  <label><?= th('dom.domain') ?></label><input type="text" name="name" value="<?= h($f['name'] ?? '') ?>" required>
  <label><?= th('tr.authinfo') ?></label><input type="text" name="authinfo" value="<?= h($f['authinfo'] ?? '') ?>">
  <label style="font-weight:400;margin-top:14px"><input type="checkbox" name="trade" value="1" data-toggle="tradebox" <?= !empty($f['trade']) ? 'checked' : '' ?>> <?= th('tr.trade') ?></label>
  <div id="tradebox">
    <label><?= th('tr.new_registrant') ?></label><input type="text" name="new_registrant" value="<?= h($f['new_registrant'] ?? '') ?>" data-contact-search="registrant" placeholder="<?= th('con.search_ph') ?>">
    <label><?= th('tr.new_authinfo') ?></label>
    <div class="row"><input type="text" id="nai" name="new_authinfo" value="<?= h($f['new_authinfo'] ?? '') ?>" placeholder="<?= th('common.empty_generated') ?>"><button class="sec shrink" data-gen="nai"><?= th('common.generate') ?></button></div>
  </div>
  <p><button><?= th('common.send') ?></button></p>
</form>
<div class="card">
  <h2><?= th('log.result') ?></h2>
  <?php if ($data): ?>
    <dl class="kv">
      <dt><?= th('dom.domain') ?></dt><dd><?= h($data['name']) ?></dd>
      <dt><?= th('account.status') ?></dt><dd><?= status_badge($data['trStatus']) ?></dd>
      <dt><?= th('tr.requester') ?></dt><dd class="mono"><?= h($data['reID']) ?> · <?= fmt_date($data['reDate']) ?></dd>
      <dt><?= th('tr.losing') ?></dt><dd class="mono"><?= h($data['acID']) ?> · <?= fmt_date($data['acDate']) ?></dd>
      <?php if ($data['exDate']): ?><dt><?= th('dom.expiry') ?></dt><dd><?= fmt_date($data['exDate']) ?></dd><?php endif; ?>
    </dl>
  <?php else: ?>
    <p class="mute"><?= th('tr.none') ?></p>
  <?php endif; ?>
  <?php require __DIR__ . '/partials_response.php'; ?>
  <p class="help"><?= th('tr.help') ?></p>
</div>
</div>
