<h1><?= th('nav.domains') ?></h1>
<div class="card">
  <form method="get" action="<?= url() ?>" class="row">
    <input type="hidden" name="r" value="domains">
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="<?= th('dom.search_ph') ?>">
    <input type="hidden" name="sort" value="<?= h($sort) ?>"><input type="hidden" name="dir" value="<?= h($dir) ?>">
    <button class="shrink"><?= th('common.search') ?></button>
    <?php if (can('operator')): ?><a class="btn shrink" href="<?= url('domains/create') ?>"><?= th('dom.new') ?></a>
      <a class="btn sec shrink" href="<?= url('import') ?>"><?= th('imp.button') ?></a><?php endif; ?>
  </form>
  <p class="help"><?= th($q !== '' ? 'dom.count_found' : 'dom.count', ['n' => count($rows), 'total' => (int)$total]) ?><?= count($rows) >= 1000 ? ' ' . th('common.first_1000') : '' ?>
    <?= th('common.click_to_sort') ?> <?= th('dom.list_help') ?></p>
  <table>
    <?php $k = ['q' => $q]; ?>
    <tr><?= sort_th('domains', 'name', t('dom.domain'), $sort, $dir, $k) ?><?= sort_th('domains', 'registrant', t('dom.registrant'), $sort, $dir, $k) ?>
      <?= sort_th('domains', 'status', t('account.status'), $sort, $dir, $k) ?><?= sort_th('domains', 'cl_id', t('dash.registrar'), $sort, $dir, $k) ?>
      <?= sort_th('domains', 'ex_date', t('dom.expiry'), $sort, $dir, $k) ?><?= sort_th('domains', 'updated_at', t('common.updated'), $sort, $dir, $k) ?></tr>
    <?php foreach ($rows as $d): ?>
      <tr>
        <td><a href="<?= url('domains/info', ['name' => $d['name']]) ?>"><?= h($d['name']) ?></a></td>
        <td class="mono"><a href="<?= url('contacts/info', ['id' => $d['registrant']]) ?>"><?= h($d['registrant']) ?></a></td>
        <td><?php foreach (array_filter(explode(',', (string)$d['status'])) as $s) echo status_badge($s); ?></td>
        <td class="mono small"><?= h($d['cl_id']) ?></td>
        <td><?= fmt_date($d['ex_date']) ?></td>
        <td class="small mute"><?= fmt_date($d['updated_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="mute"><?= th('dom.none') ?></td></tr><?php endif; ?>
  </table>
</div>
