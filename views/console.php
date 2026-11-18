<h1>Console XML</h1>
<p class="help">Invia XML EPP arbitrario sulla sessione autenticata (login automatico). Ogni invio finisce nel log. Usala per comandi non coperti dall'interfaccia: attenzione, nessun controllo di sicurezza sul contenuto.</p>
<div class="grid g2">
  <form method="post" action="<?= url('console') ?>" class="card">
    <?= csrf_field() ?>
    <textarea name="xml" class="big"><?= h($xml) ?></textarea>
    <p><button>Invia</button></p>
  </form>
  <div class="card">
    <h2>Risposta</h2>
    <?php if ($res): ?>
      <p><span class="badge <?= $res->ok() ? 'ok' : 'bad' ?>"><?= (int)$res->code() ?></span> <?= h($res->ok() ? $res->msg() : $res->errorText()) ?></p>
      <pre class="xml"><?= h($res->pretty()) ?></pre>
    <?php else: ?><p class="mute">—</p><?php endif; ?>
  </div>
</div>
