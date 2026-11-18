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
    foreach (['id' => 'ID contatto', 'name' => 'Nome', 'city' => 'Città', 'cc' => 'Nazione', 'email' => 'Email'] as $k => $l) {
        if ($c[$k] === '') {
            throw new InvalidArgumentException("Campo obbligatorio mancante: $l");
        }
    }
    if ($c['voice'] !== '' && !preg_match('/^\+\d{1,3}\.\d{1,14}$/', $c['voice'])) {
        throw new InvalidArgumentException('Telefono non valido: usa il formato +39.0501234567');
    }
    if ($c['fax'] !== '' && !preg_match('/^\+\d{1,3}\.\d{1,14}$/', $c['fax'])) {
        throw new InvalidArgumentException('Fax non valido: usa il formato +39.0501234567');
    }
    return $c;
};

return [

    // ============================================================ autenticazione

    'login' => function () {
        if (is_post()) {
            $u = repo()->userByName(in('username'));
            if ($u && $u['active'] && password_verify((string)($_POST['password'] ?? ''), $u['pass_hash'])) {
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$u['id'];
                repo()->touchLogin((int)$u['id']);
                redirect('dashboard');
            }
            usleep(500000);
            flash('err', 'Credenziali non valide');
            redirect('login');
        }
        render('login', ['bare' => true]);
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
            'expiring' => array_slice(array_filter(repo()->domains('', 'ex_date', 200),
                fn($d) => $d['ex_date'] && strtotime($d['ex_date']) < strtotime('+45 days')), 0, 20),
            'recent' => repo()->logs([], 15),
        ]);
    },

    'session/hello' => function () {
        require_role('readonly');
        $r = epp()->hello();
        render('xml', ['title' => 'Greeting del server', 'res' => $r]);
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
        $r ? flash_response($r, 'Logout EPP eseguito') : flash('ok', 'Nessuna sessione attiva');
        redirect('dashboard');
    },

    'session/password' => function () {
        require_role('admin');
        if (is_post()) {
            $new = (string)($_POST['new_pw'] ?? '');
            if ($new !== (string)($_POST['new_pw2'] ?? '') || strlen($new) < 8 || strlen($new) > 16) {
                throw new InvalidArgumentException('Le password non coincidono o la lunghezza non è valida (8–16)');
            }
            $r = epp()->login($new);
            settings()->set('epp.password', $new, current_user()['username']);
            @unlink(storage('epp-password'));
            flash_response($r, 'Password EPP cambiata presso il Registro e salvata in Configurazione');
            redirect('dashboard');
        }
        render('password');
    },

    // ============================================================ configurazione EPP (admin)

    'settings' => function () {
        require_role('admin');
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
                        throw new InvalidArgumentException("Valore non valido per «{$label}»"
                            . ($type === 'file' ? ': il file non esiste o non è leggibile da PHP' : ''));
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
            flash('ok', 'Configurazione salvata' . ($changed ? ' — sessione EPP chiusa, il prossimo comando farà il login con i nuovi parametri' : ''));

            if (in('test') === '1') {
                try {
                    $c = epp(true);
                    $c->hello();
                    $r = $c->login();
                    flash('ok', 'Test riuscito: connessione e login OK (' . $r->code() . ' ' . $r->msg() . ')');
                } catch (\Throwable $e) {
                    flash('err', 'Test fallito: ' . ($e instanceof \NicEpp\Epp\EppException && $e->response ? $e->response->errorText() : $e->getMessage()));
                }
            }
            redirect('settings');
        }
        render('settings', ['e' => epp_cfg(), 'meta' => settings()->meta()]);
    },

    // ============================================================ domini

    'domains' => function () {
        require_role('readonly');
        render('domains', ['q' => in('q'), 'rows' => repo()->domains(in('q'), in('order', 'name'))]);
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
            $name = normalize_domain(in('name'));
            $ns = parse_ns_lines(in('ns'));
            $techs = split_list(strtoupper(in('techs')));
            $ds = dnssec_enabled() ? parse_ds_lines(in('ds')) : [];
            $auth = in('authinfo') ?: gen_authinfo();
            if ($name === '' || in('registrant') === '' || in('admin') === '' || !$techs) {
                throw new InvalidArgumentException('Nome, registrante, admin e almeno un tech sono obbligatori');
            }
            if (count($ns) < 2 || count($ns) > 6) {
                throw new InvalidArgumentException('Servono da 2 a 6 nameserver');
            }
            if (count($techs) > 6) {
                throw new InvalidArgumentException('Massimo 6 contatti tecnici');
            }
            $c = epp()->commands();
            $r = epp()->exec('domain:create', $name, fn() => $c->domainCreate(
                $name, $ns, strtoupper(in('registrant')), strtoupper(in('admin')), $techs, $auth, $ds
            ));
            if (flash_response($r, "Dominio $name registrato")) {
                $cd = $r->createData();
                repo()->saveDomain(['name' => $name, 'registrant' => strtoupper(in('registrant')),
                    'status' => [], 'clID' => epp_cfg()['clid'], 'crDate' => $cd['crDate'], 'exDate' => $cd['exDate']]);
                redirect('domains/info', ['name' => $name]);
            }
            render('domain_create', ['f' => $_POST, 'res' => $r]);
            return;
        }
        render('domain_create', ['f' => ['name' => in('name'), 'authinfo' => gen_authinfo()], 'res' => null]);
    },

    'domains/update' => function () use ($domainInfo) {
        require_role('operator');
        if (!is_post()) {
            redirect('domains');
        }
        $name = normalize_domain(in('name'));
        [$cur, $d] = $domainInfo($name);
        if (!$d) {
            throw \NicEpp\Epp\EppException::fromResponse('Impossibile leggere lo stato attuale', $cur);
        }
        $c = epp()->commands();
        $act = in('action');
        $add = $rem = $chg = $sec = [];

        switch ($act) {
            case 'ns':
                $new = parse_ns_lines(in('ns'));
                if (count($new) < 2 || count($new) > 6) {
                    throw new InvalidArgumentException('Servono da 2 a 6 nameserver');
                }
                $key = fn($n) => $n['host'] . '|' . implode(',', array_map('strtolower', $n['ips']));
                $curK = array_combine(array_map($key, $d['ns']), $d['ns']);
                $newK = array_combine(array_map($key, $new), $new);
                $add['ns'] = array_values(array_diff_key($newK, $curK));
                $rem['ns'] = array_values(array_diff_key($curK, $newK));
                $msg = 'Nameserver aggiornati';
                break;

            case 'contacts':
                $curT = array_column(array_filter($d['contacts'], fn($x) => $x['type'] === 'tech'), 'id');
                $curA = array_column(array_filter($d['contacts'], fn($x) => $x['type'] === 'admin'), 'id');
                $newT = split_list(strtoupper(in('techs')));
                $newA = split_list(strtoupper(in('admin')));
                if (count($newA) !== 1 || !$newT || count($newT) > 6) {
                    throw new InvalidArgumentException('Serve esattamente un admin e da 1 a 6 tech');
                }
                foreach (array_diff($newA, $curA) as $i) { $add['contacts'][] = ['type' => 'admin', 'id' => $i]; }
                foreach (array_diff($newT, $curT) as $i) { $add['contacts'][] = ['type' => 'tech', 'id' => $i]; }
                foreach (array_diff($curA, $newA) as $i) { $rem['contacts'][] = ['type' => 'admin', 'id' => $i]; }
                foreach (array_diff($curT, $newT) as $i) { $rem['contacts'][] = ['type' => 'tech', 'id' => $i]; }
                $msg = 'Contatti aggiornati';
                break;

            case 'status':
                $want = array_values(array_intersect((array)($_POST['status'] ?? []), Commands::DOMAIN_CLIENT_STATUSES));
                $have = array_values(array_intersect($d['status'], Commands::DOMAIN_CLIENT_STATUSES));
                $add['status'] = array_values(array_diff($want, $have));
                $rem['status'] = array_values(array_diff($have, $want));
                $msg = 'Stati aggiornati';
                break;

            case 'authinfo':
                $chg['authInfo'] = in('authinfo') ?: gen_authinfo();
                $msg = 'AuthInfo cambiato';
                break;

            case 'registrant':
                $chg['registrant'] = strtoupper(in('registrant'));
                $chg['authInfo'] = in('authinfo') ?: gen_authinfo(); // obbligatorio e diverso dal precedente
                if ($chg['registrant'] === '') {
                    throw new InvalidArgumentException('Indica il nuovo registrante');
                }
                if (in('admin') !== '') {
                    $old = array_column(array_filter($d['contacts'], fn($x) => $x['type'] === 'admin'), 'id');
                    $newA = strtoupper(in('admin'));
                    if (!in_array($newA, $old, true)) {
                        $add['contacts'][] = ['type' => 'admin', 'id' => $newA];
                        foreach ($old as $o) { $rem['contacts'][] = ['type' => 'admin', 'id' => $o]; }
                    }
                }
                $msg = 'Modifica del registrante eseguita';
                break;

            case 'dnssec':
                if (!dnssec_enabled()) {
                    throw new InvalidArgumentException('DNSSEC non abilitato: attivalo in Configurazione dopo l\'accreditamento');
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
                throw new InvalidArgumentException('Azione non valida');
        }

        $nothing = empty(array_filter($add)) && empty(array_filter($rem)) && empty($chg) && empty(array_filter($sec));
        if ($nothing) {
            flash('ok', 'Nessuna modifica da inviare');
            redirect('domains/info', ['name' => $name]);
        }
        $r = epp()->exec('domain:update', $name, fn() => $c->domainUpdate($name, $add, $rem, $chg, $sec));
        if (flash_response($r, $msg) && isset($chg['authInfo'])) {
            flash('ok', 'Nuovo AuthInfo: ' . $chg['authInfo']);
        }
        redirect('domains/info', ['name' => $name]);
    },

    'domains/delete' => function () {
        require_role('operator');
        $name = normalize_domain(in('name'));
        if (!is_post() || in('confirm') !== $name) {
            throw new InvalidArgumentException('Per cancellare scrivi esattamente il nome del dominio');
        }
        $c = epp()->commands();
        $r = epp()->exec('domain:delete', $name, fn() => $c->domainDelete($name));
        flash_response($r, "Cancellazione di $name richiesta (il dominio entra in pendingDelete/redemptionPeriod)");
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
        flash_response($r, "Restore di $name eseguito");
        redirect('domains/info', ['name' => $name]);
    },

    'domains/transfer' => function () {
        require_role('readonly');
        $res = null;
        $data = null;
        if (is_post()) {
            $op = in('op');
            if (!in_array($op, ['request', 'query', 'approve', 'reject', 'cancel'], true)) {
                throw new InvalidArgumentException('Operazione non valida');
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
                throw new InvalidArgumentException('Per la richiesta di trasferimento serve l\'AuthInfo');
            }
            if ($trade && !$newReg) {
                throw new InvalidArgumentException('Per il Transfer-Trade indica il nuovo registrante');
            }
            $c = epp()->commands();
            $res = epp()->exec("domain:transfer:$op", $name, fn() => $c->domainTransfer($op, $name, $auth, $newReg, $newAuth));
            if (flash_response($res, "Transfer $op su $name")) {
                $data = $res->transferData();
                if ($newAuth) {
                    flash('ok', 'Nuovo AuthInfo (trade): ' . $newAuth);
                }
            }
        }
        render('domain_transfer', ['f' => $_POST + ['name' => in('name'), 'op' => in('op', 'request')], 'res' => $res, 'data' => $data]);
    },

    // ============================================================ contatti

    'contacts' => function () {
        require_role('readonly');
        render('contacts', ['q' => in('q'), 'rows' => repo()->contacts(in('q'))]);
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
            $c = $contactFromPost();
            $c['authInfo'] = gen_authinfo();
            $cmd = epp()->commands();
            $r = epp()->exec('contact:create', $c['id'], fn() => $cmd->contactCreate($c));
            if (flash_response($r, "Contatto {$c['id']} creato")) {
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
            if (flash_response($r, "Contatto {$c['id']} aggiornato")) {
                redirect('contacts/info', ['id' => $c['id']]);
            }
            render('contact_form', ['mode' => 'edit', 'c' => $c, 'res' => $r]);
            return;
        }
        [$r, $i] = $contactInfo(strtoupper(in('id')));
        if (!$i) {
            throw \NicEpp\Epp\EppException::fromResponse('Contatto non leggibile', $r);
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
        if (flash_response($r, "Contatto $id cancellato")) {
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
            flash('ok', 'Nessun messaggio in coda');
        } elseif ($q) {
            repo()->savePoll($q['id'], $q['qDate'], $q['msg'], $r->raw());
            if (in('ack') === '1') {
                $a = epp()->pollAck($q['id']);
                if ($a->ok()) {
                    repo()->ackPoll($q['id'], current_user()['username']);
                }
            }
            flash('ok', "Messaggio {$q['id']}: {$q['msg']} (in coda: {$q['count']})");
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
        if (flash_response($r, "Messaggio $id confermato")) {
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
        render('xml', ['title' => 'Messaggio poll ' . $p['msg_id'], 'res' => new Response($p['raw_xml'])]);
    },

    // ============================================================ log / console / utenti

    'import' => function () {
        require_role('operator');
        $file = storage('import-job.json');
        if (is_post()) {
            if (in('cancel') === '1') {
                @unlink($file);
                flash('ok', 'Import annullato');
                redirect('import');
            }
            $text = in('names');
            if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
                $text .= "\n" . file_get_contents($_FILES['file']['tmp_name']);
            }
            $names = \NicEpp\App\Importer::parseNames($text);
            if (!$names) {
                throw new InvalidArgumentException('Nessun nome .it trovato nel testo o nel file');
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
            if (!isset(ROLE_LEVEL[in('role')])) {
                throw new InvalidArgumentException('Ruolo non valido');
            }
            if (in('id') !== '') {
                repo()->updateUser((int)in('id'), in('role'), in('active') === '1', $_POST['password'] ?: null);
                flash('ok', 'Utente aggiornato');
            } else {
                if (strlen((string)$_POST['password']) < 10) {
                    throw new InvalidArgumentException('Password di almeno 10 caratteri');
                }
                repo()->createUser(in('username'), (string)$_POST['password'], in('role'));
                flash('ok', 'Utente creato');
            }
            redirect('users');
        }
        render('users', ['rows' => repo()->users()]);
    },
];
