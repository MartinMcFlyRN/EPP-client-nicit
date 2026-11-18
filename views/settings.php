<?php
$src = fn(string $k) => match (epp_cfg_source($k)) {
    'web' => '<span class="badge client">da web</span>',
    'config' => '<span class="badge">da config.php</span>',
    'file' => '<span class="badge warn">storage/epp-password</span>',
    default => '<span class="badge bad">non impostato</span>',
};
$help = [
    'url' => 'Produzione <code>https://epp.nic.it</code> — test <code>https://pub-test.nic.it</code>. Re-registrazione di domini cancellati: <code>https://epp-deleted.nic.it</code>.',
    'clid' => 'Username del registrar presso il Registro.',
    'password' => 'Lascia vuoto per non cambiarla. Viene salvata cifrata. Per cambiarla anche presso il Registro usa «Cambia password» in dashboard.',
    'tridPrefix' => 'Prefisso dei clTRID generati, utile per riconoscere i comandi di questo client.',
    'source_ip' => 'IP locale da cui uscire: deve essere tra quelli autorizzati presso il Registro. Vuoto = default di sistema.',
    'ca_file' => 'Percorso assoluto sul server. Vuoto = CA di sistema.',
    'client_cert' => 'Solo se richiesto: percorso assoluto del certificato PEM.',
    'client_key' => 'Percorso assoluto della chiave PEM del certificato client.',
    'dnssec' => 'Attivalo solo dopo l\'accreditamento DNSSEC presso il Registro: se attivo, il login dichiara i namespace secDNS-1.1 ed extsecDNS-1.0 e compaiono le funzioni DS. Senza accreditamento il login fallisce con 2306 / 10001.',
    'idle_timeout' => 'Dopo quanti secondi senza comandi rifare il login preventivamente. Tienilo sotto il timeout di sessione del Registro.',
];
?>
<h1>Configurazione EPP</h1>
<?php if (!empty($GLOBALS['EPP_CFG_ERROR'])): ?><div class="flash err"><?= h($GLOBALS['EPP_CFG_ERROR']) ?></div><?php endif; ?>
<p class="help">I valori salvati qui hanno precedenza su <code>config.php</code>. Un campo vuoto (o «usa config.php») torna al valore del file.
<?php if ($meta['at']): ?>Ultima modifica: <?= fmt_date($meta['at']) ?><?= $meta['by'] ? ' · ' . h($meta['by']) : '' ?>.<?php endif; ?></p>

<form method="post" action="<?= url('settings') ?>" class="card" style="max-width:860px" autocomplete="off">
  <?= csrf_field() ?>
  <?php foreach (EPP_FIELDS as $k => [$label, $type]):
      $isWeb = epp_cfg_source($k) === 'web';
      $val = $type === 'secret' ? '' : (string)($isWeb ? settings()->get("epp.$k") : '');
      $ph = $type === 'secret'
          ? (epp_cfg_source($k) ? '•••••••• (impostata)' : 'non impostata')
          : (string)(cfg("epp.$k") ?? '');
  ?>
    <div style="margin-bottom:6px">
      <label><?= h($label) ?> <?= $src($k) ?></label>
      <div class="row">
        <?php if ($type === 'bool'): ?>
          <input type="hidden" name="<?= $k ?>" value="0">
          <label style="font-weight:400;margin:0"><input type="checkbox" name="<?= $k ?>" value="1" <?= epp_cfg()[$k] ? 'checked' : '' ?>> abilitato</label>
        <?php else: ?>
        <input type="<?= $type === 'secret' ? 'password' : 'text' ?>" name="<?= $k ?>" value="<?= h($val) ?>"
               placeholder="<?= h($ph) ?>" <?= $type === 'secret' ? 'autocomplete="new-password"' : '' ?>>
        <?php endif; ?>
        <?php if ($isWeb): ?>
          <label class="shrink" style="font-weight:400;margin:0"><input type="checkbox" name="reset_<?= $k ?>" value="1"> usa config.php</label>
        <?php endif; ?>
      </div>
      <?php if (isset($help[$k])): ?><p class="help"><?= $help[$k] ?></p><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <p class="row">
    <button class="shrink">Salva</button>
    <button class="sec shrink" name="test" value="1">Salva e prova connessione</button>
  </p>
  <p class="help">Se cambiano server, credenziali, IP sorgente o certificati, la sessione EPP aperta viene chiusa e il comando successivo rifà il login.</p>
