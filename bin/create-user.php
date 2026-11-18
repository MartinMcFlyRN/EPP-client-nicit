<?php
// Uso: php bin/create-user.php <username> <role:admin|operator|readonly>
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

[$_, $user, $role] = $argv + [null, null, 'admin'];
if (!$user || !isset(ROLE_LEVEL[$role])) {
    fwrite(STDERR, "Uso: php bin/create-user.php <username> [admin|operator|readonly]\n");
    exit(1);
}
fwrite(STDOUT, "Password per $user: ");
system('stty -echo 2>/dev/null');
$pw = trim((string)fgets(STDIN));
system('stty echo 2>/dev/null');
fwrite(STDOUT, "\n");
if (strlen($pw) < 10) {
    fwrite(STDERR, "Password troppo corta (min 10)\n");
    exit(1);
}
repo()->createUser($user, $pw, $role);
echo "Utente $user ($role) creato\n";
