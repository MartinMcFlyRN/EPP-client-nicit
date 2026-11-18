<h1>Codici di recupero</h1>
<div class="card" style="max-width:560px">
  <div class="flash ok">Salvali adesso, ad esempio nel password manager: non verranno più mostrati.</div>
  <p>Ogni codice permette <strong>un solo accesso</strong> senza l'app di autenticazione, per esempio se perdi il telefono.</p>
  <pre class="xml" id="codes" style="font-size:16px;line-height:1.8;text-align:center"><?= h(implode("\n", $codes)) ?></pre>
  <p class="row">
    <button type="button" class="sec shrink" onclick="navigator.clipboard.writeText(document.getElementById('codes').textContent).then(()=>this.textContent='Copiati')">Copia</button>
    <a class="btn shrink" href="<?= url('dashboard') ?>">Li ho salvati, continua</a>
  </p>
  <p class="help">I codici precedenti, se c'erano, non sono più validi.</p>
</div>
