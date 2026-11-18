<h1>Dashboard</h1>
<div class="grid g3">
  <div class="card">
    <h2>Sessione EPP</h2>
    <dl class="kv">
      <dt>Server</dt><dd class="mono"><?= h(cfg('epp.url')) ?></dd>
      <dt>Registrar</dt><dd class="mono"><?= h(cfg('epp.clid')) ?></dd>
      <dt>Stato</dt><dd><?= $state['logged_in'] ? '<span class="badge ok">connesso</span>' : '<span class="badge">non connesso</span>' ?></dd>
      <dt>Ultimo login</dt><dd><?= $state['login_at'] ? date('d/m/Y H:i:s', (int)$state['login_at']) : '—' ?></dd>
      <dt>Ultima attività</dt><dd><?= $state['last'] ? date('d/m/Y H:i:s', (int)$state['last']) : '—' ?></dd>
      <dt>Messaggi in coda</dt><dd><?= $state['msgq'] === null ? '—' : (int)$state['msgq'] ?></dd>
    </dl>
    <p class="row">
      <a class="btn sec shrink" href="<?= url('session/hello') ?>">Hello</a>
      <?php if (can('operator')): ?>
        <form method="post" action="<?= url('session/login') ?>" class="shrink"><?= csrf_field() ?><button class="sec">Login</button></form>
        <form method="post" action="<?= url('session/logout') ?>" class="shrink"><?= csrf_field() ?><button class="sec">Logout</button></form>
      <?php endif; ?>
      <?php if (can('admin')): ?><a class="btn sec shrink" href="<?= url('session/password') ?>">Cambia password</a><?php endif; ?>
    </p>
  </div>
  <div class="card">
    <h2>Verifica disponibilità</h2>
    <form method="get" action="<?= url() ?>">
      <input type="hidden" name="r" value="domains/check">
      <textarea name="names" placeholder="esempio.it&#10;altro-esempio.it" style="min-height:70px"></textarea>
      <p><button>Check</button></p>
    </form>
  </div>
  <div class="card">
    <h2>Apri dominio / contatto</h2>
    <form method="get" action="<?= url() ?>"><input type="hidden" name="r" value="domains/info">
      <div class="row"><input type="text" name="name" placeholder="dominio.it"><button class="shrink">Info</button></div></form>
    <form method="get" action="<?= url() ?>" style="margin-top:10px"><input type="hidden" name="r" value="contacts/info">
      <div class="row"><input type="text" name="id" placeholder="ID contatto"><button class="shrink">Info</button></div></form>
    <?php if (can('operator')): ?>
      <p class="row"><a class="btn shrink" href="<?= url('domains/create') ?>">Nuovo dominio</a>
        <a class="btn sec shrink" href="<?= url('contacts/create', ['registrant' => 1]) ?>">Nuovo registrante</a></p>
    <?php endif; ?>
  </div>
</div>

<div class="grid g2">
  <div class="card">
    <h2>In scadenza (45 giorni, da cache locale)</h2>
    <?php if (!$expiring): ?><p class="mute">Nessun dominio in scadenza nella cache.</p><?php else: ?>
    <table><tr><th>Dominio</th><th>Scadenza</th><th>Stato</th></tr>
      <?php foreach ($expiring as $d): ?>
        <tr><td><a href="<?= url('domains/info', ['name' => $d['name']]) ?>"><?= h($d['name']) ?></a></td>
          <td><?= fmt_date($d['ex_date']) ?></td><td class="small"><?= h($d['status']) ?></td></tr>
      <?php endforeach; ?></table>
    <?php endif; ?>
    <p class="help">Il Registro .it rinnova automaticamente alla scadenza se c'è credito: questa lista serve per controllare credito e disdette.</p>
  </div>
  <div class="card">
    <h2>Ultimi comandi</h2>
    <table><tr><th>Ora</th><th>Comando</th><th>Oggetto</th><th>Esito</th></tr>
      <?php foreach ($recent as $l): ?>
        <tr><td class="small"><a href="<?= url('log/view', ['id' => $l['id']]) ?>"><?= fmt_date($l['created_at']) ?></a></td>
          <td class="mono small"><?= h($l['command']) ?></td><td class="small"><?= h($l['object']) ?></td>
          <td><?= $l['error'] ? '<span class="badge bad">errore</span>' : '<span class="badge ' . ($l['result_code'] < 2000 ? 'ok' : 'bad') . '">' . h($l['result_code']) . '</span>' ?></td></tr>
      <?php endforeach; ?></table>
  </div>
</div>
