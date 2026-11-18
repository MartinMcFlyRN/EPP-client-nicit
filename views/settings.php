<?php
$src = fn(string $k) => match (epp_cfg_source($k)) {
    'web' => '<span class="badge client">' . th('set.src_web') . '</span>',
    'config' => '<span class="badge">' . th('set.src_config') . '</span>',
    'file' => '<span class="badge warn">storage/epp-password</span>',
    default => '<span class="badge bad">' . th('set.src_none') . '</span>',
};
?>
<h1><?= th('set.title') ?></h1>
<?php if (!empty($GLOBALS['EPP_CFG_ERROR'])): ?><div class="flash err"><?= h($GLOBALS['EPP_CFG_ERROR']) ?></div><?php endif; ?>
<p class="help"><?= t('set.intro_html') ?>
<?php if ($meta['at']): ?><?= th('set.last_change', ['date' => fmt_date($meta['at'])]) ?><?= $meta['by'] ? ' · ' . h($meta['by']) : '' ?>.<?php endif; ?></p>

<form method="post" action="<?= url('settings') ?>" class="card" style="max-width:860px" autocomplete="off">
  <?= csrf_field() ?>
  <?php foreach (EPP_FIELDS as $k => [$label, $type]):
      $isWeb = epp_cfg_source($k) === 'web';
      $val = $type === 'secret' ? '' : (string)($isWeb ? settings()->get("epp.$k") : '');
      $ph = $type === 'secret'
          ? (epp_cfg_source($k) ? t('set.pw_set') : t('set.pw_not_set'))
          : (string)(cfg("epp.$k") ?? '');
  ?>
    <div style="margin-bottom:6px">
      <label><?= th('set.epp.' . $k) ?> <?= $src($k) ?></label>
      <div class="row">
        <?php if ($type === 'bool'): ?>
          <input type="hidden" name="<?= $k ?>" value="0">
          <label style="font-weight:400;margin:0"><input type="checkbox" name="<?= $k ?>" value="1" <?= epp_cfg()[$k] ? 'checked' : '' ?>> <?= th('set.enabled') ?></label>
        <?php else: ?>
        <input type="<?= $type === 'secret' ? 'password' : 'text' ?>" name="<?= $k ?>" value="<?= h($val) ?>"
               placeholder="<?= h($ph) ?>" <?= $type === 'secret' ? 'autocomplete="new-password"' : '' ?>>
        <?php endif; ?>
        <?php if ($isWeb): ?>
          <label class="shrink" style="font-weight:400;margin:0"><input type="checkbox" name="reset_<?= $k ?>" value="1"> <?= th('set.use_config') ?></label>
        <?php endif; ?>
      </div>
      <?php if (lang_strings('en')['set.epp_help.' . $k] ?? null): ?><p class="help"><?= t('set.epp_help.' . $k) ?></p><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <p class="row">
    <button class="shrink"><?= th('common.save') ?></button>
    <button class="sec shrink" name="test" value="1"><?= th('set.save_test') ?></button>
  </p>
  <p class="help"><?= th('set.session_reset_help') ?></p>
</form>

<div class="card" style="max-width:860px">
  <h2><?= th('set.in_use') ?></h2>
  <dl class="kv">
    <dt><?= th('dash.server') ?></dt><dd class="mono"><?= h($e['url'] ?: '—') ?></dd>
    <dt>clID</dt><dd class="mono"><?= h($e['clid'] ?: '—') ?></dd>
    <dt><?= th('set.epp.source_ip') ?></dt><dd class="mono"><?= h(($e['source_ip'] ?? '') ?: 'default') ?></dd>
    <dt>DNSSEC</dt><dd><?= $e['dnssec'] ? '<span class="badge ok">' . th('set.enabled') . '</span>' : '<span class="badge">' . th('set.disabled') . '</span>' ?></dd>
    <dt><?= th('set.relogin_after') ?></dt><dd><?= (int)($e['idle_timeout'] ?? 600) ?> s</dd>
  </dl>
  <p class="help"><?= t('set.key_help_html', ['key' => '<code>' . (cfg('app.secret') ? 'app.secret (config.php)' : 'storage/app.key') . '</code>']) ?></p>
</div>

