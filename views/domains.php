<h1>Domini</h1>
<div class="card">
  <form method="get" action="<?= url() ?>" class="row">
    <input type="hidden" name="r" value="domains">
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="Cerca per nome o registrante">
    <select name="order" class="shrink" style="width:auto"><option value="name">per nome</option><option value="ex_date" <?= in('order') === 'ex_date' ? 'selected' : '' ?>>per scadenza</option></select>
    <button class="shrink">Cerca</button>
    <?php if (can('operator')): ?><a class="btn shrink" href="<?= url('domains/create') ?>">Nuovo dominio</a>
      <a class="btn sec shrink" href="<?= url('import') ?>">Importa dal Registro</a><?php endif; ?>
  </form>
  <p class="help">Elenco dalla cache locale: si aggiorna a ogni Info/Create. Per un dominio non in elenco usa Info, per caricarne molti usa «Importa dal Registro».</p>
  <table>
    <tr><th>Dominio</th><th>Registrante</th><th>Stato</th><th>Registrar</th><th>Scadenza</th><th>Aggiornato</th></tr>
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
    <?php if (!$rows): ?><tr><td colspan="6" class="mute">Nessun dominio in cache.</td></tr><?php endif; ?>
  </table>
</div>
