<?php
declare(strict_types=1);

namespace NicEpp\Epp;

/**
 * Trasporto EPP del Registro .it: HTTPS POST dell'XML, sessione mantenuta via cookie.
 * I cookie (Set-Cookie del server) sono gestiti qui e salvati in JSON nel file indicato:
 * se il file non è scrivibile viene sollevato un errore esplicito invece di perdere la sessione in silenzio.
 */
final class Transport
{
    public function __construct(
        private string $url,
        private string $cookieFile,
        private array $opts = []
    ) {
        self::assertWritable($this->cookieFile);
    }

    /** Verifica che il file (o la cartella, se il file non esiste) sia scrivibile dall'utente PHP. */
    public static function assertWritable(string $file): void
    {
        $target = is_file($file) ? $file : dirname($file);
        if (is_writable($target)) {
            return;
        }
        $who = function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? (string)posix_geteuid()) : get_current_user();
        $owner = @fileowner($target);
        $ownerName = ($owner !== false && function_exists('posix_getpwuid')) ? (posix_getpwuid($owner)['name'] ?? (string)$owner) : (string)$owner;
        throw new EppException(sprintf(
            '%s non è scrivibile dall\'utente PHP «%s» (proprietario: %s). Correggi con: chown -R %s %s',
            $target, $who, $ownerName, $who, dirname($file)
        ));
    }

    private function loadCookies(): array
    {
        $c = is_file($this->cookieFile) ? json_decode((string)file_get_contents($this->cookieFile), true) : null;
        return is_array($c) ? $c : []; // anche il vecchio formato Netscape di curl viene ignorato
    }

    private function saveCookies(array $c): void
    {
        $old = umask(0077);
        $ok = @file_put_contents($this->cookieFile, json_encode($c), LOCK_EX);
        umask($old);
        if ($ok === false) {
            self::assertWritable($this->cookieFile);
            throw new EppException('Impossibile salvare il cookie di sessione EPP in ' . $this->cookieFile);
        }
    }

    public function hasSessionCookie(): bool
    {
        return (bool)$this->loadCookies();
    }

    /** @return array{0:int,1:string} [httpCode, body] */
    public function post(string $xml): array
    {
        $ch = curl_init($this->url);
        $o = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: text/xml; charset=UTF-8', 'Accept: text/xml'],
            CURLOPT_CONNECTTIMEOUT => (int)($this->opts['connect_timeout'] ?? 10),
            CURLOPT_TIMEOUT        => (int)($this->opts['timeout'] ?? 60),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'NicEppWeb/1.0',
        ];
        // Il Registro accetta connessioni solo dagli IP dichiarati: permette di scegliere l'IP sorgente.
        if (!empty($this->opts['source_ip'])) {
            $o[CURLOPT_INTERFACE] = $this->opts['source_ip'];
        }
        if (!empty($this->opts['ca_file'])) {
            $o[CURLOPT_CAINFO] = $this->opts['ca_file'];
        }
        if (!empty($this->opts['client_cert'])) {
            $o[CURLOPT_SSLCERT] = $this->opts['client_cert'];
            if (!empty($this->opts['client_key'])) {
                $o[CURLOPT_SSLKEY] = $this->opts['client_key'];
            }
        }
        $jar = $this->loadCookies();
        if ($jar) {
            $o[CURLOPT_COOKIE] = implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($jar), $jar));
        }
        $set = [];
        $o[CURLOPT_HEADERFUNCTION] = function ($ch, string $line) use (&$set): int {
            if (stripos($line, 'Set-Cookie:') === 0) {
                $parts = array_map('trim', explode(';', trim(substr($line, 11))));
                [$name, $value] = explode('=', array_shift($parts), 2) + [1 => ''];
                $expired = $value === '';
                foreach ($parts as $p) {
                    [$an, $av] = explode('=', $p, 2) + [1 => ''];
                    $an = strtolower($an);
                    if (($an === 'max-age' && (int)$av <= 0) || ($an === 'expires' && ($t = strtotime($av)) && $t < time())) {
                        $expired = true;
                    }
                }
                $set[trim($name)] = $expired ? null : $value;
            }
            return strlen($line);
        };
        curl_setopt_array($ch, $o);
        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        unset($ch);
        if ($set) {
            foreach ($set as $k => $v) {
                if ($v === null) {
                    unset($jar[$k]);
                } else {
                    $jar[$k] = $v;
                }
            }
            $this->saveCookies($jar);
        }

        if ($body === false) {
            throw new EppException('Errore di connessione al server EPP: ' . $err);
        }
        return [$http, (string)$body];
    }

    public function resetCookies(): void
    {
        $this->saveCookies([]);
    }
}
