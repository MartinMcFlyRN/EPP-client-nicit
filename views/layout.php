<?php $u = current_user(); $st = $u ? epp_state() : null; $cur = $_GET['r'] ?? 'dashboard'; ?>
<!doctype html>
<html lang="it">
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
      $navs = ['dashboard' => 'Dashboard', 'domains' => 'Domini', 'domains/check' => 'Check', 'domains/transfer' => 'Trasferimenti',
               'contacts' => 'Contatti', 'poll' => 'Poll', 'log' => 'Log'];
      $active = isset($navs[$cur]) ? $cur : explode('/', $cur)[0];
      foreach ($navs as $r => $l): ?>
      <a href="<?= url($r) ?>" class="<?= $active === $r ? 'on' : '' ?>"><?= $l ?></a>
    <?php endforeach; ?>
    <?php if (can('admin')): ?>
      <a href="<?= url('console') ?>" class="<?= $cur === 'console' ? 'on' : '' ?>">Console XML</a>
      <a href="<?= url('users') ?>" class="<?= $cur === 'users' ? 'on' : '' ?>">Utenti</a>
      <a href="<?= url('settings') ?>" class="<?= $cur === 'settings' ? 'on' : '' ?>">Configurazione</a>
    <?php endif; ?>
  </nav>
  <div class="who">
    <span class="sess <?= $st['logged_in'] ? 'up' : 'down' ?>" title="Sessione EPP">EPP <?= $st['logged_in'] ? 'connesso' : 'non connesso' ?></span>
    <?php if ($st['msgq']): ?><a class="badge warn" href="<?= url('poll') ?>"><?= (int)$st['msgq'] ?> msg</a><?php endif; ?>
    <span><?= h($u['username']) ?> · <?= h($u['role']) ?></span>
    <form method="post" action="<?= url('logout') ?>" class="inline"><?= csrf_field() ?><button class="link">Esci</button></form>
  </div>
</header>
<?php endif; ?>
<main>
<?php foreach (flash() as [$t, $m]): ?>
  <div class="flash <?= h($t) ?>"><?= h($m) ?></div>
<?php endforeach; ?>
<?= $content ?>
</main>
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
document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
</script>
</body>
</html>
