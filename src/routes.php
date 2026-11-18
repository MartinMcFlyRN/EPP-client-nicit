<?php
declare(strict_types=1);

use NicEpp\Epp\Commands;
use NicEpp\Epp\Response;

/** Esegue info dominio, aggiorna la cache e restituisce [Response, array info]. */
$domainInfo = function (string $name, ?string $auth = null): array {
    $r = epp()->domainInfo($name, $auth);
    $i = $r->ok() ? $r->domainInfo() : null;
    if ($i && $i['name']) {
        repo()->saveDomain($i);
    }
    return [$r, $i];
};

$contactInfo = function (string $id): array {
    $r = epp()->contactInfo($id);
    $i = $r->ok() ? $r->contactInfo() : null;
    if ($i && $i['id']) {
        repo()->saveContact($i);
    }
    return [$r, $i];
};

/** Raccoglie i campi contatto dal POST. */
$contactFromPost = function (): array {
    $c = [
        'id' => strtoupper(in('id')),
        'name' => in('name'),
        'org' => in('org'),
        'street' => [in('street1'), in('street2'), in('street3')],
        'city' => in('city'),
        'sp' => strtoupper(in('sp')),
        'pc' => in('pc'),
        'cc' => strtoupper(in('cc', 'IT')),
        'voice' => in('voice'),
        'fax' => in('fax'),
        'email' => in('email'),
        'consent' => in('consent') === '1',
        'registrant' => null,
    ];
    if (in('is_registrant') === '1') {
        $c['registrant'] = [
            'nationality' => strtoupper(in('nationality', 'IT')),
            'entityType' => (int)in('entityType', 1),
            'regCode' => strtoupper(in('regCode')),
        ];
    }
    foreach (['id' => t('con.id'), 'name' => t('con.name'), 'city' => t('con.city'), 'cc' => t('con.country'), 'email' => 'Email'] as $k => $l) {
        if ($c[$k] === '') {
            throw new InvalidArgumentException(t('err.required', ['field' => $l]));
        }
    }
    if ($c['voice'] !== '' && !preg_match('/^\+\d{1,3}\.\d{1,14}$/', $c['voice'])) {
        throw new InvalidArgumentException(t('err.phone'));
    }
    if ($c['fax'] !== '' && !preg_match('/^\+\d{1,3}\.\d{1,14}$/', $c['fax'])) {
        throw new InvalidArgumentException(t('err.fax'));
    }
    return $c;
};

