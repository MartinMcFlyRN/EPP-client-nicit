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
    <dt><?= th('common.date') ?></dt><dd><?= fmt_date($l['created_at']) ?></dd>
    <dt><?= th('login.username') ?></dt><dd><?= h($l['username']) ?></dd>
    <dt><?= th('log.object') ?></dt><dd><?= h($l['object']) ?></dd>
    <dt>clTRID / svTRID</dt><dd class="mono small"><?= h($l['cltrid']) ?> / <?= h($l['svtrid']) ?></dd>
    <dt><?= th('log.result') ?></dt><dd><?= h($l['result_code']) ?> <?= h($l['result_msg']) ?></dd>
    <?php if ($l['error']): ?><dt><?= th('log.error_label') ?></dt><dd class="taken"><?= h($l['error']) ?></dd><?php endif; ?>
    <dt><?= th('log.duration') ?></dt><dd><?= (int)$l['duration_ms'] ?> ms</dd>
  </dl>
</div>
<div class="grid g2">
  <div class="card"><h2><?= th('log.request') ?></h2><pre class="xml"><?= h($pretty($l['request_xml'])) ?></pre></div>
  <div class="card"><h2><?= th('log.response') ?></h2><pre class="xml"><?= h($pretty($l['response_xml'])) ?></pre></div>
</div>
