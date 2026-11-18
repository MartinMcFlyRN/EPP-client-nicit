<h1>Contatti</h1>
<div class="card">
  <form method="get" action="<?= url() ?>" class="row">
    <input type="hidden" name="r" value="contacts">
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="Cerca ID, nome, organizzazione, email">
    <button class="shrink">Cerca</button>
    <a class="btn sec shrink" href="<?= url('contacts/check') ?>">Check ID</a>
    <?php if (can('operator')): ?>
      <a class="btn shrink" href="<?= url('contacts/create', ['registrant' => 1]) ?>">Nuovo registrante</a>
      <a class="btn sec shrink" href="<?= url('contacts/create') ?>">Nuovo admin/tech</a>
    <?php endif; ?>
  </form>
  <table>
    <tr><th>ID</th><th>Nome</th><th>Organizzazione</th><th>Email</th><th>Tipo</th></tr>
    <?php foreach ($rows as $c): ?>
      <tr><td class="mono"><a href="<?= url('contacts/info', ['id' => $c['id']]) ?>"><?= h($c['id']) ?></a></td>
        <td><?= h($c['name']) ?></td><td><?= h($c['org']) ?></td><td><?= h($c['email']) ?></td>
        <td><?= $c['is_registrant'] ? '<span class="badge client">registrante</span>' : '<span class="badge">contatto</span>' ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="mute">Nessun contatto in cache.</td></tr><?php endif; ?>
  </table>
</div>
