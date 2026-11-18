<?php
declare(strict_types=1);

use NicEpp\App\Repo;
use NicEpp\App\Settings;
use NicEpp\Epp\{Client, Commands, Response, Transport};

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(function (string $cls): void {
    if (str_starts_with($cls, 'NicEpp\\')) {
        $f = APP_ROOT . '/src/' . str_replace('\\', '/', substr($cls, 7)) . '.php';
        if (is_file($f)) {
            require $f;
        }
    }
});
require APP_ROOT . '/src/helpers.php';

$cfgFile = getenv('NICEPP_CONFIG') ?: APP_ROOT . '/config.php';
if (!is_file($cfgFile)) {
    http_response_code(500);
    exit("Manca config.php (copia config.sample.php)\n");
}
$GLOBALS['CFG'] = require $cfgFile;
date_default_timezone_set(cfg('app.timezone', 'Europe/Rome'));

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(cfg('db.dsn'), cfg('db.user'), cfg('db.pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function repo(): Repo
{
    static $r = null;
    return $r ??= new Repo(db());
}

function storage(string $f = ''): string
{
    $d = cfg('epp.storage_dir', APP_ROOT . '/storage');
    if (!is_dir($d)) {
        mkdir($d, 0700, true);
    }
    return $f === '' ? $d : $d . '/' . $f;
}

function settings(): Settings
{
    static $s = null;
    return $s ??= new Settings(db(), storage('app.key'), cfg('app.secret') ?: null);
}

/**
 * Parametri EPP modificabili da web: chiave => [etichetta, tipo].
 * Il valore salvato in DB (sezione Configurazione) ha precedenza su config.php.
 */
const EPP_FIELDS = [
    'url'             => ['URL server EPP', 'url'],
    'clid'            => ['Username (clID)', 'text'],
    'password'        => ['Password', 'secret'],
    'tridPrefix'      => ['Prefisso clTRID', 'text'],
    'source_ip'       => ['IP sorgente', 'ip'],
    'ca_file'         => ['Bundle CA', 'file'],
    'client_cert'     => ['Certificato client', 'file'],
    'client_key'      => ['Chiave certificato client', 'file'],
    'timeout'         => ['Timeout richiesta (s)', 'int'],
    'connect_timeout' => ['Timeout connessione (s)', 'int'],
    'idle_timeout'    => ['Relogin dopo inattività (s)', 'int'],
    'dnssec'          => ['DNSSEC (secDNS-1.1)', 'bool'],
];

/** Configurazione EPP effettiva: DB > (solo password) storage/epp-password > config.php */
function epp_cfg(): array
{
    $e = (array)cfg('epp', []);
    foreach (array_keys(EPP_FIELDS) as $k) {
        try {
            $v = settings()->get('epp.' . $k);
        } catch (\RuntimeException $ex) { // password non decifrabile (chiave cambiata): non bloccare l'interfaccia
            $GLOBALS['EPP_CFG_ERROR'] = $ex->getMessage();
            $v = null;
        }
        if ($v !== null) {
            $e[$k] = match (EPP_FIELDS[$k][1]) { 'int' => (int)$v, 'bool' => $v === '1', default => $v };
        }
    }
    if (!settings()->has('epp.password') && is_file(storage('epp-password'))) {
        $e['password'] = trim((string)file_get_contents(storage('epp-password'))); // compatibilità versioni precedenti
    }
    $e['dnssec'] = !empty($e['dnssec']);
    return $e + ['url' => '', 'clid' => '', 'password' => '', 'tridPrefix' => 'WEB'];
}

/** Da dove arriva ogni valore: 'web', 'config' o 'file'. */
function epp_cfg_source(string $k): string
{
    if (settings()->has('epp.' . $k)) {
        return 'web';
    }
    if ($k === 'password' && is_file(storage('epp-password'))) {
        return 'file';
    }
    if (EPP_FIELDS[$k][1] === 'bool') {
        return 'config';
    }
    return cfg('epp.' . $k) !== null && cfg('epp.' . $k) !== '' ? 'config' : '';
}

function importer(): \NicEpp\App\Importer
{
    return new \NicEpp\App\Importer(epp(), repo(), storage('import-job.json'), epp_cfg()['clid']);
}

/** Stato della sessione EPP letto da disco (funziona anche se i parametri EPP non sono ancora configurati). */
function dnssec_enabled(): bool
{
    return epp_cfg()['dnssec'];
}

function epp_state(): array
{
    $f = storage('epp-state.json');
    $s = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    return (is_array($s) ? $s : []) + ['logged_in' => false, 'last' => 0, 'login_at' => 0, 'msgq' => null];
}

function epp(bool $fresh = false): Client
{
    static $c = null;
    if ($c && !$fresh) {
        return $c;
    }
    $e = epp_cfg();
    if (!empty($GLOBALS['EPP_CFG_ERROR'])) {
        throw new \NicEpp\Epp\EppException($GLOBALS['EPP_CFG_ERROR']);
    }
    if ($e['url'] === '' || $e['clid'] === '') {
        throw new \NicEpp\Epp\EppException('Parametri EPP non configurati: impostali in Configurazione');
    }
    $t = new Transport($e['url'], storage('epp-cookies.txt'), $e);
    $c = new Client($t, new Commands($e['tridPrefix'] ?? 'WEB', $e['dnssec']), [
        'clid' => $e['clid'],
        'password' => (string)$e['password'],
        'state_file' => storage('epp-state.json'),
        'lock_file' => storage('epp.lock'),
        'idle_timeout' => $e['idle_timeout'] ?? 600,
    ]);
    $c->setLogger(function (string $cmd, string $obj, string $req, ?Response $res, int $ms, ?string $err) use ($c): void {
        try {
            $u = current_user();
            repo()->logEpp([
                'user_id' => $u['id'] ?? null,
                'username' => $u['username'] ?? (PHP_SAPI === 'cli' ? 'cli' : null),
                'command' => $cmd,
                'object' => mb_substr($obj, 0, 255),
                'cltrid' => $res?->clTRID() ?: $c->commands()->lastTrid(),
                'svtrid' => $res?->svTRID(),
                'result_code' => $res?->code() ?: null,
                'result_msg' => $res ? mb_substr($res->msg(), 0, 255) : null,
                'duration_ms' => $ms,
                'error' => $err,
                'request_xml' => mask_xml($req),
                'response_xml' => $res?->raw(),
            ]);
        } catch (\Throwable $e) {
            error_log('epp log failed: ' . $e->getMessage());
        }
    });
    return $c;
}

function mask_xml(string $xml): string
{
    $xml = preg_replace('#<(pw|newPW)>.*?</\1>#s', '<$1>***</$1>', $xml);
    if (cfg('app.mask_authinfo_in_log')) {
        $xml = preg_replace('#<(\w+:pw)>.*?</\1>#s', '<$1>***</$1>', $xml);
    }
    return $xml;
}
