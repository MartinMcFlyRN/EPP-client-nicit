<h1>Contatto <?= h($id) ?></h1>
<?php if (!$c): ?>
  <div class="card"><p>Contatto non leggibile.</p><?php require __DIR__ . '/partials_response.php'; ?></div>
  <?php return; ?>
<?php endif; ?>
<div class="grid g2">
  <div class="card">
    <h2>Dati</h2>
    <dl class="kv">
      <dt>Nome</dt><dd><?= h($c['name']) ?></dd>
      <?php if ($c['org']): ?><dt>Organizzazione</dt><dd><?= h($c['org']) ?></dd><?php endif; ?>
      <dt>Indirizzo</dt><dd><?= nl2br(h(implode("\n", $c['street']))) ?><br><?= h(trim("{$c['pc']} {$c['city']} " . ($c['sp'] ? "({$c['sp']})" : ''))) ?> — <?= h($c['cc']) ?></dd>
      <dt>Telefono</dt><dd class="mono"><?= h($c['voice']) ?></dd>
      <?php if ($c['fax']): ?><dt>Fax</dt><dd class="mono"><?= h($c['fax']) ?></dd><?php endif; ?>
      <dt>Email</dt><dd><?= h($c['email']) ?></dd>
      <dt>Consenso pubbl.</dt><dd><?= $c['consent'] ? 'sì' : 'no' ?></dd>
      <dt>Stato</dt><dd><?php foreach ($c['status'] as $s) echo status_badge($s); ?></dd>
      <dt>Registrar</dt><dd class="mono"><?= h($c['clID']) ?></dd>
      <dt>Creato</dt><dd><?= fmt_date($c['crDate']) ?></dd>
      <dt>Aggiornato</dt><dd><?= fmt_date($c['upDate']) ?></dd>
    </dl>
  </div>
  <div class="card">
    <h2>Dati registrante</h2>
    <?php if ($c['registrant']): ?>
      <dl class="kv">
        <dt>Nazionalità</dt><dd><?= h($c['registrant']['nationality']) ?></dd>
        <dt>Tipo soggetto</dt><dd><?= h(ENTITY_TYPES[$c['registrant']['entityType']] ?? $c['registrant']['entityType']) ?></dd>
        <dt>CF / P.IVA</dt><dd class="mono"><?= h($c['registrant']['regCode']) ?></dd>
      </dl>
    <?php else: ?><p class="mute">Non è un registrante (contatto admin/tech).</p><?php endif; ?>
    <h2 style="margin-top:14px">Domini in cache collegati</h2>
    <?php if ($domains): foreach ($domains as $dn): ?>
      <a href="<?= url('domains/info', ['name' => $dn]) ?>"><?= h($dn) ?></a><br>
    <?php endforeach; else: ?><p class="mute">Nessuno</p><?php endif; ?>
    <?php require __DIR__ . '/partials_response.php'; ?>
  </div>
</div>
<?php if (can('operator') && $c['clID'] === epp_cfg()['clid']): ?>
<p class="row">
  <a class="btn shrink" href="<?= url('contacts/edit', ['id' => $c['id']]) ?>">Modifica</a>
  <form method="post" action="<?= url('contacts/delete') ?>" class="shrink" data-confirm="Cancellare il contatto <?= h($c['id']) ?>?">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= h($c['id']) ?>"><button class="danger">Cancella</button></form>
</p>
<?php endif; ?>
