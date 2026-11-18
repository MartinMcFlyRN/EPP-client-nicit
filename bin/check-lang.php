<?php
// Verifica i file in lang/: stesse chiavi dell'inglese e stessi segnaposto {nome}.
// Uso: php bin/check-lang.php
declare(strict_types=1);

$dir = dirname(__DIR__) . '/lang';
$en = require "$dir/en.php";
$ok = true;
foreach (glob("$dir/*.php") as $f) {
    $code = basename($f, '.php');
    if ($code === 'en') {
        continue;
    }
    $l = require $f;
    $missing = array_diff(array_keys($en), array_keys($l));
    $extra = array_diff(array_keys($l), array_keys($en));
    $ph = [];
    foreach ($en as $k => $v) {
        if (!isset($l[$k])) {
            continue;
        }
        preg_match_all('/\{(\w+)\}/', $v, $a);
        preg_match_all('/\{(\w+)\}/', $l[$k], $b);
        sort($a[1]);
        sort($b[1]);
        if ($a[1] !== $b[1]) {
            $ph[] = $k;
        }
    }
    printf("%s: %d chiavi, %d mancanti, %d in più, %d con segnaposto diversi\n", $code, count($l), count($missing), count($extra), count($ph));
    foreach (['mancanti' => $missing, 'in più' => $extra, 'segnaposto' => $ph] as $what => $list) {
        foreach ($list as $k) {
            echo "  $what: $k\n";
        }
    }
    $ok = $ok && !$missing && !$extra && !$ph;
}
exit($ok ? 0 : 1);
