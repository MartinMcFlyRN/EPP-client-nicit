<h1><?= h($title) ?></h1>
<div class="flash err"><?= h($message) ?></div>
<?php if (!empty($xml)): ?><details open><summary><?= th('error.xml') ?></summary><pre class="xml"><?= h($xml) ?></pre></details><?php endif; ?>
<p><a href="javascript:history.back()"><?= th('common.back') ?></a></p>
