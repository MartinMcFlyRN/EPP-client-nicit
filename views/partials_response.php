<?php /** @var ?\NicEpp\Epp\Response $res */ if (!empty($res)): ?>
<details><summary><?= th('common.epp_response', ['code' => (int)$res->code(), 'msg' => $res->msg()]) ?></summary><pre class="xml"><?= h($res->pretty()) ?></pre></details>
<?php endif; ?>
