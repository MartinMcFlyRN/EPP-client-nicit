<?php
declare(strict_types=1);

function cfg(string $key, mixed $default = null): mixed
{
    $v = $GLOBALS['CFG'] ?? [];
    foreach (explode('.', $key) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) {
            return $default;
        }
        $v = $v[$k];
    }
    return $v;
}

function h(mixed $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $route = '', array $q = []): string
{
    $base = rtrim((string)cfg('app.base_url', ''), '/');
    $q = $route !== '' ? ['r' => $route] + $q : $q;
    return $base . '/index.php' . ($q ? '?' . http_build_query($q) : '');
}

function redirect(string $route = '', array $q = []): never
{
    header('Location: ' . url($route, $q));
    exit;
}

function in(string $k, mixed $d = ''): mixed
{
    $v = $_POST[$k] ?? $_GET[$k] ?? $d;
    return is_string($v) ? trim($v) : $v;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// ---------------------------------------------------------------- sessione web

function current_user(): ?array
{
    static $u = false;
    if ($u !== false) {
        return $u;
    }
    if (PHP_SAPI === 'cli' || empty($_SESSION['uid'])) {
        return $u = null;
    }
    $r = repo()->userById((int)$_SESSION['uid']);
    return $u = ($r && $r['active']) ? $r : null;
}

const ROLE_LEVEL = ['readonly' => 1, 'operator' => 2, 'admin' => 3];

function can(string $role): bool
{
    $u = current_user();
    return $u && ROLE_LEVEL[$u['role']] >= ROLE_LEVEL[$role];
}

function require_role(string $role): void
{
    if (!current_user()) {
        redirect('login');
    }
    if (!can($role)) {
        http_response_code(403);
        render('error', ['title' => 'Accesso negato', 'message' => 'Il tuo ruolo non permette questa operazione.']);
        exit;
    }
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(400);
        exit('Token CSRF non valido. Ricarica la pagina.');
    }
}

function flash(?string $type = null, ?string $msg = null): array
{
    if ($type !== null) {
        $_SESSION['flash'][] = [$type, $msg];
        return [];
    }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function render(string $view, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require APP_ROOT . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require APP_ROOT . '/views/layout.php';
}

// ---------------------------------------------------------------- utilità di dominio

function normalize_domain(string $d): string
{
    $d = strtolower(trim($d, " \t\n\r\0\x0B."));
    if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $d)) {
        $a = idn_to_ascii($d, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($a) {
            return $a;
        }
    }
    return $d;
}

/** Righe di testo → lista (separatori: a capo, virgola, spazio). */
function split_list(string $s): array
{
    return array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,;]+/', $s)), 'strlen')));
}

/** "ns1.example.it 192.0.2.1 2001:db8::1" per riga */
function parse_ns_lines(string $s): array
{
    $out = [];
    foreach (preg_split('/\R/', $s) as $line) {
        $p = preg_split('/[\s,;]+/', trim($line));
        if (!$p || $p[0] === '') {
            continue;
        }
        $host = strtolower(rtrim(array_shift($p), '.'));
        $ips = array_values(array_filter($p, fn($ip) => filter_var($ip, FILTER_VALIDATE_IP)));
        $out[$host] = ['host' => $host, 'ips' => $ips];
    }
    return array_values($out);
}

function ns_to_lines(array $ns): string
{
    return implode("\n", array_map(fn($n) => trim($n['host'] . ' ' . implode(' ', $n['ips'])), $ns));
}

/** "keyTag alg digestType digest" per riga (accetta anche il formato DS del record DNS) */
function parse_ds_lines(string $s): array
{
    $out = [];
    foreach (preg_split('/\R/', $s) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/(\d+)\s+(\d+)\s+(\d+)\s+([0-9A-Fa-f\s]+)$/', $line, $m)) {
            $out[] = ['keyTag' => (int)$m[1], 'alg' => (int)$m[2], 'digestType' => (int)$m[3],
                      'digest' => strtoupper(preg_replace('/\s+/', '', $m[4]))];
        } else {
            throw new InvalidArgumentException('Record DS non valido: ' . $line);
        }
    }
    return $out;
}

function ds_to_lines(array $ds): string
{
    return implode("\n", array_map(fn($d) => "{$d['keyTag']} {$d['alg']} {$d['digestType']} {$d['digest']}", $ds));
}

function ds_key(array $d): string
{
    return $d['keyTag'] . '|' . $d['alg'] . '|' . $d['digestType'] . '|' . strtoupper($d['digest']);
}

