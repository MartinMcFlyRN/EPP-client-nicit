<h1><?= th('dash.title') ?></h1>
<?php if (epp_cfg()['url'] === '' || epp_cfg()['clid'] === ''): ?>
  <div class="flash err"><?= th('dash.not_configured') ?> <?= can('admin') ? '<a href="' . url('settings') . '">' . th('dash.go_settings') . '</a>' : th('dash.ask_admin') ?></div>
<?php endif; ?>
<div class="grid g3">
  <div class="card">
    <h2><?= th('nav.epp_session') ?></h2>
    <dl class="kv">
      <dt><?= th('dash.server') ?></dt><dd class="mono"><?= h(epp_cfg()['url']) ?></dd>
      <dt><?= th('dash.registrar') ?></dt><dd class="mono"><?= h(epp_cfg()['clid']) ?></dd>
      <dt><?= th('account.status') ?></dt><dd><?= $state['logged_in'] ? '<span class="badge ok">' . th('dash.connected') . '</span>' : '<span class="badge">' . th('dash.not_connected') . '</span>' ?></dd>
      <dt><?= th('dash.last_login') ?></dt><dd><?= $state['login_at'] ? date(t('fmt.datetime') . ':s', (int)$state['login_at']) : '—' ?></dd>
      <dt><?= th('dash.last_activity') ?></dt><dd><?= $state['last'] ? date(t('fmt.datetime') . ':s', (int)$state['last']) : '—' ?></dd>
      <dt><?= th('dash.queued') ?></dt><dd><?= $state['msgq'] === null ? '—' : (int)$state['msgq'] ?></dd>
    </dl>
    <p class="row">
      <a class="btn sec shrink" href="<?= url('session/hello') ?>">Hello</a>
      <?php if (can('operator')): ?>
        <form method="post" action="<?= url('session/login') ?>" class="shrink"><?= csrf_field() ?><button class="sec">Login</button></form>
        <form method="post" action="<?= url('session/logout') ?>" class="shrink"><?= csrf_field() ?><button class="sec">Logout</button></form>
      <?php endif; ?>
      <?php if (can('admin')): ?><a class="btn sec shrink" href="<?= url('session/password') ?>"><?= th('eppw.submit') ?></a>
        <a class="btn sec shrink" href="<?= url('settings') ?>"><?= th('nav.settings') ?></a><?php endif; ?>
    </p>
  </div>
  <div class="card">
    <h2><?= th('dash.check') ?></h2>
    <form method="get" action="<?= url() ?>">
      <input type="hidden" name="r" value="domains/check">
      <textarea name="names" placeholder="<?= th('dash.check_ph') ?>" style="min-height:70px"></textarea>
      <p><button><?= th('nav.check') ?></button></p>
    </form>
  </div>
  <div class="card">
    <h2><?= th('dash.open') ?></h2>
    <form method="get" action="<?= url() ?>"><input type="hidden" name="r" value="domains/info">
      <div class="row"><input type="text" name="name" placeholder="<?= th('dash.domain_ph') ?>"><button class="shrink">Info</button></div></form>
    <form method="get" action="<?= url() ?>" style="margin-top:10px"><input type="hidden" name="r" value="contacts/info">
      <div class="row"><input type="text" name="id" placeholder="<?= th('dash.contact_ph') ?>"><button class="shrink">Info</button></div></form>
    <?php if (can('operator')): ?>
      <p class="row"><a class="btn shrink" href="<?= url('domains/create') ?>"><?= th('dom.new') ?></a>
        <a class="btn sec shrink" href="<?= url('contacts/create', ['registrant' => 1]) ?>"><?= th('con.new_registrant') ?></a></p>
    <?php endif; ?>
  </div>
</div>

<div class="grid g2">
  <div class="card">
    <h2><?= th('dash.expiring') ?></h2>
    <?php if (!$expiring): ?><p class="mute"><?= th('dash.no_expiring') ?></p><?php else: ?>
    <table><tr><th><?= th('dom.domain') ?></th><th><?= th('dom.expiry') ?></th><th><?= th('account.status') ?></th></tr>
      <?php foreach ($expiring as $d): ?>
        <tr><td><a href="<?= url('domains/info', ['name' => $d['name']]) ?>"><?= h($d['name']) ?></a></td>
          <td><?= fmt_date($d['ex_date']) ?></td><td class="small"><?= h($d['status']) ?></td></tr>
      <?php endforeach; ?></table>
    <?php endif; ?>
    <p class="help"><?= th('dash.expiring_help') ?></p>
  </div>
  <div class="card">
    <h2><?= th('dash.recent') ?></h2>
    <table><tr><th><?= th('dash.time') ?></th><th><?= th('log.command') ?></th><th><?= th('log.object') ?></th><th><?= th('log.result') ?></th></tr>
      <?php foreach ($recent as $l): ?>
        <tr><td class="small"><a href="<?= url('log/view', ['id' => $l['id']]) ?>"><?= fmt_date($l['created_at']) ?></a></td>
          <td class="mono small"><?= h($l['command']) ?></td><td class="small"><?= h($l['object']) ?></td>
          <td><?= $l['error'] ? '<span class="badge bad">' . th('log.error') . '</span>' : '<span class="badge ' . ($l['result_code'] < 2000 ? 'ok' : 'bad') . '">' . h($l['result_code']) . '</span>' ?></td></tr>
      <?php endforeach; ?></table>
  </div>
</div>
