<h1><?= th('nav.contacts') ?></h1>
<div class="card">
  <form method="get" action="<?= url() ?>" class="row">
    <input type="hidden" name="r" value="contacts">
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="<?= th('con.list_search_ph') ?>">
    <input type="hidden" name="sort" value="<?= h($sort) ?>"><input type="hidden" name="dir" value="<?= h($dir) ?>">
    <button class="shrink"><?= th('common.search') ?></button>
    <a class="btn sec shrink" href="<?= url('contacts/check') ?>"><?= th('con.check_id') ?></a>
    <?php if (can('operator')): ?>
      <a class="btn shrink" href="<?= url('contacts/create', ['registrant' => 1]) ?>"><?= th('con.new_registrant') ?></a>
      <a class="btn sec shrink" href="<?= url('contacts/create') ?>"><?= th('con.new_admin_tech') ?></a>
    <?php endif; ?>
  </form>
  <p class="help"><?= th($q !== '' ? 'con.count_found' : 'con.count', ['n' => count($rows), 'total' => (int)$total]) ?><?= count($rows) >= 1000 ? ' ' . th('common.first_1000') : '' ?> <?= th('common.click_to_sort') ?></p>
  <table>
    <?php $k = ['q' => $q]; ?>
    <tr><?= sort_th('contacts', 'id', 'ID', $sort, $dir, $k) ?><?= sort_th('contacts', 'name', t('con.name'), $sort, $dir, $k) ?>
      <?= sort_th('contacts', 'org', t('con.org'), $sort, $dir, $k) ?><?= sort_th('contacts', 'email', 'Email', $sort, $dir, $k) ?>
      <?= sort_th('contacts', 'is_registrant', t('con.type'), $sort, $dir, $k) ?><?= sort_th('contacts', 'updated_at', t('common.updated'), $sort, $dir, $k) ?></tr>
    <?php foreach ($rows as $c): ?>
      <tr><td class="mono"><a href="<?= url('contacts/info', ['id' => $c['id']]) ?>"><?= h($c['id']) ?></a></td>
        <td><?= h($c['name']) ?></td><td><?= h($c['org']) ?></td><td><?= h($c['email']) ?></td>
        <td><?= $c['is_registrant'] ? '<span class="badge client">' . th('con.registrant_badge') . '</span>' : '<span class="badge">' . th('con.contact_badge') . '</span>' ?></td>
        <td class="small mute"><?= fmt_date($c['updated_at']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="mute"><?= th('con.none') ?></td></tr><?php endif; ?>
  </table>
</div>
