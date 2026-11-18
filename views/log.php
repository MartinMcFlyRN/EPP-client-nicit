<h1>Log comandi EPP</h1>
<div class="card">
  <form method="get" action="<?= url() ?>" class="row">
    <input type="hidden" name="r" value="log">
    <input type="search" name="q" value="<?= h($f['q']) ?>" placeholder="Oggetto, clTRID, svTRID">
    <input type="text" name="command" value="<?= h($f['command']) ?>" placeholder="comando (es. domain:create)">
    <label class="shrink" style="font-weight:400"><input type="checkbox" name="errors" value="1" <?= $f['errors'] ? 'checked' : '' ?>> solo errori</label>
    <button class="shrink">Filtra</button>
  </form>
  <table>
    <tr><th>Data</th><th>Utente</th><th>Comando</th><th>Oggetto</th><th>Esito</th><th>ms</th></tr>
    <?php foreach ($rows as $l): ?>
      <tr><td class="small"><a href="<?= url('log/view', ['id' => $l['id']]) ?>"><?= fmt_date($l['created_at']) ?></a></td>
        <td class="small"><?= h($l['username']) ?></td><td class="mono small"><?= h($l['command']) ?></td>
        <td class="small"><?= h($l['object']) ?></td>
        <td class="small"><?= $l['error'] ? '<span class="badge bad">' . h(mb_substr($l['error'], 0, 60)) . '</span>'
            : '<span class="badge ' . ($l['result_code'] < 2000 ? 'ok' : 'bad') . '">' . h($l['result_code']) . '</span> ' . h($l['result_msg']) ?></td>
        <td class="small mute"><?= (int)$l['duration_ms'] ?></td></tr>
    <?php endforeach; ?>
  </table>
  <div class="pager">
    <?php if ($page > 1): ?><a href="<?= url('log', $f + ['page' => $page - 1]) ?>">← Precedenti</a><?php endif; ?>
    <?php if (count($rows) === 100): ?><a href="<?= url('log', $f + ['page' => $page + 1]) ?>">Successivi →</a><?php endif; ?>
  </div>
</div>
