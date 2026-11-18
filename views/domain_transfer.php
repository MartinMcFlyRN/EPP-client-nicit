<h1>Trasferimenti</h1>
<div class="grid g2">
<form method="post" action="<?= url('domains/transfer') ?>" class="card">
  <?= csrf_field() ?>
  <label>Operazione</label>
  <select name="op">
    <?php foreach (['request' => 'request — richiedi trasferimento verso di noi', 'query' => 'query — stato del trasferimento',
                    'cancel' => 'cancel — annulla la nostra richiesta', 'approve' => 'approve — approva (registrar uscente)',
                    'reject' => 'reject — rifiuta (registrar uscente)'] as $k => $l): ?>
      <option value="<?= $k ?>" <?= ($f['op'] ?? '') === $k ? 'selected' : '' ?>><?= h($l) ?></option>
    <?php endforeach; ?>
  </select>
  <label>Dominio</label><input type="text" name="name" value="<?= h($f['name'] ?? '') ?>" required>
  <label>AuthInfo del dominio</label><input type="text" name="authinfo" value="<?= h($f['authinfo'] ?? '') ?>">
  <label style="font-weight:400;margin-top:14px"><input type="checkbox" name="trade" value="1" data-toggle="tradebox" <?= !empty($f['trade']) ? 'checked' : '' ?>> Transfer-Trade: cambia anche il registrante</label>
  <div id="tradebox">
    <label>Nuovo registrante (ID)</label><input type="text" name="new_registrant" value="<?= h($f['new_registrant'] ?? '') ?>">
    <label>Nuovo AuthInfo</label>
    <div class="row"><input type="text" id="nai" name="new_authinfo" value="<?= h($f['new_authinfo'] ?? '') ?>" placeholder="vuoto = generato"><button class="sec shrink" data-gen="nai">Genera</button></div>
  </div>
  <p><button>Invia</button></p>
</form>
<div class="card">
  <h2>Esito</h2>
  <?php if ($data): ?>
    <dl class="kv">
      <dt>Dominio</dt><dd><?= h($data['name']) ?></dd>
      <dt>Stato</dt><dd><?= status_badge($data['trStatus']) ?></dd>
      <dt>Richiedente</dt><dd class="mono"><?= h($data['reID']) ?> · <?= fmt_date($data['reDate']) ?></dd>
      <dt>Uscente</dt><dd class="mono"><?= h($data['acID']) ?> · <?= fmt_date($data['acDate']) ?></dd>
      <?php if ($data['exDate']): ?><dt>Scadenza</dt><dd><?= fmt_date($data['exDate']) ?></dd><?php endif; ?>
    </dl>
  <?php else: ?>
    <p class="mute">Nessuna operazione eseguita.</p>
  <?php endif; ?>
  <?php require __DIR__ . '/partials_response.php'; ?>
  <p class="help">Dopo la richiesta il dominio va in pendingTransfer: il registrar uscente può approvare o rifiutare; senza risposta il trasferimento viene approvato automaticamente. Gli esiti arrivano anche nella coda Poll.</p>
</div>
</div>
