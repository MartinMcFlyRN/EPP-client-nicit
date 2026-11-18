<h1><?= th('chk.title') ?></h1>
<div class="card">
  <form method="get" action="<?= url() ?>">
    <input type="hidden" name="r" value="domains/check">
    <label><?= th('chk.names') ?></label>
    <textarea name="names"><?= h($names) ?></textarea>
    <p><button><?= th('nav.check') ?></button></p>
  </form>
</div>
<?php if ($results !== null): ?>
<div class="card">
  <table><tr><th><?= th('dom.domain') ?></th><th><?= th('log.result') ?></th><th><?= th('chk.reason') ?></th><th></th></tr>
  <?php foreach ($results as $r): ?>
    <tr><td><?= h($r['name']) ?></td>
      <td><?= $r['avail'] ? '<span class="avail">' . th('chk.available') . '</span>' : '<span class="taken">' . th('chk.not_available') . '</span>' ?></td>
      <td class="small"><?= h($r['reason']) ?></td>
      <td><?php if ($r['avail'] && can('operator')): ?><a href="<?= url('domains/create', ['name' => $r['name']]) ?>"><?= th('chk.register') ?></a>
          <?php elseif (!$r['avail']): ?><a href="<?= url('domains/info', ['name' => $r['name']]) ?>">Info →</a><?php endif; ?></td></tr>
  <?php endforeach; ?></table>
</div>
<?php endif; ?>
