<h1>Check disponibilità domini</h1>
<div class="card">
  <form method="get" action="<?= url() ?>">
    <input type="hidden" name="r" value="domains/check">
    <label>Nomi (uno per riga, o separati da virgola)</label>
    <textarea name="names"><?= h($names) ?></textarea>
    <p><button>Check</button></p>
  </form>
</div>
<?php if ($results !== null): ?>
<div class="card">
  <table><tr><th>Dominio</th><th>Esito</th><th>Motivo</th><th></th></tr>
  <?php foreach ($results as $r): ?>
    <tr><td><?= h($r['name']) ?></td>
      <td><?= $r['avail'] ? '<span class="avail">disponibile</span>' : '<span class="taken">non disponibile</span>' ?></td>
      <td class="small"><?= h($r['reason']) ?></td>
      <td><?php if ($r['avail'] && can('operator')): ?><a href="<?= url('domains/create', ['name' => $r['name']]) ?>">Registra →</a>
          <?php elseif (!$r['avail']): ?><a href="<?= url('domains/info', ['name' => $r['name']]) ?>">Info →</a><?php endif; ?></td></tr>
  <?php endforeach; ?></table>
</div>
<?php endif; ?>
