<?php
declare(strict_types=1);

use NicEpp\App\Repo;
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

function epp_password(): string
{
    $f = storage('epp-password');
    return is_file($f) ? trim((string)file_get_contents($f)) : (string)cfg('epp.password');
}

function epp(): Client
{
    static $c = null;
    if ($c) {
        return $c;
    }
    $e = cfg('epp');
    $t = new Transport($e['url'], storage('epp-cookies.txt'), $e);
    $c = new Client($t, new Commands($e['tridPrefix'] ?? 'WEB'), [
        'clid' => $e['clid'],
        'password' => epp_password(),
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
