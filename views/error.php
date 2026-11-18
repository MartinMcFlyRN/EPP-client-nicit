<h1><?= h($title) ?></h1>
<div class="flash err"><?= h($message) ?></div>
<?php if (!empty($xml)): ?><details open><summary>Risposta XML</summary><pre class="xml"><?= h($xml) ?></pre></details><?php endif; ?>
<p><a href="javascript:history.back()">← Indietro</a></p>
