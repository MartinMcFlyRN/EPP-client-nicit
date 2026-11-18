<?php
// Uso: php bin/create-user.php <username> [admin|operator|readonly] [en|it]
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

[$_, $user, $role, $lang] = $argv + [null, null, 'admin', 'en'];
if (!$user || !isset(ROLE_LEVEL[$role])) {
    fwrite(STDERR, t('cli.create_user_usage') . "\n");
    exit(1);
}
fwrite(STDOUT, t('cli.password_for', ['user' => $user]) . ' ');
system('stty -echo 2>/dev/null');
$pw = trim((string)fgets(STDIN));
system('stty echo 2>/dev/null');
fwrite(STDOUT, "\n");
if (strlen($pw) < min_password()) {
    fwrite(STDERR, t('cli.password_short', ['n' => min_password()]) . "\n");
    exit(1);
}
repo()->createUser($user, $pw, $role, isset(languages()[$lang]) ? $lang : 'en');
echo t('cli.user_created', ['user' => $user, 'role' => $role]) . "\n";
