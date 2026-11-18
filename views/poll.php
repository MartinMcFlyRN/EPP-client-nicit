<h1><?= th('poll.title') ?></h1>
<div class="card">
  <p><?= th('poll.queued') ?> <strong><?= $state['msgq'] === null ? '—' : (int)$state['msgq'] ?></strong></p>
  <?php if (can('operator')): ?>
  <div class="row">
    <form method="post" action="<?= url('poll/req') ?>" class="shrink"><?= csrf_field() ?><button><?= th('poll.read_next') ?></button></form>
    <form method="post" action="<?= url('poll/req') ?>" class="shrink"><?= csrf_field() ?><input type="hidden" name="ack" value="1"><button class="sec"><?= th('poll.read_ack') ?></button></form>
  </div>
  <p class="help"><?= t('poll.cron_help_html') ?></p>
  <?php endif; ?>
</div>
<div class="card">
  <table><tr><th><?= th('poll.msg_id') ?></th><th><?= th('common.date') ?></th><th><?= th('poll.message') ?></th><th>Ack</th><th></th></tr>
  <?php foreach ($rows as $p): ?>
    <tr><td class="mono"><?= h($p['msg_id']) ?></td><td class="small"><?= fmt_date($p['q_date']) ?></td>
      <td><a href="<?= url('poll/view', ['id' => $p['id']]) ?>"><?= h($p['msg']) ?></a></td>
      <td class="small"><?= $p['acked_at'] ? fmt_date($p['acked_at']) . ' · ' . h($p['acked_by']) : '<span class="badge warn">' . th('poll.to_ack') . '</span>' ?></td>
      <td><?php if (!$p['acked_at'] && can('operator')): ?>
        <form method="post" action="<?= url('poll/ack') ?>"><?= csrf_field() ?><input type="hidden" name="msg_id" value="<?= h($p['msg_id']) ?>"><button class="small sec">Ack</button></form>
      <?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="mute"><?= th('poll.none') ?></td></tr><?php endif; ?>
  </table>
</div>