/** AuthInfo conforme (8–32 caratteri, lettere maiuscole/minuscole, cifre, simboli). */
function gen_authinfo(int $len = 16): string
{
    $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '-_+=.:'];
    $pw = '';
    foreach ($sets as $s) {
        $pw .= $s[random_int(0, strlen($s) - 1)];
    }
    $all = implode('', $sets);
    while (strlen($pw) < $len) {
        $pw .= $all[random_int(0, strlen($all) - 1)];
    }
    return str_shuffle($pw);
}

const ENTITY_TYPES = [
    1 => '1 — Persona fisica (italiana o estera)',
    2 => '2 — Società / impresa',
    3 => '3 — Libero professionista / ditta individuale / ordine professionale',
    4 => '4 — Ente no-profit',
    5 => '5 — Ente pubblico',
    6 => '6 — Altri soggetti',
    7 => '7 — Soggetto estero equiparato ai precedenti (non persona fisica)',
];

function fmt_date(?string $d): string
{
    if (!$d) {
        return '';
    }
    $t = strtotime($d);
    return $t ? date('d/m/Y H:i', $t) : $d;
}

function status_badge(string $s): string
{
    $cls = match (true) {
        $s === 'ok' => 'ok',
        str_starts_with($s, 'client') => 'client',
        str_starts_with($s, 'pending') || in_array($s, ['redemptionPeriod', 'pendingDelete'], true) => 'warn',
        in_array($s, ['inactive', 'serverHold', 'dnsHold', 'revoked', 'toBeReassigned', 'challenged', 'noRegistrar'], true) => 'bad',
        default => 'srv',
    };
    return '<span class="badge ' . $cls . '">' . h($s) . '</span>';
}

/** Mostra l'esito di una risposta EPP come flash. */
function flash_response(\NicEpp\Epp\Response $r, string $okMsg): bool
{
    if ($r->ok()) {
        flash('ok', $okMsg . ' (' . $r->code() . ' ' . $r->msg() . ')');
        return true;
    }
    flash('err', $r->errorText());
    return false;
}

// ---------------------------------------------------------------- secondo fattore

/** Utente che ha superato la password ma non ancora il secondo fattore (scade dopo 5 minuti). */
function pending_user(): array
{
    $id = (int)($_SESSION['pre_uid'] ?? 0);
    $u = $id ? repo()->userById($id) : null;
    if (!$u || !$u['active'] || time() - (int)($_SESSION['pre_at'] ?? 0) > 300) {
        unset($_SESSION['pre_uid'], $_SESSION['pre_at'], $_SESSION['pre_tries'], $_SESSION['setup_secret']);
        flash('err', 'Sessione di accesso scaduta, rientra');
        redirect('login');
    }
    return $u;
}

function finish_login(array $u, string $to = 'dashboard'): never
{
    session_regenerate_id(true);
    unset($_SESSION['pre_uid'], $_SESSION['pre_at'], $_SESSION['pre_tries']);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['login_at'] = $_SESSION['seen_at'] = time();
    security()->clearFailures(client_ip(), $u['username']);
    repo()->touchLogin((int)$u['id']);
    redirect($to);
}

/** Dopo 5 codici errati si torna alla password; ogni errore conta anche nei limiti di Sicurezza. */
function second_factor_failed(string $msg = 'Codice non valido'): void
{
    $u = repo()->userById((int)($_SESSION['pre_uid'] ?? 0));
    if ($u) {
        security()->recordFailure(client_ip(), $u['username'], '2fa');
        if (($wait = security()->blockedFor(client_ip(), $u['username'])) > 0) {
            unset($_SESSION['pre_uid'], $_SESSION['pre_at'], $_SESSION['pre_tries'], $_SESSION['setup_secret']);
            flash('err', 'Troppi tentativi falliti: riprova tra ' . wait_text($wait));
            redirect('login');
        }
    }
    usleep(500000);
    $_SESSION['pre_tries'] = (int)($_SESSION['pre_tries'] ?? 0) + 1;
    if ($_SESSION['pre_tries'] >= 5) {
        unset($_SESSION['pre_uid'], $_SESSION['pre_at'], $_SESSION['pre_tries'], $_SESSION['setup_secret']);
        flash('err', 'Troppi codici errati, rientra con la password');
        redirect('login');
    }
    flash('err', $msg);
}

// ---------------------------------------------------------------- valori predefiniti (Configurazione)

/** Nameserver e contatti proposti per i nuovi domini e nelle modifiche. */
function epp_defaults(): array
{
    return [
        'ns' => (string)settings()->get('defaults.ns', ''),
        'admin' => (string)settings()->get('defaults.admin', ''),
        'techs' => (string)settings()->get('defaults.techs', ''),
    ];
}

