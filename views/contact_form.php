<?php
$edit = $mode === 'edit';
$reg = $c['registrant'] ?? null;
$st = $c['street'] ?? [];
?>
<h1><?= $edit ? 'Modifica contatto ' . h($c['id']) : 'Nuovo contatto' ?></h1>
<form method="post" action="<?= url($edit ? 'contacts/edit' : 'contacts/create') ?>" class="card" style="max-width:860px">
  <?= csrf_field() ?>
  <div class="row">
    <div><label>ID contatto</label>
      <input type="text" name="id" value="<?= h($c['id'] ?? '') ?>" required maxlength="16" <?= $edit ? 'readonly' : '' ?> placeholder="es. PWEB-ROSSI01">
      <?php if (!$edit): ?><p class="help">3–16 caratteri, univoco nel Registro. Conviene un prefisso del registrar.</p><?php endif; ?></div>
    <div><label>Nome e cognome</label><input type="text" name="name" value="<?= h($c['name'] ?? '') ?>" required></div>
  </div>
  <label>Organizzazione</label><input type="text" name="org" value="<?= h($c['org'] ?? '') ?>">
  <label>Indirizzo</label>
  <input type="text" name="street1" value="<?= h($st[0] ?? '') ?>" required placeholder="Via, numero civico">
  <input type="text" name="street2" value="<?= h($st[1] ?? '') ?>" style="margin-top:4px">
  <input type="text" name="street3" value="<?= h($st[2] ?? '') ?>" style="margin-top:4px">
  <div class="row">
    <div><label>CAP</label><input type="text" name="pc" value="<?= h($c['pc'] ?? '') ?>"></div>
    <div><label>Città</label><input type="text" name="city" value="<?= h($c['city'] ?? '') ?>" required></div>
    <div><label>Provincia (sigla)</label><input type="text" name="sp" value="<?= h($c['sp'] ?? '') ?>" maxlength="2" placeholder="GE"></div>
    <div><label>Nazione</label><input type="text" name="cc" value="<?= h($c['cc'] ?? 'IT') ?>" maxlength="2" required></div>
  </div>
  <div class="row">
    <div><label>Telefono</label><input type="text" name="voice" value="<?= h($c['voice'] ?? '') ?>" placeholder="+39.0101234567"></div>
    <div><label>Fax</label><input type="text" name="fax" value="<?= h($c['fax'] ?? '') ?>" placeholder="+39.0101234567"></div>
    <div><label>Email</label><input type="email" name="email" value="<?= h($c['email'] ?? '') ?>" required></div>
  </div>
  <label style="font-weight:400;margin-top:14px"><input type="checkbox" name="consent" value="1" <?= !empty($c['consent']) ? 'checked' : '' ?>> Consenso alla pubblicazione dei dati personali (consentForPublishing)</label>

  <label style="font-weight:400;margin-top:10px"><input type="checkbox" name="is_registrant" value="1" data-toggle="regbox" <?= $reg ? 'checked' : '' ?>> È un registrante (sezione extcon:registrant)</label>
  <div id="regbox" class="card" style="background:#fafbfc;margin-top:8px">
    <div class="row">
      <div><label>Nazionalità</label><input type="text" name="nationality" value="<?= h($reg['nationality'] ?? 'IT') ?>" maxlength="2"></div>
      <div><label>Codice fiscale / Partita IVA</label><input type="text" name="regCode" value="<?= h($reg['regCode'] ?? '') ?>"></div>
    </div>
    <label>Tipo soggetto (entityType)</label>
    <select name="entityType">
      <?php foreach (ENTITY_TYPES as $k => $l): ?><option value="<?= $k ?>" <?= (int)($reg['entityType'] ?? 1) === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?>
    </select>
    <p class="help">Per soggetti non persona fisica il consenso alla pubblicazione deve essere attivo. Persone fisiche: codice fiscale; società: partita IVA (numerica per entità italiane).</p>
  </div>
  <p><button><?= $edit ? 'Salva modifiche' : 'Crea contatto' ?></button></p>
  <?php require __DIR__ . '/partials_response.php'; ?>
</form>