<?php $def = epp_defaults(); ?>
<form method="post" action="<?= url('settings') ?>" class="card" style="max-width:860px" id="defaults">
  <?= csrf_field() ?><input type="hidden" name="section" value="defaults">
  <h2><?= th('set.defaults') ?></h2>
  <p class="help"><?= th('set.defaults_help') ?></p>
  <label><?= th('set.def_ns') ?></label>
  <textarea name="def_ns" placeholder="ns1.example.it 192.0.2.10&#10;ns2.example.it 192.0.2.11"><?= h($def['ns']) ?></textarea>
  <p class="help"><?= th('set.def_ns_help') ?></p>
  <label><?= th('set.contact_prefix') ?></label>
  <input type="text" name="def_contact_prefix" value="<?= h((string)settings()->get('defaults.contact_prefix', '')) ?>" maxlength="10"
         placeholder="<?= h(contact_prefix() ?: 'PWEB-') ?>" style="max-width:220px;text-transform:uppercase">
  <p class="help"><?= t('set.contact_prefix_help_html') ?></p>
  <div class="row">
    <div><label><?= th('set.def_admin') ?></label><input type="text" name="def_admin" value="<?= h($def['admin']) ?>" data-contact-search="any" placeholder="<?= th('con.search_short_ph') ?>"></div>
    <div><label><?= th('set.def_techs') ?></label><textarea name="def_techs" id="def_techs" style="min-height:60px"><?= h($def['techs']) ?></textarea>
      <input type="text" data-contact-search="any" data-append="def_techs" placeholder="<?= th('con.search_add_tech') ?>" style="margin-top:4px"></div>
  </div>
  <p class="help"><?= th('set.def_contacts_help') ?></p>
  <p><button><?= th('set.save_defaults') ?></button></p>
</form>

<form method="post" action="<?= url('settings') ?>" class="card" style="max-width:860px" id="logs">
  <?= csrf_field() ?><input type="hidden" name="section" value="logs">
  <h2><?= th('log.title') ?></h2>
  <dl class="kv">
    <dt><?= th('set.log_records') ?></dt><dd><?= number_format($logStats['count'], 0, '', t('fmt.thousands')) ?></dd>
    <dt><?= th('set.log_oldest') ?></dt><dd><?= $logStats['oldest'] ? fmt_date($logStats['oldest']) : '—' ?></dd>
  </dl>
  <label><?= th('set.retention') ?></label>
  <div class="row">
    <input type="text" name="retention_days" inputmode="numeric" value="<?= log_retention_days() ?: '' ?>" placeholder="<?= th('set.retention_ph') ?>" style="max-width:220px">
    <button class="shrink"><?= th('set.save_apply') ?></button>
  </div>
  <p class="help"><?= t('set.retention_help_html') ?></p>
</form>

<form method="post" action="<?= url('settings') ?>" class="card" style="max-width:860px" id="security">
  <?= csrf_field() ?><input type="hidden" name="section" value="security">
  <h2><?= th('sec.title') ?></h2>
  <label><?= th('sec.allowed_ips') ?></label>
  <textarea name="allowed_ips" placeholder="<?= th('sec.allowed_ips_ph') ?>&#10;192.0.2.10&#10;198.51.100.0/24&#10;2001:db8::/32"><?= h(implode("\n", security()->allowedIps())) ?></textarea>
  <p class="help"><?= t('sec.allowed_ips_help_html', ['ip' => '<code>' . h(client_ip()) . '</code>']) ?></p>
  <div class="grid g2" style="gap:0 16px">
  <?php foreach (\NicEpp\App\Security::FIELDS as $k => [$label, $def, $min, $max]):
      $cur = settings()->get("sec.$k"); ?>
    <div>
      <label><?= th('sec.' . $k) ?></label>
      <input type="text" name="<?= $k ?>" inputmode="numeric" value="<?= h($cur ?? '') ?>" placeholder="<?= th('sec.default_ph', ['n' => $def]) ?>">
      <p class="help"><?= th('sec.help.' . $k) ?></p>
    </div>
  <?php endforeach; ?>
  </div>
  <p class="help"><?= th('sec.current', ['idle' => security()->get('idle_timeout'), 'max' => security()->get('max_session'), 'pw' => min_password(),
      'win' => security()->get('fail_window'), 'ip' => security()->get('fail_per_ip'), 'uip' => security()->get('fail_per_user_ip'), 'days' => security()->get('remember_days')]) ?></p>
  <p><button><?= th('sec.save') ?></button></p>
</form>
