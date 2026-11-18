<?php
// Importa nella cache locale i domini elencati in un file (CSV o testo, es. export dal portale RAIN).
// Uso: php bin/import-domains.php elenco.csv [--no-contacts] [--skip-recent] [--sleep=200] [--resume]
//   --resume       riprende l'ultimo import interrotto (anche quello avviato da web)
//   --skip-recent  salta i domini già aggiornati nelle ultime 24 ore
//   --sleep=MS     pausa tra un comando e l'altro (default 200 ms)
// Va eseguito con lo stesso utente di PHP-FPM (condivide sessione EPP e lock in storage/).
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

use NicEpp\App\Importer;

$opts = [];
$file = null;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--')) {
        [$k, $v] = explode('=', substr($a, 2), 2) + [1 => true];
        $opts[$k] = $v;
    } else {
        $file = $a;
    }
}
$sleep = (int)($opts['sleep'] ?? 200);
$imp = importer();

if (isset($opts['resume'])) {
    $job = $imp->job();
    if (!$job || $job['finished_at']) {
        fwrite(STDERR, "Nessun import da riprendere\n");
        exit(1);
    }
} else {
    if (!$file || !is_readable($file)) {
        fwrite(STDERR, "Uso: php bin/import-domains.php elenco.csv [--no-contacts] [--skip-recent] [--sleep=200] | --resume\n");
        exit(1);
    }
    $names = Importer::parseNames((string)file_get_contents($file));
    if (!$names) {
        fwrite(STDERR, "Nessun nome .it trovato in $file\n");
        exit(1);
    }
    $job = $imp->start($names, !isset($opts['no-contacts']), isset($opts['skip-recent']), 'cli');
    echo count($names) . " domini da importare\n";
}

$total = count($job['names']);
while (true) {
    $job = $imp->step(60, $sleep, function (array $j, string $name) use ($total) {
        fprintf(STDOUT, "\r[%d/%d] %-60s", $j['pos'], $total, $name);
    });
    if (!empty($job['last_error'])) {
        echo "\nInterrotto: {$job['last_error']}\nRiprendi con: php bin/import-domains.php --resume\n";
        exit(2);
    }
    if ($job['finished_at']) {
        break;
    }
}
printf("\nFatto: %d domini importati (%d di altri registrar, %d saltati), %d contatti, %d errori\n",
    $job['ok'], $job['foreign'], $job['skipped'], $job['contacts_ok'], count($job['errors']));
foreach ($job['errors'] as $e) {
    echo "  ERR {$e['what']}: {$e['msg']}\n";
}
