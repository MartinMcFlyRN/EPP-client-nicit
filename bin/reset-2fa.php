<?php
// Azzera il doppio fattore di un utente (es. admin che ha perso telefono e codici di recupero).
// Uso: php bin/reset-2fa.php <username>
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

$user = $argv[1] ?? null;
$u = $user ? repo()->userByName($user) : null;
if (!$u) {
    fwrite(STDERR, "Uso: php bin/reset-2fa.php <username> (utente esistente)\n");
    exit(1);
}
repo()->resetTotp((int)$u['id']);
echo "Doppio fattore di $user azzerato: al prossimo accesso dovrà configurarlo di nuovo.\n";
