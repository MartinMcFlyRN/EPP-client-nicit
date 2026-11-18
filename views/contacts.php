<h1>Contatti</h1>
<div class="card">
  <form method="get" action="<?= url() ?>" class="row">
    <input type="hidden" name="r" value="contacts">
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="Cerca ID, nome, organizzazione, email">
    <input type="hidden" name="sort" value="<?= h($sort) ?>"><input type="hidden" name="dir" value="<?= h($dir) ?>">
    <button class="shrink">Cerca</button>
    <a class="btn sec shrink" href="<?= url('contacts/check') ?>">Check ID</a>
    <?php if (can('operator')): ?>
      <a class="btn shrink" href="<?= url('contacts/create', ['registrant' => 1]) ?>">Nuovo registrante</a>
      <a class="btn sec shrink" href="<?= url('contacts/create') ?>">Nuovo admin/tech</a>
    <?php endif; ?>
  </form>
  <p class="help"><?= count($rows) ?> <?= $q !== '' ? 'trovati' : 'contatti' ?> su <?= (int)$total ?> in cache<?= count($rows) >= 1000 ? ' (mostrati i primi 1000: restringi la ricerca)' : '' ?>. Clicca sulle intestazioni per ordinare.</p>
  <table>
    <?php $k = ['q' => $q]; ?>
    <tr><?= sort_th('contacts', 'id', 'ID', $sort, $dir, $k) ?><?= sort_th('contacts', 'name', 'Nome', $sort, $dir, $k) ?>
      <?= sort_th('contacts', 'org', 'Organizzazione', $sort, $dir, $k) ?><?= sort_th('contacts', 'email', 'Email', $sort, $dir, $k) ?>
      <?= sort_th('contacts', 'is_registrant', 'Tipo', $sort, $dir, $k) ?><?= sort_th('contacts', 'updated_at', 'Aggiornato', $sort, $dir, $k) ?></tr>
    <?php foreach ($rows as $c): ?>
      <tr><td class="mono"><a href="<?= url('contacts/info', ['id' => $c['id']]) ?>"><?= h($c['id']) ?></a></td>
        <td><?= h($c['name']) ?></td><td><?= h($c['org']) ?></td><td><?= h($c['email']) ?></td>
        <td><?= $c['is_registrant'] ? '<span class="badge client">registrante</span>' : '<span class="badge">contatto</span>' ?></td>
        <td class="small mute"><?= fmt_date($c['updated_at']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="mute">Nessun contatto in cache.</td></tr><?php endif; ?>
  </table>
</div>
