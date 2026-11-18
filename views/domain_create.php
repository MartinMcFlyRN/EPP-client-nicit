<h1><?= th('dc.title') ?></h1>
<form method="post" action="<?= url('domains/create') ?>" class="card" style="max-width:820px">
  <?= csrf_field() ?>
  <div class="row">
    <div><label><?= th('dc.name') ?></label><input type="text" name="name" value="<?= h($f['name'] ?? '') ?>" required placeholder="<?= th('dc.name_ph') ?>"></div>
  </div>
  <div class="row">
    <div><label><?= th('dom.registrant') ?></label><input type="text" name="registrant" id="reg" value="<?= h($f['registrant'] ?? '') ?>" required
      data-contact-search="registrant" placeholder="<?= th('con.search_ph') ?>"></div>
    <div><label>Admin</label><input type="text" name="admin" id="adm" value="<?= h($f['admin'] ?? '') ?>"
      data-contact-search="any" placeholder="<?= th('con.search_ph') ?>"></div>
  </div>
  <p class="help" id="reg_info"></p>
  <?php $def = epp_defaults(); ?>
  <p class="row" style="margin:0"><?= fill_button('adm', $def['admin'], t('def.use_admin')) ?></p>
  <label style="font-weight:400"><input type="checkbox" name="admin_is_registrant" value="1" id="adm_reg" <?= !empty($f['admin_is_registrant']) ? 'checked' : '' ?>>
    <?= th('dc.admin_is_registrant') ?></label>
  <label><?= th('dc.techs') ?></label>
  <textarea name="techs" id="techs" style="min-height:50px"><?= h($f['techs'] ?? '') ?></textarea>
  <input type="text" data-contact-search="any" data-append="techs" placeholder="<?= th('con.search_add_tech_long') ?>" style="margin-top:4px">
  <p class="row" style="margin:4px 0 0"><?= fill_button('techs', $def['techs'], t('def.use_techs')) ?></p>
  <label><?= th('dc.ns') ?></label>
  <textarea name="ns" id="ns_new" placeholder="ns1.example.it 192.0.2.1&#10;ns2.provider.net"><?= h($f['ns'] ?? '') ?></textarea>
  <p class="row" style="margin:4px 0 0"><?= fill_button('ns_new', $def['ns'], t('def.use_ns')) ?></p>
  <p class="help"><?= th('dc.ns_help') ?></p>
  <?php if (dnssec_enabled()): ?>
  <label><?= th('dc.ds') ?></label>
  <textarea name="ds" style="min-height:50px" placeholder="12345 13 2 ABCDEF..."><?= h($f['ds'] ?? '') ?></textarea>
  <?php endif; ?>
  <label>AuthInfo</label>
  <div class="row"><input type="text" id="ai" name="authinfo" value="<?= h($f['authinfo'] ?? '') ?>"><button class="sec shrink" data-gen="ai"><?= th('common.generate') ?></button></div>
  <p class="help"><?= th('dc.period') ?></p>
  <p><button><?= th('dc.submit') ?></button> <a href="<?= url('contacts/create', ['registrant' => 1]) ?>" target="_blank"><?= th('dc.create_registrant') ?></a></p>
  <?php require __DIR__ . '/partials_response.php'; ?>
</form>
<script>
  (() => { const c = document.getElementById('adm_reg'), a = document.getElementById('adm');
    const f = () => { a.disabled = c.checked; a.required = !c.checked; }; c.addEventListener('change', f); f();
    document.getElementById('reg').addEventListener('contact-picked', e => {
      const d = e.detail, info = document.getElementById('reg_info');
      info.textContent = d.name + (d.org && d.org !== d.name ? ' — ' + d.org : '') + (d.email ? ' · ' + d.email : '');
      if (d.entityType === 1 && !c.checked) { c.checked = true; f(); info.textContent += ' · ' + I18N.person_admin_set; }
    });
  })();
</script>
