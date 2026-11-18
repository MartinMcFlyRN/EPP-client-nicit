<h1>Check ID contatti</h1>
<div class="card">
  <form method="get" action="<?= url() ?>">
    <input type="hidden" name="r" value="contacts/check">
    <textarea name="ids" placeholder="ID uno per riga"><?= h($ids) ?></textarea>
    <p><button>Check</button></p>
  </form>
  <?php if ($results !== null): ?>
  <table><tr><th>ID</th><th>Esito</th><th>Motivo</th></tr>
    <?php foreach ($results as $r): ?>
      <tr><td class="mono"><?= h($r['id']) ?></td>
        <td><?= $r['avail'] ? '<span class="avail">libero</span>' : '<a class="taken" href="' . url('contacts/info', ['id' => $r['id']]) . '">in uso →</a>' ?></td>
        <td class="small"><?= h($r['reason']) ?></td></tr>
    <?php endforeach; ?></table>
  <?php endif; ?>
</div>
