<?php
declare(strict_types=1);

namespace NicEpp\Epp;

/**
 * Client EPP con sessione condivisa tra tutte le richieste web.
 *
 * - Una sola sessione EPP (cookie jar su disco), serializzata con flock:
 *   il Registro limita sessioni concorrenti e frequenza dei login.
 * - Login automatico se la sessione non c'è o è scaduta; un retry se il server
 *   risponde "non autenticato" o restituisce il greeting.
 * - Ogni scambio passa dal logger (audit log su DB).
 */
final class Client
{
    /** @var callable|null fn(string $cmd, string $obj, string $req, ?Response $res, int $ms, ?string $err) */
    private $logger;
    private $lockFh = null;
    private int $lockDepth = 0;

    public function __construct(
        private Transport $t,
        private Commands $c,
        private array $cfg,      // clid, password, state_file, lock_file, idle_timeout
        ?callable $logger = null
    ) {
        $this->logger = $logger;
    }

    public function commands(): Commands
    {
        return $this->c;
    }

    public function setLogger(callable $l): void
    {
        $this->logger = $l;
    }

    // ------------------------------------------------------------------ stato sessione

    public function state(): array
    {
        $f = $this->cfg['state_file'];
        $s = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
        return is_array($s) ? $s + ['logged_in' => false, 'last' => 0, 'login_at' => 0, 'msgq' => null]
                            : ['logged_in' => false, 'last' => 0, 'login_at' => 0, 'msgq' => null];
    }

    private function saveState(array $s): void
    {
        if (@file_put_contents($this->cfg['state_file'], json_encode($s), LOCK_EX) === false) {
            Transport::assertWritable($this->cfg['state_file']);
            throw new EppException(\t('err.write', ['path' => $this->cfg['state_file']]));
        }
    }

    private function touch(?Response $r = null): void
    {
        $s = $this->state();
        $s['last'] = time();
        if ($r && $r->msgQCount() !== null) {
            $s['msgq'] = $r->msgQCount();
        } elseif ($r && $r->code() === 1300) { // poll: nessun messaggio
            $s['msgq'] = 0;
        }
        $this->saveState($s);
    }

    private function markLoggedOut(): void
    {
        $s = $this->state();
        $s['logged_in'] = false;
        $this->saveState($s);
        $this->t->resetCookies();
    }

    private function lock(): void
    {
        if ($this->lockDepth++ > 0) {
            return;
        }
        Transport::assertWritable($this->cfg['lock_file']);
        $this->lockFh = fopen($this->cfg['lock_file'], 'c');
        if (!$this->lockFh || !flock($this->lockFh, LOCK_EX)) {
            throw new EppException(\t('err.lock'));
        }
    }

    private function unlock(): void
    {
        if (--$this->lockDepth > 0) {
            return;
        }
        if ($this->lockFh) {
            flock($this->lockFh, LOCK_UN);
            fclose($this->lockFh);
            $this->lockFh = null;
        }
    }

    // ------------------------------------------------------------------ invio

    private function raw(string $cmd, string $obj, string $xml): Response
    {
        $t0 = microtime(true);
        $res = null;
        $err = null;
        try {
            [$http, $body] = $this->t->post($xml);
            if ($http !== 200 && trim($body) === '') {
                throw new EppException(\t('err.http', ['code' => $http]), $http);
            }
            $res = new Response($body);
            return $res;
        } catch (\Throwable $e) {
            $err = $e->getMessage();
            throw $e instanceof EppException ? $e : new EppException($err, 0, $e);
        } finally {
            if ($this->logger) {
                ($this->logger)($cmd, $obj, $xml, $res, (int)round((microtime(true) - $t0) * 1000), $err);
            }
        }
    }

    public function hello(): Response
    {
        $this->lock();
        try {
            $r = $this->raw('hello', '', $this->c->hello());
            $this->touch();
            return $r;
        } finally {
            $this->unlock();
        }
    }

    public function login(?string $newPw = null): Response
    {
        $this->lock();
        try {
            $this->t->resetCookies();
            $r = $this->raw('login', $this->cfg['clid'], $this->c->login($this->cfg['clid'], $this->cfg['password'], $newPw));
            $s = $this->state();
            $s['logged_in'] = $r->ok();
            $s['login_at'] = $r->ok() ? time() : $s['login_at'];
            $s['last'] = time();
            $this->saveState($s);
            if (!$r->ok()) {
                throw EppException::fromResponse(\t('err.login_failed'), $r);
            }
            return $r;
        } finally {
            $this->unlock();
        }
    }

    public function logout(): ?Response
    {
        $this->lock();
        try {
            if (!$this->state()['logged_in']) {
                return null;
            }
            try {
                return $this->raw('logout', '', $this->c->logout());
            } finally {
                $this->markLoggedOut();
            }
        } finally {
            $this->unlock();
        }
    }

    private function sessionLost(Response $r): bool
    {
        // greeting al posto della response, oppure 2002 (command use error) / 2200 (authentication error)
        return $r->isGreeting() || in_array($r->code(), [2002, 2200], true);
    }

    /**
     * Esegue un comando con sessione autenticata.
     * $build è una closure che costruisce l'XML (rigenerata al retry per avere un clTRID nuovo).
     */
    public function exec(string $cmd, string $obj, callable $build): Response
    {
        $this->lock();
        try {
            $st = $this->state();
            $idle = (int)($this->cfg['idle_timeout'] ?? 600);
            if (!$st['logged_in'] || (time() - (int)$st['last']) > $idle) {
                $this->login();
            }
            $r = $this->raw($cmd, $obj, $build());
            if ($this->sessionLost($r)) {
                $this->markLoggedOut();
                $this->login();
                $r = $this->raw($cmd, $obj, $build());
                if ($this->sessionLost($r)) {
                    $this->markLoggedOut();
                    throw EppException::fromResponse(
                        \t($this->t->hasSessionCookie() ? 'err.session_cookie_rejected' : 'err.session_cookie_missing'),
                        $r
                    );
                }
            }
            $this->touch($r);
            return $r;
        } finally {
            $this->unlock();
        }
    }

    /** Invio XML grezzo (console admin). Il clTRID è quello scritto dall'operatore. */
    public function execRaw(string $xml): Response
    {
        return $this->exec('raw', '', fn() => $xml);
    }

    // ------------------------------------------------------------------ scorciatoie

    public function domainCheck(array $names): Response
    {
        return $this->exec('domain:check', implode(',', $names), fn() => $this->c->domainCheck($names));
    }

    public function domainInfo(string $name, ?string $auth = null): Response
    {
        return $this->exec('domain:info', $name, fn() => $this->c->domainInfo($name, $auth));
    }

    public function contactCheck(array $ids): Response
    {
        return $this->exec('contact:check', implode(',', $ids), fn() => $this->c->contactCheck($ids));
    }

    public function contactInfo(string $id): Response
    {
        return $this->exec('contact:info', $id, fn() => $this->c->contactInfo($id));
    }

    public function pollReq(): Response
    {
        return $this->exec('poll:req', '', fn() => $this->c->pollReq());
    }

    public function pollAck(string $id): Response
    {
        return $this->exec('poll:ack', $id, fn() => $this->c->pollAck($id));
    }
}
