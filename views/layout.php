<?php $u = current_user(); $st = $u ? epp_state() : null; $cur = $_GET['r'] ?? 'dashboard'; ?>
<!doctype html>
<html lang="<?= h(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(cfg('app.name', 'EPP .it')) ?></title>
<link rel="stylesheet" href="<?= h(rtrim((string)cfg('app.base_url', ''), '/')) ?>/assets/app.css">
</head>
<body class="<?= !empty($bare) ? 'bare' : '' ?>">
<?php if ($u && empty($bare)): ?>
<header class="top">
  <a class="brand" href="<?= url('dashboard') ?>"><?= h(cfg('app.name', 'EPP .it')) ?></a>
  <nav>
    <?php
      $navs = ['dashboard' => t('nav.dashboard'), 'domains' => t('nav.domains'), 'domains/check' => t('nav.check'),
               'domains/transfer' => t('nav.transfers'), 'contacts' => t('nav.contacts'), 'poll' => t('nav.poll'), 'log' => t('nav.log')];
      $active = isset($navs[$cur]) ? $cur : explode('/', $cur)[0];
      foreach ($navs as $r => $l): ?>
      <a href="<?= url($r) ?>" class="<?= $active === $r ? 'on' : '' ?>"><?= h($l) ?></a>
    <?php endforeach; ?>
    <?php if (can('admin')): ?>
      <a href="<?= url('console') ?>" class="<?= $cur === 'console' ? 'on' : '' ?>"><?= th('nav.console') ?></a>
      <a href="<?= url('users') ?>" class="<?= $cur === 'users' ? 'on' : '' ?>"><?= th('nav.users') ?></a>
      <a href="<?= url('settings') ?>" class="<?= $cur === 'settings' ? 'on' : '' ?>"><?= th('nav.settings') ?></a>
    <?php endif; ?>
  </nav>
  <div class="who">
    <span class="sess <?= $st['logged_in'] ? 'up' : 'down' ?>" title="<?= th('nav.epp_session') ?>"><?= th($st['logged_in'] ? 'nav.epp_connected' : 'nav.epp_disconnected') ?></span>
    <?php if ($st['msgq']): ?><a class="badge warn" href="<?= url('poll') ?>"><?= th('nav.messages', ['n' => (int)$st['msgq']]) ?></a><?php endif; ?>
    <a href="<?= url('account') ?>" title="<?= th('nav.account') ?>"><?= h($u['username']) ?> · <?= th('role.' . $u['role']) ?></a>
    <form method="post" action="<?= url('logout') ?>" class="inline"><?= csrf_field() ?><button class="link"><?= th('nav.logout') ?></button></form>
  </div>
</header>
<?php endif; ?>
<main>
<?php foreach (flash() as [$t, $m]): ?>
  <div class="flash <?= h($t) ?>"><?= h($m) ?></div>
<?php endforeach; ?>
<?= $content ?>
</main>
<script>window.I18N = <?= json_encode(js_strings(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script>
document.querySelectorAll('[data-gen]').forEach(b => b.addEventListener('click', e => {
  e.preventDefault();
  const sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ','abcdefghijkmnopqrstuvwxyz','23456789','-_+=.:'];
  const all = sets.join(''); const r = n => crypto.getRandomValues(new Uint32Array(1))[0] % n;
  let p = sets.map(s => s[r(s.length)]).join(''); while (p.length < 16) p += all[r(all.length)];
  p = p.split('').sort(() => r(3) - 1).join('');
  document.getElementById(b.dataset.gen).value = p;
}));
document.querySelectorAll('[data-toggle]').forEach(c => {
  const t = document.getElementById(c.dataset.toggle); const f = () => t.hidden = !c.checked; c.addEventListener('change', f); f();
});
document.querySelectorAll('[data-fill]').forEach(b => b.addEventListener('click', () => {
  const t = document.getElementById(b.dataset.fill);
  if (t.disabled) { const c = document.getElementById('adm_reg'); if (c && t.id === 'adm') { c.checked = false; c.dispatchEvent(new Event('change')); } }
  t.value = b.dataset.value; t.classList.remove('filled'); void t.offsetWidth; t.classList.add('filled'); t.focus();
}));
document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
</script>
<?php if ($u): ?>
<script>window.CONTACT_SEARCH = <?= json_encode(['url' => url('contacts/search'), 'createUrl' => can('operator') ? url('contacts/create', ['registrant' => 1]) : null,
  'importUrl' => can('operator') ? url('import') : null]) ?>;</script>
<script src="<?= h(rtrim((string)cfg('app.base_url', ''), '/')) ?>/assets/contact-search.js"></script>
<?php endif; ?>
</body>
</html>