</form>

<div class="card" style="max-width:860px">
  <h2>Valori in uso</h2>
  <dl class="kv">
    <dt>Server</dt><dd class="mono"><?= h($e['url'] ?: '—') ?></dd>
    <dt>clID</dt><dd class="mono"><?= h($e['clid'] ?: '—') ?></dd>
    <dt>IP sorgente</dt><dd class="mono"><?= h(($e['source_ip'] ?? '') ?: 'default') ?></dd>
    <dt>DNSSEC</dt><dd><?= $e['dnssec'] ? '<span class="badge ok">abilitato</span>' : '<span class="badge">disabilitato</span>' ?></dd>
    <dt>Relogin dopo</dt><dd><?= (int)($e['idle_timeout'] ?? 600) ?> s</dd>
  </dl>
  <p class="help">La password è cifrata nel DB con la chiave <code><?= cfg('app.secret') ? 'app.secret di config.php' : 'storage/app.key' ?></code>: includila nei backup, senza la chiave andrà reinserita.</p>
</div>

<?php $def = epp_defaults(); ?>
<form method="post" action="<?= url('settings') ?>" class="card" style="max-width:860px" id="defaults">
  <?= csrf_field() ?><input type="hidden" name="section" value="defaults">
  <h2>Valori predefiniti</h2>
  <p class="help">Compilano in automatico il modulo «Nuovo dominio» e vengono proposti con un pulsante quando modifichi nameserver o contatti di un dominio.</p>
  <label>Nameserver predefiniti (2–6, uno per riga: host [ip...])</label>
  <textarea name="def_ns" placeholder="ns1.pensareweb.it 192.0.2.10&#10;ns2.pensareweb.it 192.0.2.11"><?= h($def['ns']) ?></textarea>
  <p class="help">Glue IP solo se il nameserver sta sotto il dominio che registri; per i vostri nameserver su un altro dominio basta il nome.</p>
  <label>Prefisso ID contatti</label>
  <input type="text" name="def_contact_prefix" value="<?= h((string)settings()->get('defaults.contact_prefix', '')) ?>" maxlength="10"
         placeholder="<?= h(contact_prefix() ?: 'es. PWEB-') ?>" style="max-width:220px;text-transform:uppercase">
  <p class="help">Messo davanti a ogni nuovo ID contatto (es. <code>PWEB-</code> → <code>PWEB-ROSSI01</code> o <code>PWEB-K7Q2M9XA</code>).
    Se vuoto si usa il prefisso clTRID seguito da un trattino. Gli ID nel Registro sono lunghi da 3 a 16 caratteri.</p>
  <div class="row">
    <div><label>Admin predefinito (ID)</label><input type="text" name="def_admin" value="<?= h($def['admin']) ?>" data-contact-search="any" placeholder="ID, nome o organizzazione"></div>
    <div><label>Tech predefiniti (ID, uno per riga, max 6)</label><textarea name="def_techs" id="def_techs" style="min-height:60px"><?= h($def['techs']) ?></textarea>
      <input type="text" data-contact-search="any" data-append="def_techs" placeholder="Cerca e aggiungi un tech" style="margin-top:4px"></div>
  </div>
  <p class="help">Per i registranti persona fisica il Registro vuole l'admin uguale al registrante: nel modulo di registrazione c'è l'opzione «Usa il registrante come admin».
    Al salvataggio viene controllato che i contatti esistano presso il Registro.</p>
  <p><button>Salva valori predefiniti</button></p>
</form>