return [

    // ============================================================ autenticazione

    'login' => function () {
        if (is_post()) {
            $name = in('username');
            $ip = client_ip();
            security()->purgeFailures();
            if (($wait = security()->blockedFor($ip, $name)) > 0) {
                flash('err', t('err.too_many_signin', ['wait' => wait_text($wait)]));
                redirect('login');
            }
            $u = repo()->userByName($name);
            if ($u && $u['active'] && password_verify((string)($_POST['password'] ?? ''), $u['pass_hash'])) {
                session_regenerate_id(true);
                unset($_SESSION['uid'], $_SESSION['setup_secret']);
                // computer ricordato dopo un 2FA precedente: si entra senza codice
                if ($u['totp_enabled_at'] && security()->deviceTrusted((int)$u['id'], (string)($_COOKIE[\NicEpp\App\Security::COOKIE] ?? ''))) {
                    finish_login($u);
                }
                // primo fattore superato: l'accesso si completa solo dopo il secondo
                $_SESSION['pre_uid'] = (int)$u['id'];
                $_SESSION['pre_at'] = time();
                $_SESSION['pre_tries'] = 0;
                redirect($u['totp_enabled_at'] ? 'login/2fa' : 'login/setup');
            }
            security()->recordFailure($ip, $name, 'password');
            usleep(500000);
            flash('err', t('err.bad_credentials'));
            redirect('login');
        }
        render('login', ['bare' => true]);
    },

    'lang' => function () {
        // scelta della lingua dalla pagina di login (prima dell'accesso)
        set_lang_cookie((string)in('l'));
        redirect(current_user() ? 'account' : 'login');
    },

    'login/2fa' => function () {
        $u = pending_user();
        if (!$u['totp_enabled_at']) {
            redirect('login/setup');
        }
        if (is_post()) {
            $code = (string)($_POST['code'] ?? '');
            $ok = false;
            if (preg_match('/[A-Za-z]/', $code) || strlen(\NicEpp\App\Totp::normalizeRecovery($code)) === 10) {
                // codice di recupero monouso
                $hashes = json_decode((string)$u['recovery_codes'], true) ?: [];
                foreach ($hashes as $i => $hsh) {
                    if (password_verify(\NicEpp\App\Totp::normalizeRecovery($code), $hsh)) {
                        unset($hashes[$i]);
                        repo()->setRecoveryCodes((int)$u['id'], $hashes);
                        flash('ok', t('ok.recovery_used', ['n' => count($hashes)]));
                        $ok = true;
                        break;
                    }
                }
            } else {
                $step = \NicEpp\App\Totp::verify(settings()->decrypt((string)$u['totp_secret']), $code, (int)$u['totp_last_step']);
                if ($step !== null) {
                    repo()->setTotpStep((int)$u['id'], $step);
                    $ok = true;
                }
            }
            if ($ok) {
                if (in('remember') === '1' && security()->get('remember_days') > 0) {
                    [$val, $exp] = security()->trustDevice((int)$u['id'], client_ip(), (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
                    set_trust_cookie($val, $exp);
                }
                finish_login($u);
            }
            second_factor_failed();
            redirect('login/2fa');
        }
        render('login_2fa', ['bare' => true, 'u' => $u, 'remember' => security()->get('remember_days')]);
    },

    'login/setup' => function () {
        $u = pending_user();
        if ($u['totp_enabled_at']) {
            redirect('login/2fa');
        }
        $secret = $_SESSION['setup_secret'] ??= \NicEpp\App\Totp::newSecret();
        if (is_post()) {
            $step = \NicEpp\App\Totp::verify($secret, (string)($_POST['code'] ?? ''));
            if ($step === null) {
                second_factor_failed(t('err.setup_code'));
                redirect('login/setup');
            }
            $codes = \NicEpp\App\Totp::recoveryCodes();
            repo()->enableTotp((int)$u['id'], settings()->encrypt($secret), array_map(
                fn($c) => password_hash(\NicEpp\App\Totp::normalizeRecovery($c), PASSWORD_DEFAULT), $codes), $step);
            unset($_SESSION['setup_secret']);
            $_SESSION['show_recovery'] = $codes;
            finish_login($u, 'account/recovery');
        }
        $issuer = (string)cfg('app.name', 'EPP .it');
        render('login_setup', ['bare' => true, 'u' => $u, 'secret' => $secret,
            'uri' => \NicEpp\App\Totp::uri($secret, $u['username'], $issuer)]);
    },

    'account/recovery' => function () {
        $codes = $_SESSION['show_recovery'] ?? null;
        unset($_SESSION['show_recovery']);
        if (!$codes) {
            redirect('account');
        }
        render('recovery', ['codes' => $codes]);
    },

    'account' => function () {
        $u = current_user();
        if (is_post() && in('action') === 'lang') {
            $lang = (string)in('lang');
            if (!isset(languages()[$lang])) {
                throw new InvalidArgumentException(t('err.bad_lang'));
            }
            repo()->setUserLang((int)$u['id'], $lang);
            set_lang_cookie($lang);
            $GLOBALS['__lang_user']['lang'] = $lang;
            flash('ok', t('ok.lang_saved'));
            redirect('account');
        }
        if (is_post() && in('revoke') !== '') {
            in('revoke') === 'all' ? security()->revokeAllDevices((int)$u['id']) : security()->revokeDevice((int)$u['id'], (int)in('revoke'));
            flash('ok', t(in('revoke') === 'all' ? 'ok.devices_revoked' : 'ok.device_revoked'));
            redirect('account');
        }
        if (is_post()) {
            $step = \NicEpp\App\Totp::verify(settings()->decrypt((string)$u['totp_secret']), (string)($_POST['code'] ?? ''), (int)$u['totp_last_step']);
            if ($step === null) {
                throw new InvalidArgumentException(t('err.app_code'));
            }
            repo()->setTotpStep((int)$u['id'], $step);
            if (in('action') === 'password') {
                $pw = (string)($_POST['new_pw'] ?? '');
                if (strlen($pw) < min_password() || $pw !== (string)($_POST['new_pw2'] ?? '')) {
                    throw new InvalidArgumentException(t('err.password_match', ['n' => min_password()]));
                }
                repo()->updateUser((int)$u['id'], $u['role'], (bool)$u['active'], $pw);
                security()->revokeAllDevices((int)$u['id']);
                flash('ok', t('ok.password_changed'));
                redirect('account');
            }
            $codes = \NicEpp\App\Totp::recoveryCodes();
            repo()->setRecoveryCodes((int)$u['id'], array_map(
                fn($c) => password_hash(\NicEpp\App\Totp::normalizeRecovery($c), PASSWORD_DEFAULT), $codes));
            $_SESSION['show_recovery'] = $codes;
            redirect('account/recovery');
        }
        render('account', ['u' => $u, 'left' => count(json_decode((string)$u['recovery_codes'], true) ?: []),
            'devices' => security()->devices((int)$u['id']), 'remember' => security()->get('remember_days')]);
    },

    'logout' => function () {
        if (is_post()) {
            $_SESSION = [];
            session_destroy();
        }
        redirect('login');
    },

    // ============================================================ dashboard / sessione EPP

    'dashboard' => function () {
        render('dashboard', [
            'state' => epp_state(),
            'expiring' => array_slice(array_filter(repo()->domains('', 'ex_date', 'asc', 200),
                fn($d) => $d['ex_date'] && strtotime($d['ex_date']) < strtotime('+45 days')), 0, 20),
            'recent' => repo()->logs([], 15),
        ]);
    },

    'session/hello' => function () {
        require_role('readonly');
        $r = epp()->hello();
        render('xml', ['title' => t('title.greeting'), 'res' => $r]);
    },

    'session/login' => function () {
        require_role('operator');
        $r = epp()->login();
        flash_response($r, 'Login EPP eseguito');
        redirect('dashboard');
    },

    'session/logout' => function () {
        require_role('operator');
        $r = epp()->logout();
        $r ? flash_response($r, t('ok.epp_logout')) : flash('ok', t('ok.no_session'));
        redirect('dashboard');
    },

    'session/password' => function () {
        require_role('admin');
        if (is_post()) {
            $new = (string)($_POST['new_pw'] ?? '');
            if ($new !== (string)($_POST['new_pw2'] ?? '') || strlen($new) < 8 || strlen($new) > 16) {
                throw new InvalidArgumentException(t('err.epp_password'));
            }
            $r = epp()->login($new);
            settings()->set('epp.password', $new, current_user()['username']);
            @unlink(storage('epp-password'));
            flash_response($r, t('ok.epp_password'));
            redirect('dashboard');
        }
        render('password');
    },

    // ============================================================ configurazione EPP (admin)

    'settings' => function () {
        require_role('admin');
        if (is_post() && in('section') === 'security') {
            $by = current_user()['username'];
            $ips = \NicEpp\App\Security::parseIpList(in('allowed_ips'));
            if ($ips && !\NicEpp\App\Security::ipMatches(client_ip(), $ips)) {
                throw new InvalidArgumentException(t('err.ip_lockout', ['ip' => client_ip()]));
            }
            $vals = [];
            foreach (\NicEpp\App\Security::FIELDS as $k => [$label, $def, $min, $max]) {
                $v = in($k);
                if ($v === '') {
                    $vals[$k] = null; // torna al valore predefinito
                    continue;
                }
                if (!ctype_digit($v) || (int)$v < $min || (int)$v > $max) {
                    throw new InvalidArgumentException(t('err.number_range', ['field' => t('sec.' . $k), 'min' => $min, 'max' => $max]));
                }
                $vals[$k] = (string)(int)$v;
            }
            $idle = (int)($vals['idle_timeout'] ?? \NicEpp\App\Security::FIELDS['idle_timeout'][1]);
            if ($idle > 0 && $idle < 60) {
                throw new InvalidArgumentException(t('err.idle_min'));
            }
            settings()->set('sec.allowed_ips', implode("\n", $ips), $by);
            foreach ($vals as $k => $v) {
                settings()->set("sec.$k", $v, $by);
            }
            if (($vals['remember_days'] ?? null) === '0') {
                db()->exec('DELETE FROM trusted_devices'); // funzione disattivata: revoca tutti i computer ricordati
            }
            flash('ok', t('ok.security_saved'));
            redirect('settings');
        }
        if (is_post() && in('section') === 'logs') {
            $days = in('retention_days');
            if ($days !== '' && (!ctype_digit($days) || (int)$days < 1 || (int)$days > 3650)) {
                throw new InvalidArgumentException(t('err.retention'));
            }
            settings()->set('log.retention_days', $days === '' ? null : (string)(int)$days, current_user()['username']);
            $n = purge_logs_if_due(true);
            flash('ok', $days === '' ? t('ok.retention_off')
                : t('ok.retention_set', ['days' => $days]) . ($n ? ' ' . t('ok.retention_purged', ['n' => $n]) : ''));
            redirect('settings');
        }
        if (is_post() && in('section') === 'defaults') {
            $by = current_user()['username'];
            $ns = parse_ns_lines(in('def_ns'));
            if ($ns && (count($ns) < 2 || count($ns) > 6)) {
                throw new InvalidArgumentException(t('err.def_ns'));
            }
            $cpref = strtoupper(in('def_contact_prefix'));
            if ($cpref !== '' && !preg_match('/^[A-Z0-9-]{1,10}$/', $cpref)) {
                throw new InvalidArgumentException(t('err.contact_prefix'));
            }
            settings()->set('defaults.contact_prefix', $cpref, $by);
            $admin = strtoupper(in('def_admin'));
            $techs = split_list(strtoupper(in('def_techs')));
            foreach (array_merge($admin !== '' ? [$admin] : [], $techs) as $id) {
                if (!preg_match('/^[A-Z0-9._-]{3,16}$/', $id)) {
                    throw new InvalidArgumentException(t('err.contact_id', ['id' => $id]));
                }
            }
            if (count($techs) > 6) {
                throw new InvalidArgumentException(t('err.max_techs'));
            }
            settings()->set('defaults.ns', ns_to_lines($ns), $by);
            settings()->set('defaults.admin', $admin, $by);
            settings()->set('defaults.techs', implode("\n", $techs), $by);
            flash('ok', t('ok.defaults_saved'));

            // verifica (non bloccante) che i contatti esistano presso il Registro
            $ids = array_values(array_unique(array_merge($admin !== '' ? [$admin] : [], $techs)));
            if ($ids) {
                try {
                    $r = epp()->contactCheck($ids);
                    if ($r->ok()) {
                        $missing = array_column(array_filter($r->contactCheck(), fn($c) => $c['avail']), 'id');
                        if ($missing) {
                            flash('err', t('err.contacts_missing', ['ids' => implode(', ', $missing)]));
                        }
                    }
                } catch (\Throwable) {
                    // EPP non raggiungibile: la verifica si salta
                }
            }
            redirect('settings');
        }
        if (is_post()) {
            $by = current_user()['username'];
            $new = [];
            foreach (EPP_FIELDS as $k => [$label, $type]) {
                $reset = in("reset_$k") === '1';
                $v = $type === 'secret' ? (string)($_POST[$k] ?? '') : in($k);
                if ($reset) {
                    $new[$k] = null;
                    continue;
                }
                if ($type === 'secret' && $v === '') {
                    continue; // password lasciata vuota = invariata
                }
                if ($v !== '') {
                    $ok = match ($type) {
                        'url'  => preg_match('#^https://[a-z0-9.-]+(:\d+)?(/.*)?$#i', $v)
                                  || preg_match('#^http://(127\.0\.0\.1|localhost)(:\d+)?(/.*)?$#i', $v),
                        'ip'   => (bool)filter_var($v, FILTER_VALIDATE_IP),
                        'int'  => ctype_digit($v) && (int)$v >= 1 && (int)$v <= 86400,
                        'bool' => in_array($v, ['0', '1'], true),
                        'file' => is_file($v) && is_readable($v),
                        'text' => (bool)preg_match('/^[\w.-]{1,64}$/', $v),
                        default => true,
                    };
                    if (!$ok) {
                        throw new InvalidArgumentException(t($type === 'file' ? 'err.invalid_file' : 'err.invalid_value', ['field' => t('set.epp.' . $k)]));
                    }
                }
                $new[$k] = $v;
            }

            // Se cambiano server o credenziali, chiude la sessione aperta con i vecchi parametri
            $old = epp_cfg();
            $sessionKeys = ['url', 'clid', 'password', 'source_ip', 'ca_file', 'client_cert', 'client_key', 'dnssec'];
            $changed = false;
            foreach ($sessionKeys as $k) {
                if (!array_key_exists($k, $new)) {
                    continue;
                }
                $nv = $new[$k] ?? cfg("epp.$k", '');
                $ov = $old[$k] ?? '';
                if ($k === 'dnssec') {
                    [$nv, $ov] = [in_array($nv, ['1', 1, true], true) ? '1' : '0', $ov ? '1' : '0'];
                }
                if ((string)$nv !== (string)$ov) {
                    $changed = true;
                }
            }
            if ($changed && epp_state()['logged_in']) {
                try { epp()->logout(); } catch (\Throwable) { /* sessione forse già scaduta */ }
            }
            foreach ($new as $k => $v) {
                settings()->set("epp.$k", $v, $by);
            }
            if ($changed) {
                @file_put_contents(storage('epp-cookies.txt'), '');
                @file_put_contents(storage('epp-state.json'), json_encode(['logged_in' => false, 'last' => 0, 'login_at' => 0, 'msgq' => null]));
                if (array_key_exists('password', $new) && $new['password'] !== null) {
                    @unlink(storage('epp-password'));
                }
            }
            flash('ok', t($changed ? 'ok.settings_saved_relogin' : 'ok.settings_saved'));

            if (in('test') === '1') {
                try {
                    $c = epp(true);
                    $c->hello();
                    $r = $c->login();
                    flash('ok', t('ok.test', ['code' => $r->code(), 'msg' => $r->msg()]));
                } catch (\Throwable $e) {
                    flash('err', t('err.test', ['error' => $e instanceof \NicEpp\Epp\EppException && $e->response ? $e->response->errorText() : $e->getMessage()]));
                }
            }
            redirect('settings');
        }
        render('settings', ['e' => epp_cfg(), 'meta' => settings()->meta(), 'logStats' => repo()->logStats()]);
    },

    // ============================================================ domini

    'domains' => function () {
        require_role('readonly');
        $sort = in('sort', 'name');
        $dir = in('dir', 'asc');
        render('domains', ['q' => in('q'), 'sort' => $sort, 'dir' => $dir, 'total' => repo()->countDomains(),
            'rows' => repo()->domains(in('q'), $sort, $dir)]);
    },

    'domains/check' => function () {
        require_role('readonly');
        $results = null;
        $names = in('names');
        if ($names !== '') {
            $list = array_map('normalize_domain', split_list($names));
            $results = [];
            foreach (array_chunk($list, 5) as $chunk) { // il Registro accetta più nomi per check: lotti piccoli
                $r = epp()->domainCheck($chunk);
                if (!$r->ok()) {
                    flash('err', $r->errorText());
                    break;
                }
                $results = array_merge($results, $r->domainCheck());
            }
        }
        render('domain_check', ['names' => $names, 'results' => $results]);
    },

    'domains/info' => function () use ($domainInfo) {
        require_role('readonly');
        $name = normalize_domain(in('name'));
        if ($name === '') {
            redirect('domains');
        }
        [$r, $i] = $domainInfo($name, in('auth') ?: null);
        if (!$r->ok()) {
            flash('err', $r->errorText());
        }
        render('domain_view', ['name' => $name, 'res' => $r, 'd' => $i, 'ours' => $i && $i['clID'] === epp_cfg()['clid']]);
    },

    'domains/create' => function () {
        require_role('operator');
        if (is_post()) {
            if (in('admin_is_registrant') === '1') {
                $_POST['admin'] = in('registrant'); // es. registrante persona fisica
            }
            $name = normalize_domain(in('name'));
            $ns = parse_ns_lines(in('ns'));
            $techs = split_list(strtoupper(in('techs')));
            $ds = dnssec_enabled() ? parse_ds_lines(in('ds')) : [];
            $auth = in('authinfo') ?: gen_authinfo();
            if ($name === '' || in('registrant') === '' || in('admin') === '' || !$techs) {
                throw new InvalidArgumentException(t('err.create_required'));
            }
            if (count($ns) < 2 || count($ns) > 6) {
                throw new InvalidArgumentException(t('err.ns_count'));
            }
            if (count($techs) > 6) {
                throw new InvalidArgumentException(t('err.max_techs'));
            }
            $c = epp()->commands();
            $r = epp()->exec('domain:create', $name, fn() => $c->domainCreate(
                $name, $ns, strtoupper(in('registrant')), strtoupper(in('admin')), $techs, $auth, $ds
            ));
            if (flash_response($r, t('ok.domain_created', ['name' => $name]))) {
                $cd = $r->createData();
                repo()->saveDomain(['name' => $name, 'registrant' => strtoupper(in('registrant')),
                    'status' => [], 'clID' => epp_cfg()['clid'], 'crDate' => $cd['crDate'], 'exDate' => $cd['exDate']]);
                redirect('domains/info', ['name' => $name]);
            }
            render('domain_create', ['f' => $_POST, 'res' => $r]);
            return;
        }
        $def = epp_defaults();
        render('domain_create', ['f' => ['name' => in('name'), 'authinfo' => gen_authinfo(),
            'ns' => $def['ns'], 'admin' => $def['admin'], 'techs' => $def['techs']], 'res' => null]);
    },

    'domains/update' => function () use ($domainInfo) {
        require_role('operator');
        if (!is_post()) {
            redirect('domains');
        }
        $name = normalize_domain(in('name'));
        [$cur, $d] = $domainInfo($name);
        if (!$d) {
            throw \NicEpp\Epp\EppException::fromResponse(t('err.current_state'), $cur);
        }
        $c = epp()->commands();
        $act = in('action');
        $add = $rem = $chg = $sec = [];

        switch ($act) {
            case 'ns':
                $new = parse_ns_lines(in('ns'));
                if (count($new) < 2 || count($new) > 6) {
                    throw new InvalidArgumentException(t('err.ns_count'));
                }
                $key = fn($n) => $n['host'] . '|' . implode(',', array_map('strtolower', $n['ips']));
                $curK = array_combine(array_map($key, $d['ns']), $d['ns']);
                $newK = array_combine(array_map($key, $new), $new);
                $add['ns'] = array_values(array_diff_key($newK, $curK));
                $rem['ns'] = array_values(array_diff_key($curK, $newK));
                $msg = t('ok.ns_updated');
                break;

            case 'contacts':
                $curT = array_column(array_filter($d['contacts'], fn($x) => $x['type'] === 'tech'), 'id');
                $curA = array_column(array_filter($d['contacts'], fn($x) => $x['type'] === 'admin'), 'id');
                $newT = split_list(strtoupper(in('techs')));
                $newA = split_list(strtoupper(in('admin')));
                if (count($newA) !== 1 || !$newT || count($newT) > 6) {
                    throw new InvalidArgumentException(t('err.contacts_count'));
                }
                foreach (array_diff($newA, $curA) as $i) { $add['contacts'][] = ['type' => 'admin', 'id' => $i]; }
                foreach (array_diff($newT, $curT) as $i) { $add['contacts'][] = ['type' => 'tech', 'id' => $i]; }
                foreach (array_diff($curA, $newA) as $i) { $rem['contacts'][] = ['type' => 'admin', 'id' => $i]; }
                foreach (array_diff($curT, $newT) as $i) { $rem['contacts'][] = ['type' => 'tech', 'id' => $i]; }
                $msg = t('ok.contacts_updated');
                break;

            case 'status':
                $want = array_values(array_intersect((array)($_POST['status'] ?? []), Commands::DOMAIN_CLIENT_STATUSES));
                $have = array_values(array_intersect($d['status'], Commands::DOMAIN_CLIENT_STATUSES));
                $add['status'] = array_values(array_diff($want, $have));
                $rem['status'] = array_values(array_diff($have, $want));
                $msg = t('ok.status_updated');
                break;

            case 'authinfo':
                $chg['authInfo'] = in('authinfo') ?: gen_authinfo();
                $msg = t('ok.authinfo_changed');
                break;

            case 'registrant':
                $chg['registrant'] = strtoupper(in('registrant'));
                $chg['authInfo'] = in('authinfo') ?: gen_authinfo(); // obbligatorio e diverso dal precedente
                if ($chg['registrant'] === '') {
                    throw new InvalidArgumentException(t('err.new_registrant'));
                }
                if (in('admin') !== '') {
                    $old = array_column(array_filter($d['contacts'], fn($x) => $x['type'] === 'admin'), 'id');
                    $newA = strtoupper(in('admin'));
                    if (!in_array($newA, $old, true)) {
                        $add['contacts'][] = ['type' => 'admin', 'id' => $newA];
                        foreach ($old as $o) { $rem['contacts'][] = ['type' => 'admin', 'id' => $o]; }
                    }
                }
                $msg = t('ok.registrant_changed');
                break;

            case 'dnssec':
                if (!dnssec_enabled()) {
                    throw new InvalidArgumentException(t('err.dnssec_off'));
                }
                $new = parse_ds_lines(in('ds'));
                $curK = array_combine(array_map('ds_key', $d['ds']), $d['ds']) ?: [];
                $newK = $new ? array_combine(array_map('ds_key', $new), $new) : [];
                if (!$newK && $curK) {
                    $sec['remAll'] = true;
                } else {
                    $sec['rem'] = array_values(array_diff_key($curK, $newK));
                    $sec['add'] = array_values(array_diff_key($newK, $curK));
                }
                $msg = 'DNSSEC aggiornato';
                break;

            default:
                throw new InvalidArgumentException(t('err.bad_action'));
        }

        $nothing = empty(array_filter($add)) && empty(array_filter($rem)) && empty($chg) && empty(array_filter($sec));
        if ($nothing) {
            flash('ok', t('ok.nothing_to_send'));
            redirect('domains/info', ['name' => $name]);
        }
        $r = epp()->exec('domain:update', $name, fn() => $c->domainUpdate($name, $add, $rem, $chg, $sec));
        if (flash_response($r, $msg) && isset($chg['authInfo'])) {
            flash('ok', t('ok.new_authinfo', ['auth' => $chg['authInfo']]));
        }
        redirect('domains/info', ['name' => $name]);
    },

    'domains/delete' => function () {
        require_role('operator');
        $name = normalize_domain(in('name'));
        if (!is_post() || in('confirm') !== $name) {
            throw new InvalidArgumentException(t('err.delete_confirm'));
        }
        $c = epp()->commands();
        $r = epp()->exec('domain:delete', $name, fn() => $c->domainDelete($name));
        flash_response($r, t('ok.delete_requested', ['name' => $name]));
        redirect('domains/info', ['name' => $name]);
    },

    'domains/restore' => function () {
        require_role('operator');
        if (!is_post()) {
            redirect('domains');
        }
        $name = normalize_domain(in('name'));
        $c = epp()->commands();
        $r = epp()->exec('domain:restore', $name, fn() => $c->domainRestore($name));
        flash_response($r, t('ok.restored', ['name' => $name]));
        redirect('domains/info', ['name' => $name]);
    },

    'domains/transfer' => function () {
        require_role('readonly');
        $res = null;
        $data = null;
        if (is_post()) {
            $op = in('op');
            if (!in_array($op, ['request', 'query', 'approve', 'reject', 'cancel'], true)) {
                throw new InvalidArgumentException(t('err.bad_operation'));
            }
            if ($op !== 'query') {
                require_role('operator');
            }
            $name = normalize_domain(in('name'));
            $auth = in('authinfo');
            $trade = $op === 'request' && in('trade') === '1';
            $newReg = $trade ? strtoupper(in('new_registrant')) : null;
            $newAuth = $trade ? (in('new_authinfo') ?: gen_authinfo()) : null;
            if ($op === 'request' && $auth === '') {
                throw new InvalidArgumentException(t('err.transfer_authinfo'));
            }
            if ($trade && !$newReg) {
                throw new InvalidArgumentException(t('err.trade_registrant'));
            }
            $c = epp()->commands();
            $res = epp()->exec("domain:transfer:$op", $name, fn() => $c->domainTransfer($op, $name, $auth, $newReg, $newAuth));
            if (flash_response($res, t('ok.transfer', ['op' => $op, 'name' => $name]))) {
                $data = $res->transferData();
                if ($newAuth) {
                    flash('ok', t('ok.new_authinfo_trade', ['auth' => $newAuth]));
                }
            }
        }
        render('domain_transfer', ['f' => $_POST + ['name' => in('name'), 'op' => in('op', 'request')], 'res' => $res, 'data' => $data]);
    },

    // ============================================================ contatti

    'contacts' => function () {
        require_role('readonly');
        $sort = in('sort', 'id');
        $dir = in('dir', 'asc');
        render('contacts', ['q' => in('q'), 'sort' => $sort, 'dir' => $dir, 'total' => repo()->countContacts(),
            'rows' => repo()->contacts(in('q'), $sort, $dir)]);
    },

    'contacts/search' => function () {
        require_role('readonly');
        header('Content-Type: application/json');
        echo json_encode(repo()->searchContacts((string)in('q'), in('registrant') === '1'), JSON_UNESCAPED_UNICODE);
    },

    'contacts/check' => function () {
        require_role('readonly');
        $results = null;
        if (in('ids') !== '') {
            $r = epp()->contactCheck(split_list(strtoupper(in('ids'))));
            $r->ok() ? $results = $r->contactCheck() : flash('err', $r->errorText());
        }
        render('contact_check', ['ids' => in('ids'), 'results' => $results]);
    },

    'contacts/info' => function () use ($contactInfo) {
        require_role('readonly');
        $id = strtoupper(in('id'));
        [$r, $i] = $contactInfo($id);
        if (!$r->ok()) {
            flash('err', $r->errorText());
        }
        render('contact_view', ['id' => $id, 'res' => $r, 'c' => $i, 'domains' => repo()->domainsByContact($id)]);
    },

    'contacts/create' => function () use ($contactFromPost) {
        require_role('operator');
        if (is_post()) {
            // ID = prefisso da Configurazione + parte scelta; se la parte è vuota viene generata e verificata libera
            $prefix = contact_prefix();
            $suffix = strtoupper(in('id_suffix'));
            if ($suffix !== '') {
                if (!preg_match('/^[A-Z0-9-]+$/', $suffix)) {
                    throw new InvalidArgumentException(t('err.id_chars'));
                }
                $_POST['id'] = $prefix . $suffix;
                if (strlen($_POST['id']) < 3 || strlen($_POST['id']) > 16) {
                    throw new InvalidArgumentException(t('err.id_length', ['n' => strlen($_POST['id'])]));
                }
            } else {
                foreach (['name' => t('con.name'), 'city' => t('con.city'), 'email' => 'Email'] as $k => $l) {
                    if (in($k) === '') { // evita di generare un ID per un modulo incompleto
                        throw new InvalidArgumentException(t('err.required', ['field' => $l]));
                    }
                }
                $_POST['id'] = generate_contact_id($prefix);
            }
            $c = $contactFromPost();
            $c['authInfo'] = gen_authinfo();
            $cmd = epp()->commands();
            $r = epp()->exec('contact:create', $c['id'], fn() => $cmd->contactCreate($c));
            if (flash_response($r, t('ok.contact_created', ['id' => $c['id']]))) {
                repo()->saveContact($c);
                redirect('contacts/info', ['id' => $c['id']]);
            }
            render('contact_form', ['mode' => 'create', 'c' => $c, 'res' => $r]);
            return;
        }
        render('contact_form', ['mode' => 'create', 'c' => ['cc' => 'IT', 'consent' => false,
            'registrant' => in('registrant') ? ['nationality' => 'IT', 'entityType' => 1, 'regCode' => ''] : null], 'res' => null]);
    },

    'contacts/edit' => function () use ($contactFromPost, $contactInfo) {
        require_role('operator');
        if (is_post()) {
            $c = $contactFromPost();
            $cmd = epp()->commands();
            $r = epp()->exec('contact:update', $c['id'], fn() => $cmd->contactUpdate($c));
            if (flash_response($r, t('ok.contact_updated', ['id' => $c['id']]))) {
                redirect('contacts/info', ['id' => $c['id']]);
            }
            render('contact_form', ['mode' => 'edit', 'c' => $c, 'res' => $r]);
            return;
        }
        [$r, $i] = $contactInfo(strtoupper(in('id')));
        if (!$i) {
            throw \NicEpp\Epp\EppException::fromResponse(t('con.unreadable'), $r);
        }
        render('contact_form', ['mode' => 'edit', 'c' => $i, 'res' => null]);
    },

    'contacts/delete' => function () {
        require_role('operator');
        if (!is_post()) {
            redirect('contacts');
        }
        $id = strtoupper(in('id'));
        $cmd = epp()->commands();
        $r = epp()->exec('contact:delete', $id, fn() => $cmd->contactDelete($id));
        if (flash_response($r, t('ok.contact_deleted', ['id' => $id]))) {
            repo()->deleteContact($id);
            redirect('contacts');
        }
        redirect('contacts/info', ['id' => $id]);
    },

    // ============================================================ poll

    'poll' => function () {
        require_role('readonly');
        render('poll', ['rows' => repo()->polls(), 'state' => epp_state()]);
    },

    'poll/req' => function () {
        require_role('operator');
        if (!is_post()) {
            redirect('poll');
        }
        $r = epp()->pollReq();
        $q = $r->msgQ();
        if ($r->code() === 1300) {
            flash('ok', t('ok.queue_empty'));
        } elseif ($q) {
            repo()->savePoll($q['id'], $q['qDate'], $q['msg'], $r->raw());
            if (in('ack') === '1') {
                $a = epp()->pollAck($q['id']);
                if ($a->ok()) {
                    repo()->ackPoll($q['id'], current_user()['username']);
                }
            }
            flash('ok', t('ok.poll_message', ['id' => $q['id'], 'msg' => $q['msg'], 'count' => $q['count']]));
        } else {
            flash('err', $r->errorText());
        }
        redirect('poll');
    },

    'poll/ack' => function () {
        require_role('operator');
        if (!is_post()) {
            redirect('poll');
        }
        $id = in('msg_id');
        $r = epp()->pollAck($id);
        if (flash_response($r, t('ok.poll_acked', ['id' => $id]))) {
            repo()->ackPoll($id, current_user()['username']);
        }
        redirect('poll');
    },

    'poll/view' => function () {
        require_role('readonly');
        $p = repo()->poll((int)in('id'));
        if (!$p) {
            redirect('poll');
        }
        render('xml', ['title' => t('title.poll_message', ['id' => $p['msg_id']]), 'res' => new Response($p['raw_xml'])]);
    },

    // ============================================================ log / console / utenti

    'import' => function () {
        require_role('operator');
        $file = storage('import-job.json');
        if (is_post()) {
            if (in('cancel') === '1') {
                @unlink($file);
                flash('ok', t('ok.import_cancelled'));
                redirect('import');
            }
            $text = in('names');
            if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
                $text .= "\n" . file_get_contents($_FILES['file']['tmp_name']);
            }
            $names = \NicEpp\App\Importer::parseNames($text);
            if (!$names) {
                throw new InvalidArgumentException(t('err.import_none'));
            }
            importer()->start($names, in('contacts') === '1', in('skip_recent') === '1', current_user()['username']);
            redirect('import');
        }
        $job = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
        render('import', ['job' => $job]);
    },

    'import/step' => function () {
        require_role('operator');
        @set_time_limit(90);
        session_write_close(); // non bloccare le altre pagine dell'utente
        $j = importer()->step(20);
        header('Content-Type: application/json');
        echo json_encode([
            'pos' => $j['pos'] ?? 0, 'total' => count($j['names'] ?? []), 'ok' => $j['ok'] ?? 0,
            'skipped' => $j['skipped'] ?? 0, 'foreign' => $j['foreign'] ?? 0, 'contacts_ok' => $j['contacts_ok'] ?? 0,
            'errors' => count($j['errors'] ?? []), 'done' => !empty($j['finished_at']), 'last_error' => $j['last_error'] ?? null,
        ]);
    },

    'log' => function () {
        require_role('readonly');
        $page = max(1, (int)in('page', 1));
        $f = ['q' => in('q'), 'command' => in('command'), 'errors' => in('errors')];
        render('log', ['rows' => repo()->logs($f, 100, ($page - 1) * 100), 'f' => $f, 'page' => $page]);
    },

    'log/view' => function () {
        require_role('readonly');
        $l = repo()->log((int)in('id'));
        if (!$l) {
            redirect('log');
        }
        render('log_view', ['l' => $l]);
    },

    'console' => function () {
        require_role('admin');
        $res = null;
        $xml = (string)($_POST['xml'] ?? '');
        if (is_post() && trim($xml) !== '') {
            $res = epp()->execRaw($xml);
        }
        if ($xml === '') {
            $xml = epp()->commands()->domainCheck(['esempio.it']);
        }
        render('console', ['xml' => $xml, 'res' => $res]);
    },

    'users' => function () {
        require_role('admin');
        if (is_post()) {
            if (in('reset_2fa') !== '') {
                repo()->resetTotp((int)in('reset_2fa'));
                security()->revokeAllDevices((int)in('reset_2fa'));
                flash('ok', t('ok.2fa_reset'));
                redirect('users');
            }
            if (!isset(ROLE_LEVEL[in('role')])) {
                throw new InvalidArgumentException(t('err.bad_role'));
            }
            if (in('id') !== '') {
                if (($_POST['password'] ?? '') !== '' && strlen((string)$_POST['password']) < min_password()) {
                    throw new InvalidArgumentException(t('err.password_min', ['n' => min_password()]));
                }
                repo()->updateUser((int)in('id'), in('role'), in('active') === '1', $_POST['password'] ?: null);
                if (($_POST['password'] ?? '') !== '' || in('active') !== '1') {
                    security()->revokeAllDevices((int)in('id'));
                }
                flash('ok', t('ok.user_updated'));
            } else {
                if (strlen((string)$_POST['password']) < min_password()) {
                    throw new InvalidArgumentException(t('err.password_min', ['n' => min_password()]));
                }
                repo()->createUser(in('username'), (string)$_POST['password'], in('role'), isset(languages()[in('lang')]) ? in('lang') : 'en');
                flash('ok', t('ok.user_created'));
            }
            redirect('users');
        }
        render('users', ['rows' => repo()->users()]);
    },
];
