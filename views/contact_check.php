<h1><?= th('con.check_title') ?></h1>
<div class="card">
  <form method="get" action="<?= url() ?>">
    <input type="hidden" name="r" value="contacts/check">
    <textarea name="ids" placeholder="<?= th('con.check_ph') ?>"><?= h($ids) ?></textarea>
    <p><button><?= th('nav.check') ?></button></p>
  </form>
  <?php if ($results !== null): ?>
  <table><tr><th>ID</th><th><?= th('log.result') ?></th><th><?= th('chk.reason') ?></th></tr>
    <?php foreach ($results as $r): ?>
      <tr><td class="mono"><?= h($r['id']) ?></td>
        <td><?= $r['avail'] ? '<span class="avail">' . th('con.free') . '</span>' : '<a class="taken" href="' . url('contacts/info', ['id' => $r['id']]) . '">' . th('con.in_use') . '</a>' ?></td>
        <td class="small"><?= h($r['reason']) ?></td></tr>
    <?php endforeach; ?></table>
  <?php endif; ?>
</div>