/**
 * Pulsante che riempie un campo con un valore predefinito (gestito da data-fill in layout.php).
 * Se il valore non è impostato il pulsante è disattivato e rimanda alla Configurazione.
 */
function fill_button(string $target, string $value, string $label): string
{
    if (trim($value) !== '') {
        return '<button type="button" class="sec small shrink" data-fill="' . h($target) . '" data-value="' . h($value) . '"'
            . ' title="' . h(str_replace("\n", ', ', $value)) . '">' . h($label) . '</button>';
    }
    $hint = can('admin') ? ' <a class="small" href="' . url('settings') . '#defaults">imposta</a>' : '';
    return '<span class="shrink small mute"><button type="button" class="sec small" disabled title="Non impostato in Configurazione">'
        . h($label) . '</button>' . $hint . '</span>';
}

// ---------------------------------------------------------------- ID contatti

/** Prefisso degli ID contatto: da Configurazione, altrimenti il prefisso clTRID seguito da "-". */
function contact_prefix(): string
{
    $p = (string)settings()->get('defaults.contact_prefix', '');
    if ($p === '') {
        $p = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)(epp_cfg()['tridPrefix'] ?? ''))) ;
        $p = $p !== '' ? substr($p, 0, 9) . '-' : '';
    }
    return $p;
}

/**
 * Genera un ID contatto casuale con il prefisso dato, verificando con contact:check
 * che sia libero presso il Registro (e assente nella cache locale).
 */
function generate_contact_id(string $prefix): string
{
    $len = max(4, min(8, 16 - strlen($prefix)));
    $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    for ($round = 0; $round < 3; $round++) {
        $cand = [];
        while (count($cand) < 5) {
            $s = '';
            for ($i = 0; $i < $len; $i++) {
                $s .= $alpha[random_int(0, strlen($alpha) - 1)];
            }
            $id = $prefix . $s;
            if (!repo()->contact($id)) {
                $cand[$id] = true;
            }
        }
        $r = epp()->contactCheck(array_keys($cand));
        if (!$r->ok()) {
            throw \NicEpp\Epp\EppException::fromResponse('Verifica disponibilità ID non riuscita', $r);
        }
        foreach ($r->contactCheck() as $c) {
            if ($c['avail']) {
                return $c['id'];
            }
        }
    }
    throw new InvalidArgumentException('Impossibile generare un ID libero: riprova o inseriscilo a mano');
}

/** Intestazione di colonna cliccabile: alterna crescente/decrescente e mantiene gli altri filtri. */
function sort_th(string $route, string $col, string $label, string $sort, string $dir, array $keep = []): string
{
    $active = $sort === $col;
    $next = $active && $dir === 'asc' ? 'desc' : 'asc';
    $arrow = $active ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<th><a class="sort' . ($active ? ' on' : '') . '" href="' . url($route, array_filter($keep, 'strlen') + ['sort' => $col, 'dir' => $next])
        . '">' . h($label) . $arrow . '</a></th>';
}

// ---------------------------------------------------------------- retention log

function log_retention_days(): int
{
    return (int)settings()->get('log.retention_days', '0');
}

/**
 * Cancella i log EPP oltre la retention impostata. Gira al massimo una volta l'ora
 * (a fine richiesta web e nel cron poll), oppure subito con $force.
 */
function purge_logs_if_due(bool $force = false): ?int
{
    $days = log_retention_days();
    if ($days <= 0) {
        return null;
    }
    $f = storage('log-purge.txt');
    if (!$force && is_file($f) && time() - (int)file_get_contents($f) < 3600) {
        return null;
    }
    @file_put_contents($f, (string)time());
    return repo()->purgeLogs($days);
}

// ---------------------------------------------------------------- sicurezza

function security(): \NicEpp\App\Security
{
    static $s = null;
    return $s ??= new \NicEpp\App\Security(db(), settings());
}

/** IP del client. Dietro un reverse proxy assicurati che il web server imposti REMOTE_ADDR all'IP reale. */
function client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

function min_password(): int
{
    return security()->get('min_password');
}

function wait_text(int $s): string
{
    return $s >= 120 ? ceil($s / 60) . ' minuti' : $s . ' secondi';
}

/** Cookie "ricorda questo computer" (HttpOnly, SameSite Strict, Secure se HTTPS). */
function set_trust_cookie(string $value, int $expires): void
{
    setcookie(\NicEpp\App\Security::COOKIE, $value, [
        'expires' => $expires, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}
