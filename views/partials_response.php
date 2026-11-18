<?php /** @var ?\NicEpp\Epp\Response $res */ if (!empty($res)): ?>
<details><summary>Risposta EPP (<?= (int)$res->code() ?> <?= h($res->msg()) ?>)</summary><pre class="xml"><?= h($res->pretty()) ?></pre></details>
<?php endif; ?>
