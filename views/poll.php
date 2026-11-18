<h1>Coda messaggi (poll)</h1>
<div class="card">
  <p>Messaggi in coda sul server (ultimo valore noto): <strong><?= $state['msgq'] === null ? '—' : (int)$state['msgq'] ?></strong></p>
  <?php if (can('operator')): ?>
  <div class="row">
    <form method="post" action="<?= url('poll/req') ?>" class="shrink"><?= csrf_field() ?><button>Leggi prossimo messaggio</button></form>
    <form method="post" action="<?= url('poll/req') ?>" class="shrink"><?= csrf_field() ?><input type="hidden" name="ack" value="1"><button class="sec">Leggi e conferma (ack)</button></form>
  </div>
  <p class="help">Il cron <code>bin/poll-cron.php</code> scarica e conferma automaticamente tutta la coda salvandola qui.</p>
  <?php endif; ?>
</div>
<div class="card">
  <table><tr><th>ID msg</th><th>Data</th><th>Messaggio</th><th>Ack</th><th></th></tr>
  <?php foreach ($rows as $p): ?>
    <tr><td class="mono"><?= h($p['msg_id']) ?></td><td class="small"><?= fmt_date($p['q_date']) ?></td>
      <td><a href="<?= url('poll/view', ['id' => $p['id']]) ?>"><?= h($p['msg']) ?></a></td>
      <td class="small"><?= $p['acked_at'] ? fmt_date($p['acked_at']) . ' · ' . h($p['acked_by']) : '<span class="badge warn">da confermare</span>' ?></td>
      <td><?php if (!$p['acked_at'] && can('operator')): ?>
        <form method="post" action="<?= url('poll/ack') ?>"><?= csrf_field() ?><input type="hidden" name="msg_id" value="<?= h($p['msg_id']) ?>"><button class="small sec">Ack</button></form>
      <?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="mute">Nessun messaggio salvato.</td></tr><?php endif; ?>
  </table>
</div>
