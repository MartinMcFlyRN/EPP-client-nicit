<?php
declare(strict_types=1);

namespace NicEpp\Epp;

/**
 * Trasporto EPP del Registro .it: HTTPS POST dell'XML, sessione mantenuta via cookie.
 */
final class Transport
{
    public function __construct(
        private string $url,
        private string $cookieFile,
        private array $opts = []
    ) {
        if (!is_file($this->cookieFile)) {
            @touch($this->cookieFile);
            @chmod($this->cookieFile, 0600);
        }
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
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_COOKIEJAR      => $this->cookieFile,
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
        curl_setopt_array($ch, $o);
        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        unset($ch); // forza la scrittura del cookie jar

        if ($body === false) {
            throw new EppException('Errore di connessione al server EPP: ' . $err);
        }
        return [$http, (string)$body];
    }

    public function resetCookies(): void
    {
        @file_put_contents($this->cookieFile, '');
    }
}
