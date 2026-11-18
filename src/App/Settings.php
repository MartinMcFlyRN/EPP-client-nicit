<?php
declare(strict_types=1);

namespace NicEpp\App;

use PDO;

/**
 * Impostazioni modificabili da web (tabella settings).
 * I valori segreti (password EPP) sono cifrati con una chiave locale:
 * app.secret in config.php se presente, altrimenti storage/app.key generata al primo uso.
 */
final class Settings
{
    public const SECRET_KEYS = ['epp.password'];

    private ?array $cache = null;

    public function __construct(private PDO $db, private string $keyFile, private ?string $configSecret = null) {}

    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = [];
            try {
                foreach ($this->db->query('SELECT name, value FROM settings') as $r) {
                    $this->cache[$r['name']] = $r['value'];
                }
            } catch (\PDOException) {
                // tabella non ancora creata: si usa solo config.php
            }
        }
        return $this->cache;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->all()) && $this->all()[$name] !== '';
    }

    public function get(string $name, ?string $default = null): ?string
    {
        if (!$this->has($name)) {
            return $default;
        }
        $v = $this->all()[$name];
        return in_array($name, self::SECRET_KEYS, true) ? $this->decrypt($v) : $v;
    }

    /** null o '' = rimuove l'impostazione (si torna al valore di config.php). */
    public function set(string $name, ?string $value, ?string $by = null): void
    {
        $this->db->prepare('DELETE FROM settings WHERE name = ?')->execute([$name]);
        if ($value !== null && $value !== '') {
            if (in_array($name, self::SECRET_KEYS, true)) {
                $value = $this->encrypt($value);
            }
            $this->db->prepare('INSERT INTO settings (name, value, updated_at, updated_by) VALUES (?,?,?,?)')
                ->execute([$name, $value, date('Y-m-d H:i:s'), $by]);
        }
        $this->cache = null;
    }

    public function meta(): array
    {
        try {
            $r = $this->db->query('SELECT MAX(updated_at) AS at FROM settings')->fetch();
            $who = $this->db->query('SELECT updated_by FROM settings ORDER BY updated_at DESC LIMIT 1')->fetchColumn();
            return ['at' => $r['at'] ?? null, 'by' => $who ?: null];
        } catch (\PDOException) {
            return ['at' => null, 'by' => null];
        }
    }

    // ---------------------------------------------------------------- cifratura

    private function key(): string
    {
        if ($this->configSecret) {
            return hash('sha256', $this->configSecret, true);
        }
        if (!is_file($this->keyFile)) {
            $old = umask(0077);
            file_put_contents($this->keyFile, base64_encode(random_bytes(32)), LOCK_EX);
            umask($old);
            @chmod($this->keyFile, 0600);
        }
        $k = base64_decode(trim((string)file_get_contents($this->keyFile)), true);
        if ($k === false || strlen($k) !== 32) {
            throw new \RuntimeException(\t('err.bad_key', ['path' => $this->keyFile]));
        }
        return $k;
    }

    public function encrypt(string $plain): string
    {
        $k = $this->key();
        if (function_exists('sodium_crypto_secretbox')) {
            $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return 's1:' . base64_encode($n . sodium_crypto_secretbox($plain, $n, $k));
        }
        $iv = random_bytes(12);
        $ct = openssl_encrypt($plain, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag);
        return 'o1:' . base64_encode($iv . $tag . $ct);
    }

    public function decrypt(string $stored): string
    {
        $k = $this->key();
        [$v, $b] = explode(':', $stored, 2) + [1 => ''];
        $raw = base64_decode($b, true);
        $plain = false;
        if ($raw !== false && $v === 's1' && function_exists('sodium_crypto_secretbox_open')) {
            $n = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $n, $k);
        } elseif ($raw !== false && $v === 'o1') {
            $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $k, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        }
        if ($plain === false) {
            throw new \RuntimeException(\t('err.decrypt'));
        }
        return $plain;
    }
}
