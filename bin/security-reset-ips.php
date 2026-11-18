<?php
// Svuota la lista "IP abilitati" (Configurazione → Sicurezza), ad esempio se sei rimasto chiuso fuori.
// Uso: php bin/security-reset-ips.php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

settings()->set('sec.allowed_ips', null, 'cli');
echo "Lista IP abilitati svuotata: ora la piattaforma è accessibile da qualsiasi IP.\n";
