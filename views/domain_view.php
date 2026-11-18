<?php
use NicEpp\Epp\Commands;
$op = can('operator') && $ours;
?>
<h1><?= h($name) ?></h1>

<?php if (!$d): ?>
  <div class="card">
    <p>Impossibile leggere il dominio. Se è di un altro registrar, puoi passare l'AuthInfo per vedere i dettagli completi.</p>
    <form method="get" action="<?= url() ?>" class="row"><input type="hidden" name="r" value="domains/info"><input type="hidden" name="name" value="<?= h($name) ?>">
      <input type="text" name="auth" placeholder="AuthInfo"><button class="shrink">Info con AuthInfo</button></form>
    <?php require __DIR__ . '/partials_response.php'; ?>
  </div>
  <?php return; ?>
<?php endif; ?>

<?php if (!$ours): ?><div class="flash err">Dominio gestito da un altro registrar (<?= h($d['clID']) ?>): modifiche non disponibili. <a href="<?= url('domains/transfer', ['name' => $name]) ?>">Trasferisci →</a></div><?php endif; ?>

<div class="grid g2">
  <div class="card">
    <h2>Dati</h2>
    <dl class="kv">
      <dt>Stato</dt><dd><?php foreach ($d['status'] as $s) echo status_badge($s); foreach ($d['rgp'] as $s) echo status_badge($s); ?></dd>
      <dt>Registrante</dt><dd class="mono"><a href="<?= url('contacts/info', ['id' => $d['registrant']]) ?>"><?= h($d['registrant']) ?></a></dd>
      <?php foreach ($d['contacts'] as $c): ?>
        <dt><?= h($c['type']) ?></dt><dd class="mono"><a href="<?= url('contacts/info', ['id' => $c['id']]) ?>"><?= h($c['id']) ?></a></dd>
      <?php endforeach; ?>
      <dt>Registrar</dt><dd class="mono"><?= h($d['clID']) ?></dd>
      <dt>Creato</dt><dd><?= fmt_date($d['crDate']) ?> <span class="mute small"><?= h($d['crID']) ?></span></dd>
      <dt>Aggiornato</dt><dd><?= fmt_date($d['upDate']) ?></dd>
      <dt>Scadenza</dt><dd><strong><?= fmt_date($d['exDate']) ?></strong></dd>
      <?php if ($d['trDate']): ?><dt>Trasferito</dt><dd><?= fmt_date($d['trDate']) ?></dd><?php endif; ?>
      <dt>ROID</dt><dd class="mono small"><?= h($d['roid']) ?></dd>
      <?php if ($d['authInfo']): ?><dt>AuthInfo</dt><dd class="mono"><?= h($d['authInfo']) ?></dd><?php endif; ?>
    </dl>
  </div>
  <div class="card">
    <h2>Nameserver</h2>
    <table><?php foreach ($d['ns'] as $n): ?><tr><td class="mono"><?= h($n['host']) ?></td><td class="mono small"><?= h(implode(' ', $n['ips'])) ?></td></tr><?php endforeach; ?></table>
    <h2 style="margin-top:14px">DNSSEC</h2>
    <?php if (!$d['ds']): ?><p class="mute">Nessun record DS</p><?php else: ?>
      <table><tr><th>KeyTag</th><th>Alg</th><th>DigestType</th><th>Digest</th></tr>
      <?php foreach ($d['ds'] as $x): ?><tr class="mono small"><td><?= $x['keyTag'] ?></td><td><?= $x['alg'] ?></td><td><?= $x['digestType'] ?></td><td style="word-break:break-all"><?= h($x['digest']) ?></td></tr><?php endforeach; ?></table>
    <?php endif; ?>
    <?php if ($d['extension']): ?><details><summary>Estensioni (extdom / rgp / secDNS)</summary><pre class="xml"><?= h($d['extension']) ?></pre></details><?php endif; ?>
    <?php require __DIR__ . '/partials_response.php'; ?>
  </div>
</div>

<?php if ($op): ?>
<?php
  $admins = implode(' ', array_column(array_filter($d['contacts'], fn($c) => $c['type'] === 'admin'), 'id'));
  $techs = implode("\n", array_column(array_filter($d['contacts'], fn($c) => $c['type'] === 'tech'), 'id'));
  $form = fn(string $action) => '<form method="post" action="' . url('domains/update') . '">' . csrf_field()
      . '<input type="hidden" name="name" value="' . h($name) . '"><input type="hidden" name="action" value="' . $action . '">';
