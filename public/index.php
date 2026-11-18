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
