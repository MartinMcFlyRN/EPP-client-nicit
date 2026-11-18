<?php
declare(strict_types=1);

namespace NicEpp\App;

/** TOTP RFC 6238 (SHA1, 6 cifre, 30 s), compatibile con tutte le app di autenticazione. */
final class Totp
{
    private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function newSecret(): string
    {
        return self::b32encode(random_bytes(20));
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);
        return 'otpauth://totp/' . $label . '?' . http_build_query([
            'secret' => $secret, 'issuer' => $issuer, 'algorithm' => 'SHA1', 'digits' => 6, 'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function code(string $secret, int $step): string
    {
        $h = hash_hmac('sha1', pack('J', $step), self::b32decode($secret), true);
        $o = ord($h[19]) & 0x0f;
        $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
        return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verifica il codice con tolleranza di ±1 intervallo.
     * Restituisce lo step usato (da salvare per impedire il riuso), oppure null.
     */
    public static function verify(string $secret, string $code, int $lastStep = 0, ?int $now = null): ?int
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return null;
        }
        $cur = intdiv($now ?? time(), 30);
        foreach ([0, -1, 1] as $d) {
            $s = $cur + $d;
            if ($s > $lastStep && hash_equals(self::code($secret, $s), $code)) {
                return $s;
            }
        }
        return null;
    }

    /** 8 codici di recupero monouso, formato XXXXX-XXXXX. */
    public static function recoveryCodes(int $n = 8): array
    {
        $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $c = '';
            for ($k = 0; $k < 10; $k++) {
                $c .= $alpha[random_int(0, 31)];
            }
            $out[] = substr($c, 0, 5) . '-' . substr($c, 5);
        }
        return $out;
    }

    public static function normalizeRecovery(string $c): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $c));
    }

    private static function b32encode(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $ch) {
            $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::B32[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    private static function b32decode(string $b32): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($b32, '='))) as $ch) {
            $p = strpos(self::B32, $ch);
            if ($p !== false) {
                $bits .= str_pad(decbin($p), 5, '0', STR_PAD_LEFT);
            }
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
