<div class="card" style="max-width:440px">
  <h1>Configura il doppio fattore</h1>
  <p>Per <strong><?= h($u['username']) ?></strong> l'accesso richiede un'app di autenticazione (Google Authenticator,
    Microsoft Authenticator, 1Password, Bitwarden, FreeOTP…).</p>
  <ol class="small" style="padding-left:18px">
    <li>Nell'app aggiungi un account e inquadra il QR code.</li>
    <li>Inserisci qui sotto il codice a 6 cifre che compare.</li>
  </ol>
  <div id="qr" style="display:flex;justify-content:center;margin:14px 0"></div>
  <details><summary>Non riesci a inquadrarlo? Inserisci la chiave a mano</summary>
    <p class="mono" style="word-break:break-all;font-size:15px;letter-spacing:1px"><?= h(trim(chunk_split($secret, 4, ' '))) ?></p>
    <p class="help">Tipo: basato sul tempo (TOTP), 6 cifre, 30 secondi.</p>
  </details>
  <form method="post" action="<?= url('login/setup') ?>">
    <?= csrf_field() ?>
    <label>Codice dall'app</label>
    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="7"
           placeholder="123456" style="font-size:22px;letter-spacing:4px;text-align:center">
    <p><button>Attiva e accedi</button></p>
  </form>
  <p class="small"><a href="<?= url('login') ?>">← Annulla</a></p>
</div>
<script src="<?= h(rtrim((string)cfg('app.base_url', ''), '/')) ?>/assets/vendor/qrcode.js"></script>
<script>
  (function () {
    const q = qrcode(0, 'M');
    q.addData(<?= json_encode($uri) ?>);
    q.make();
    document.getElementById('qr').innerHTML = q.createSvgTag({cellSize: 5, margin: 4, scalable: false});
  })();
</script>
