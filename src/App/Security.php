<?php
declare(strict_types=1);

namespace NicEpp\App;

use PDO;

/**
 * Impostazioni di sicurezza (sezione Sicurezza di Configurazione):
 * IP abilitati, scadenza sessioni, lunghezza password, limiti ai tentativi falliti,
 * computer attendibili dopo il secondo fattore.
 */
final class Security
{
    /** chiave => [chiave di traduzione, default, min, max]; 0 = disattivato dove ha senso */
    public const FIELDS = [
        'idle_timeout'       => ['sec.idle_timeout', 1800, 0, 604800],
        'max_session'        => ['sec.max_session', 43200, 0, 2592000],
        'min_password'       => ['sec.min_password', 10, 8, 128],
        'fail_window'        => ['sec.fail_window', 900, 60, 86400],
        'fail_per_ip'        => ['sec.fail_per_ip', 20, 1, 10000],
        'fail_per_user_ip'   => ['sec.fail_per_user_ip', 5, 1, 1000],
        'remember_days'      => ['sec.remember_days', 0, 0, 365],
    ];

    public const COOKIE = 'NICEPP_TD';

    public function __construct(private PDO $db, private Settings $settings) {}

    public function get(string $k): int
    {
        $v = $this->settings->get('sec.' . $k);
        return $v === null ? (int)self::FIELDS[$k][1] : (int)$v;
    }

    // ---------------------------------------------------------------- IP abilitati

    /** @return string[] voci IP o CIDR */
    public function allowedIps(): array
    {
        return self::parseIpList((string)$this->settings->get('sec.allowed_ips', ''));
    }

    public static function parseIpList(string $text): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', $text) as $e) {
            $e = trim($e);
            if ($e === '' || str_starts_with($e, '#')) {
                continue;
            }
            if (!self::validEntry($e)) {
                throw new \InvalidArgumentException(\t('err.bad_ip', ['ip' => $e]));
            }
            $out[] = $e;
        }
        return array_values(array_unique($out));
    }

    private static function validEntry(string $e): bool
    {
        [$ip, $bits] = explode('/', $e, 2) + [1 => null];
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }
        return $bits === null || (ctype_digit($bits) && (int)$bits <= strlen($bin) * 8);
    }

    /** Lista vuota = tutti abilitati. Supporta IPv4, IPv6 e CIDR (es. 192.0.2.0/24, 2001:db8::/32). */
    public static function ipMatches(string $ip, array $list): bool
    {
        if (!$list) {
            return true;
        }
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }
        foreach ($list as $e) {
            [$net, $bits] = explode('/', $e, 2) + [1 => null];
            $nb = @inet_pton($net);
            if ($nb === false || strlen($nb) !== strlen($bin)) {
                continue; // IPv4 contro IPv6
            }
            $bits = $bits === null ? strlen($nb) * 8 : (int)$bits;
            $bytes = intdiv($bits, 8);
            $rest = $bits % 8;
            if (substr($bin, 0, $bytes) !== substr($nb, 0, $bytes)) {
                continue;
            }
            if ($rest === 0) {
                return true;
            }
            $mask = (0xff << (8 - $rest)) & 0xff;
            if ((ord($bin[$bytes]) & $mask) === (ord($nb[$bytes]) & $mask)) {
                return true;
            }
        }
        return false;
    }

    // ---------------------------------------------------------------- tentativi falliti

    public function recordFailure(string $ip, string $username, string $kind): void
    {
        $this->db->prepare('INSERT INTO login_attempts (ip, username, kind, created_at) VALUES (?,?,?,?)')
            ->execute([$ip, mb_substr(strtolower($username), 0, 64), $kind, date('Y-m-d H:i:s')]);
    }

    /**
     * Restituisce i secondi da attendere se IP o utente+IP hanno superato i limiti, altrimenti 0.
     */
    public function blockedFor(string $ip, string $username): int
    {
        $win = $this->get('fail_window');
        $since = date('Y-m-d H:i:s', time() - $win);
        $st = $this->db->prepare('SELECT COUNT(*) AS n, MIN(created_at) AS first FROM login_attempts WHERE ip = ? AND created_at >= ?');
        $st->execute([$ip, $since]);
        $byIp = $st->fetch();
        $st = $this->db->prepare('SELECT COUNT(*) AS n, MIN(created_at) AS first FROM login_attempts WHERE ip = ? AND username = ? AND created_at >= ?');
        $st->execute([$ip, strtolower($username), $since]);
        $byUser = $st->fetch();
        $wait = 0;
        foreach ([[$byIp, $this->get('fail_per_ip')], [$byUser, $this->get('fail_per_user_ip')]] as [$r, $max]) {
            if ((int)$r['n'] >= $max && $r['first']) {
                $wait = max($wait, strtotime($r['first']) + $win - time());
            }
        }
        return max(0, $wait);
    }

    public function clearFailures(string $ip, string $username): void
    {
        $this->db->prepare('DELETE FROM login_attempts WHERE ip = ? AND username = ?')->execute([$ip, strtolower($username)]);
    }

    public function purgeFailures(): void
    {
        $keep = max($this->get('fail_window'), 86400);
        $this->db->prepare('DELETE FROM login_attempts WHERE created_at < ?')->execute([date('Y-m-d H:i:s', time() - $keep)]);
    }

    // ---------------------------------------------------------------- computer attendibili

    /** Crea il token "ricorda questo computer" e restituisce il valore del cookie. */
    public function trustDevice(int $userId, string $ip, string $ua): array
    {
        $days = $this->get('remember_days');
        $token = bin2hex(random_bytes(32));
        $exp = time() + $days * 86400;
        $this->db->prepare('INSERT INTO trusted_devices (user_id, token_hash, created_at, expires_at, last_used, ip, user_agent) VALUES (?,?,?,?,?,?,?)')
            ->execute([$userId, hash('sha256', $token), date('Y-m-d H:i:s'), date('Y-m-d H:i:s', $exp), date('Y-m-d H:i:s'),
                $ip, mb_substr($ua, 0, 255)]);
        return [$this->db->lastInsertId() . ':' . $token, $exp];
    }

    /** Verifica il cookie per l'utente; aggiorna last_used. */
    public function deviceTrusted(int $userId, string $cookie): bool
    {
        if ($this->get('remember_days') <= 0 || !preg_match('/^(\d+):([a-f0-9]{64})$/', $cookie, $m)) {
            return false;
        }
        $st = $this->db->prepare('SELECT token_hash, expires_at FROM trusted_devices WHERE id = ? AND user_id = ?');
        $st->execute([(int)$m[1], $userId]);
        $r = $st->fetch();
        if (!$r || strtotime($r['expires_at']) < time() || !hash_equals($r['token_hash'], hash('sha256', $m[2]))) {
            return false;
        }
        $this->db->prepare('UPDATE trusted_devices SET last_used = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), (int)$m[1]]);
        return true;
    }

    public function devices(int $userId): array
    {
        $st = $this->db->prepare('SELECT id, created_at, expires_at, last_used, ip, user_agent FROM trusted_devices WHERE user_id = ? AND expires_at >= ? ORDER BY last_used DESC');
        $st->execute([$userId, date('Y-m-d H:i:s')]);
        return $st->fetchAll();
    }

    public function revokeDevice(int $userId, int $id): void
    {
        $this->db->prepare('DELETE FROM trusted_devices WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
    }

    public function revokeAllDevices(int $userId): void
    {
        $this->db->prepare('DELETE FROM trusted_devices WHERE user_id = ?')->execute([$userId]);
    }
}
