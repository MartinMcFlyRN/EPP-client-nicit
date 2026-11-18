<h1><?= th('nav.console') ?></h1>
<p class="help"><?= th('console.help') ?></p>
<div class="grid g2">
  <form method="post" action="<?= url('console') ?>" class="card">
    <?= csrf_field() ?>
    <textarea name="xml" class="big"><?= h($xml) ?></textarea>
    <p><button><?= th('common.send') ?></button></p>
  </form>
  <div class="card">
    <h2><?= th('log.response') ?></h2>
    <?php if ($res): ?>
      <p><span class="badge <?= $res->ok() ? 'ok' : 'bad' ?>"><?= (int)$res->code() ?></span> <?= h($res->ok() ? $res->msg() : $res->errorText()) ?></p>
      <pre class="xml"><?= h($res->pretty()) ?></pre>
    <?php else: ?><p class="mute">—</p><?php endif; ?>
  </div>
</div>
