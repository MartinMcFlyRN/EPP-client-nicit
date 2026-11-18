<?php
$pretty = function (?string $x): string {
    if (!$x) return '';
    $d = new DOMDocument(); $d->preserveWhiteSpace = false; $d->formatOutput = true;
    return @$d->loadXML($x) ? $d->saveXML() : $x;
};
?>
<h1>Log #<?= (int)$l['id'] ?> — <?= h($l['command']) ?></h1>
<div class="card">
  <dl class="kv">
    <dt>Data</dt><dd><?= fmt_date($l['created_at']) ?></dd>
    <dt>Utente</dt><dd><?= h($l['username']) ?></dd>
    <dt>Oggetto</dt><dd><?= h($l['object']) ?></dd>
    <dt>clTRID / svTRID</dt><dd class="mono small"><?= h($l['cltrid']) ?> / <?= h($l['svtrid']) ?></dd>
    <dt>Esito</dt><dd><?= h($l['result_code']) ?> <?= h($l['result_msg']) ?></dd>
    <?php if ($l['error']): ?><dt>Errore</dt><dd class="taken"><?= h($l['error']) ?></dd><?php endif; ?>
    <dt>Durata</dt><dd><?= (int)$l['duration_ms'] ?> ms</dd>
  </dl>
</div>
<div class="grid g2">
  <div class="card"><h2>Richiesta</h2><pre class="xml"><?= h($pretty($l['request_xml'])) ?></pre></div>
  <div class="card"><h2>Risposta</h2><pre class="xml"><?= h($pretty($l['response_xml'])) ?></pre></div>
</div>
