<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

session_name((string)cfg('app.session_name', 'NICEPPSESS'));
session_set_cookie_params([
    'httponly' => true,
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Strict',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// IP abilitati (Configurazione → Sicurezza): lista vuota = tutti
try {
    $allowed = security()->allowedIps();
} catch (\Throwable $e) {
    $allowed = []; // DB non ancora migrato
}
if (!\NicEpp\App\Security::ipMatches(client_ip(), $allowed)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Accesso non consentito da questo indirizzo IP (' . client_ip() . ').');
}

// scadenza della sessione per inattività e durata massima
if (!empty($_SESSION['uid'])) {
    $now = time();
    $_SESSION['login_at'] ??= $now;
    $idle = security()->get('idle_timeout');
    $max = security()->get('max_session');
    $why = null;
    if ($idle > 0 && $now - (int)($_SESSION['seen_at'] ?? $now) > $idle) {
        $why = 'Sei stato disconnesso per inattività';
    } elseif ($max > 0 && $now - (int)$_SESSION['login_at'] > $max) {
        $why = 'La sessione ha raggiunto la durata massima: rientra';
    }
    if ($why) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('err', $why);
        redirect('login');
    }
    $_SESSION['seen_at'] = $now;
}

$routes = require APP_ROOT . '/src/routes.php';
$r = (string)($_GET['r'] ?? 'dashboard');

if (!isset($routes[$r])) {
    http_response_code(404);
    render('error', ['title' => 'Pagina non trovata', 'message' => $r]);
    exit;
}
$public = ['login', 'login/2fa', 'login/setup'];
if (!in_array($r, $public, true)) {
    $cu = current_user();
    if (!$cu) {
        redirect('login');
    }
    if (!$cu['totp_enabled_at']) {
        // 2FA obbligatoria: anche le sessioni già aperte devono configurarla prima di proseguire
        unset($_SESSION['uid']);
        $_SESSION['pre_uid'] = (int)$cu['id'];
        $_SESSION['pre_at'] = time();
        $_SESSION['pre_tries'] = 0;
        redirect('login/setup');
    }
}
if (is_post()) {
    csrf_check();
}

// pulizia dei log oltre la retention (al massimo una volta l'ora, a fine richiesta)
register_shutdown_function(function () {
    try {
        purge_logs_if_due();
    } catch (\Throwable $e) {
        error_log('purge log: ' . $e->getMessage());
    }
});

try {
    $routes[$r]();
} catch (\NicEpp\Epp\EppException $e) {
    $resp = $e->response;
    render('error', [
        'title' => 'Errore EPP',
        'message' => $resp ? $resp->errorText() : $e->getMessage(),
        'xml' => $resp?->pretty(),
    ]);
} catch (\InvalidArgumentException $e) {
    flash('err', $e->getMessage());
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? url('dashboard')));
}