?>
<h2>Operazioni</h2>
<div class="grid g2">
  <div class="card">
    <h2>Nameserver</h2>
    <?= $form('ns') ?>
      <textarea name="ns"><?= h(ns_to_lines($d['ns'])) ?></textarea>
      <p class="help">Una riga per host: <code>ns1.esempio.it 192.0.2.1 2001:db8::1</code>. Glue IP solo per host sotto il dominio. Da 2 a 6 NS. Il Registro verifica la configurazione DNS.</p>
      <button>Aggiorna NS</button></form>
  </div>
  <div class="card">
    <h2>Contatti admin / tech</h2>
    <?= $form('contacts') ?>
      <label>Admin</label><input type="text" name="admin" value="<?= h($admins) ?>">
      <label>Tech (uno per riga, max 6)</label><textarea name="techs" style="min-height:60px"><?= h($techs) ?></textarea>
      <p><button>Aggiorna contatti</button></p></form>
  </div>
  <div class="card">
    <h2>Stati client</h2>
    <?= $form('status') ?>
      <?php foreach (Commands::DOMAIN_CLIENT_STATUSES as $s): ?>
        <label style="font-weight:400"><input type="checkbox" name="status[]" value="<?= $s ?>" <?= in_array($s, $d['status'], true) ? 'checked' : '' ?>> <?= $s ?></label>
      <?php endforeach; ?>
      <p class="help">Il cambio di stato non può essere combinato con altre modifiche. clientHold rende il dominio inattivo nel DNS.</p>
      <button>Aggiorna stati</button></form>
  </div>
  <div class="card">
    <h2>AuthInfo</h2>
    <?= $form('authinfo') ?>
      <div class="row"><input type="text" id="ai1" name="authinfo" placeholder="vuoto = generato"><button class="sec shrink" data-gen="ai1">Genera</button></div>
      <p class="help">8–32 caratteri. Viene mostrato dopo il salvataggio.</p>
      <button>Cambia AuthInfo</button></form>
  </div>
  <div class="card">
    <h2>Modifica registrante</h2>
    <?= $form('registrant') ?>
      <label>Nuovo registrante (ID)</label><input type="text" name="registrant" required>
      <label>Nuovo admin (opzionale)</label><input type="text" name="admin" placeholder="per persona fisica, se vuoto il Registro usa il registrante">
      <label>Nuovo AuthInfo (obbligatorio, diverso dall'attuale)</label>
      <div class="row"><input type="text" id="ai2" name="authinfo" placeholder="vuoto = generato"><button class="sec shrink" data-gen="ai2">Genera</button></div>
      <p class="help">Il nuovo registrante deve esistere con i dati di registrante completi; se non è persona fisica il consenso alla pubblicazione deve essere true.</p>
      <button>Cambia registrante</button></form>
  </div>
<?php if (dnssec_enabled()): ?>
  <div class="card">
    <h2>DNSSEC (record DS)</h2>
    <?= $form('dnssec') ?>
      <textarea name="ds"><?= h(ds_to_lines($d['ds'])) ?></textarea>
      <p class="help">Una riga per DS: <code>keyTag alg digestType digest</code> (accetta anche il formato <code>esempio.it. IN DS 12345 13 2 ABCD…</code>). Svuota per rimuovere tutti i DS.</p>
      <button>Aggiorna DS</button></form>
  </div>
<?php endif; ?>
  <div class="card danger-zone">
    <h2>Cancellazione / restore</h2>
    <form method="post" action="<?= url('domains/delete') ?>" data-confirm="Confermi la cancellazione di <?= h($name) ?>?">
      <?= csrf_field() ?><input type="hidden" name="name" value="<?= h($name) ?>">
      <label>Scrivi il nome del dominio per confermare</label>
      <div class="row"><input type="text" name="confirm" autocomplete="off"><button class="danger shrink">Cancella dominio</button></div>
    </form>
    <?php if (in_array('pendingDelete', $d['status'], true) || in_array('redemptionPeriod', $d['rgp'], true)): ?>
      <form method="post" action="<?= url('domains/restore') ?>" style="margin-top:12px" data-confirm="Restore di <?= h($name) ?>? L'operazione è a pagamento.">
        <?= csrf_field() ?><input type="hidden" name="name" value="<?= h($name) ?>"><button class="sec">Restore (rgp)</button></form>
    <?php endif; ?>
  </div>
  <?php if (in_array('pendingTransfer', $d['status'], true)): ?>
  <div class="card">
    <h2>Trasferimento in corso</h2>
    <p>È stato richiesto il trasferimento di questo dominio verso un altro registrar.</p>
    <?php foreach (['query' => 'Stato', 'approve' => 'Approva', 'reject' => 'Rifiuta'] as $o => $l): ?>
      <form method="post" action="<?= url('domains/transfer') ?>" class="inline"><?= csrf_field() ?>
        <input type="hidden" name="op" value="<?= $o ?>"><input type="hidden" name="name" value="<?= h($name) ?>">
        <input type="hidden" name="authinfo" value="<?= h($d['authInfo']) ?>">
        <button class="<?= $o === 'reject' ? 'danger' : 'sec' ?>"><?= $l ?></button></form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
