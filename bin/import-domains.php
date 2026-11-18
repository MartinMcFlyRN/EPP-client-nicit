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
        fwrite(STDERR, t('cli.no_import') . "\n");
        exit(1);
    }
} else {
    if (!$file || !is_readable($file)) {
        fwrite(STDERR, t('cli.import_usage') . "\n");
        exit(1);
    }
    $names = Importer::parseNames((string)file_get_contents($file));
    if (!$names) {
        fwrite(STDERR, t('cli.no_names', ['file' => $file]) . "\n");
        exit(1);
    }
    $job = $imp->start($names, !isset($opts['no-contacts']), isset($opts['skip-recent']), 'cli');
    echo t('cli.to_import', ['n' => count($names)]) . "\n";
}

$total = count($job['names']);
while (true) {
    $job = $imp->step(60, $sleep, function (array $j, string $name) use ($total) {
        fprintf(STDOUT, "\r[%d/%d] %-60s", $j['pos'], $total, $name);
    });
    if (!empty($job['last_error'])) {
        echo "\n" . t('cli.interrupted', ['error' => $job['last_error']]) . "\n";
        exit(2);
    }
    if ($job['finished_at']) {
        break;
    }
}
echo "\n" . t('cli.import_done', ['ok' => $job['ok'], 'foreign' => $job['foreign'], 'skipped' => $job['skipped'],
    'contacts' => $job['contacts_ok'], 'errors' => count($job['errors'])]) . "\n";
foreach ($job['errors'] as $e) {
    echo "  ERR {$e['what']}: {$e['msg']}\n";
}
