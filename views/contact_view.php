<h1><?= th('con.title', ['id' => $id]) ?></h1>
<?php if (!$c): ?>
  <div class="card"><p><?= th('con.unreadable') ?></p><?php require __DIR__ . '/partials_response.php'; ?></div>
  <?php return; ?>
<?php endif; ?>
<div class="grid g2">
  <div class="card">
    <h2><?= th('dv.data') ?></h2>
    <dl class="kv">
      <dt><?= th('con.name') ?></dt><dd><?= h($c['name']) ?></dd>
      <?php if ($c['org']): ?><dt><?= th('con.org') ?></dt><dd><?= h($c['org']) ?></dd><?php endif; ?>
      <dt><?= th('con.address') ?></dt><dd><?= nl2br(h(implode("\n", $c['street']))) ?><br><?= h(trim("{$c['pc']} {$c['city']} " . ($c['sp'] ? "({$c['sp']})" : ''))) ?> — <?= h($c['cc']) ?></dd>
      <dt><?= th('con.phone') ?></dt><dd class="mono"><?= h($c['voice']) ?></dd>
      <?php if ($c['fax']): ?><dt>Fax</dt><dd class="mono"><?= h($c['fax']) ?></dd><?php endif; ?>
      <dt>Email</dt><dd><?= h($c['email']) ?></dd>
      <dt><?= th('con.consent_short') ?></dt><dd><?= th($c['consent'] ? 'common.yes' : 'common.no') ?></dd>
      <dt><?= th('account.status') ?></dt><dd><?php foreach ($c['status'] as $s) echo status_badge($s); ?></dd>
      <dt><?= th('dash.registrar') ?></dt><dd class="mono"><?= h($c['clID']) ?></dd>
      <dt><?= th('common.created') ?></dt><dd><?= fmt_date($c['crDate']) ?></dd>
      <dt><?= th('common.updated') ?></dt><dd><?= fmt_date($c['upDate']) ?></dd>
    </dl>
  </div>
  <div class="card">
    <h2><?= th('con.registrant_data') ?></h2>
    <?php if ($c['registrant']): ?>
      <dl class="kv">
        <dt><?= th('con.nationality') ?></dt><dd><?= h($c['registrant']['nationality']) ?></dd>
        <dt><?= th('con.entity_type') ?></dt><dd><?= h(entity_types()[$c['registrant']['entityType']] ?? $c['registrant']['entityType']) ?></dd>
        <dt><?= th('con.regcode_short') ?></dt><dd class="mono"><?= h($c['registrant']['regCode']) ?></dd>
      </dl>
    <?php else: ?><p class="mute"><?= th('con.not_registrant') ?></p><?php endif; ?>
    <h2 style="margin-top:14px"><?= th('con.linked_domains') ?></h2>
    <?php if ($domains): foreach ($domains as $dn): ?>
      <a href="<?= url('domains/info', ['name' => $dn]) ?>"><?= h($dn) ?></a><br>
    <?php endforeach; else: ?><p class="mute"><?= th('common.none') ?></p><?php endif; ?>
    <?php require __DIR__ . '/partials_response.php'; ?>
  </div>
</div>
<?php if (can('operator') && $c['clID'] === epp_cfg()['clid']): ?>
<p class="row">
  <a class="btn shrink" href="<?= url('contacts/edit', ['id' => $c['id']]) ?>"><?= th('common.edit') ?></a>
  <form method="post" action="<?= url('contacts/delete') ?>" class="shrink" data-confirm="<?= th('con.delete_confirm', ['id' => $c['id']]) ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= h($c['id']) ?>"><button class="danger"><?= th('common.delete') ?></button></form>
</p>
<?php endif; ?>
