<h1>Registra nuovo dominio</h1>
<form method="post" action="<?= url('domains/create') ?>" class="card" style="max-width:820px">
  <?= csrf_field() ?>
  <div class="row">
    <div><label>Nome a dominio</label><input type="text" name="name" value="<?= h($f['name'] ?? '') ?>" required placeholder="esempio.it"></div>
  </div>
  <div class="row">
    <div><label>Registrante (ID)</label><input type="text" name="registrant" value="<?= h($f['registrant'] ?? '') ?>" required></div>
    <div><label>Admin (ID)</label><input type="text" name="admin" value="<?= h($f['admin'] ?? '') ?>" required></div>
  </div>
  <label>Tech (ID, uno per riga, max 6)</label>
  <textarea name="techs" style="min-height:50px"><?= h($f['techs'] ?? '') ?></textarea>
  <label>Nameserver (2–6, uno per riga: host [ip...])</label>
  <textarea name="ns" placeholder="ns1.esempio.it 192.0.2.1&#10;ns2.provider.net"><?= h($f['ns'] ?? '') ?></textarea>
  <p class="help">I DNS devono essere già configurati correttamente: il Registro esegue i controlli tecnici al momento della create.</p>
  <label>Record DS (opzionale)</label>
  <textarea name="ds" style="min-height:50px" placeholder="12345 13 2 ABCDEF..."><?= h($f['ds'] ?? '') ?></textarea>
  <label>AuthInfo</label>
  <div class="row"><input type="text" id="ai" name="authinfo" value="<?= h($f['authinfo'] ?? '') ?>"><button class="sec shrink" data-gen="ai">Genera</button></div>
  <p class="help">Durata: 1 anno (unica consentita dal Registro .it).</p>
  <p><button>Registra</button> <a href="<?= url('contacts/create', ['registrant' => 1]) ?>" target="_blank">Crea registrante in un'altra scheda</a></p>
  <?php require __DIR__ . '/partials_response.php'; ?>
</form>
