<?php
declare(strict_types=1);

namespace NicEpp\App;

use PDO;

final class Repo
{
    public function __construct(private PDO $db) {}

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private static function dt(?string $iso): ?string
    {
        if (!$iso) {
            return null;
        }
        $t = strtotime($iso);
        return $t ? date('Y-m-d H:i:s', $t) : null;
    }

    private function upsert(string $table, string $pk, array $row): void
    {
        $st = $this->db->prepare("SELECT 1 FROM $table WHERE $pk = ?");
        $st->execute([$row[$pk]]);
        if ($st->fetchColumn()) {
            $set = implode(',', array_map(fn($k) => "$k = :$k", array_filter(array_keys($row), fn($k) => $k !== $pk)));
            $this->db->prepare("UPDATE $table SET $set WHERE $pk = :$pk")->execute($row);
        } else {
            $cols = implode(',', array_keys($row));
            $ph = implode(',', array_map(fn($k) => ":$k", array_keys($row)));
            $this->db->prepare("INSERT INTO $table ($cols) VALUES ($ph)")->execute($row);
        }
    }

    // ---------------------------------------------------------------- utenti

    public function userByName(string $u): ?array
    {
        $st = $this->db->prepare('SELECT * FROM users WHERE username = ?');
        $st->execute([$u]);
        return $st->fetch() ?: null;
    }

    public function userById(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function users(): array
    {
        return $this->db->query('SELECT id, username, role, active, created_at, last_login, totp_enabled_at FROM users ORDER BY username')->fetchAll();
    }

    public function createUser(string $u, string $pw, string $role): void
    {
        $this->db->prepare('INSERT INTO users (username, pass_hash, role, active, created_at) VALUES (?,?,?,1,?)')
            ->execute([$u, password_hash($pw, PASSWORD_DEFAULT), $role, $this->now()]);
    }

    public function updateUser(int $id, string $role, bool $active, ?string $pw): void
    {
        $this->db->prepare('UPDATE users SET role = ?, active = ? WHERE id = ?')->execute([$role, $active ? 1 : 0, $id]);
        if ($pw) {
            $this->db->prepare('UPDATE users SET pass_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
        }
    }

    // ---------------------------------------------------------------- 2FA

    /** $secretEnc: segreto TOTP cifrato; $recoveryHashes: password_hash dei codici di recupero */
    public function enableTotp(int $id, string $secretEnc, array $recoveryHashes, int $step): void
    {
        $this->db->prepare('UPDATE users SET totp_secret = ?, totp_enabled_at = ?, totp_last_step = ?, recovery_codes = ? WHERE id = ?')
            ->execute([$secretEnc, $this->now(), $step, json_encode($recoveryHashes), $id]);
    }

    public function setTotpStep(int $id, int $step): void
    {
        $this->db->prepare('UPDATE users SET totp_last_step = ? WHERE id = ? AND totp_last_step < ?')->execute([$step, $id, $step]);
    }

    public function setRecoveryCodes(int $id, array $hashes): void
    {
        $this->db->prepare('UPDATE users SET recovery_codes = ? WHERE id = ?')->execute([json_encode(array_values($hashes)), $id]);
    }

    public function resetTotp(int $id): void
    {
        $this->db->prepare('UPDATE users SET totp_secret = NULL, totp_enabled_at = NULL, totp_last_step = 0, recovery_codes = NULL WHERE id = ?')
            ->execute([$id]);
    }

    public function touchLogin(int $id): void
    {
        $this->db->prepare('UPDATE users SET last_login = ? WHERE id = ?')->execute([$this->now(), $id]);
    }

    // ---------------------------------------------------------------- log

    public function logEpp(array $r): void
    {
        $r['created_at'] = $this->now();
        $cols = implode(',', array_keys($r));
        $ph = implode(',', array_fill(0, count($r), '?'));
        $this->db->prepare("INSERT INTO epp_log ($cols) VALUES ($ph)")->execute(array_values($r));
    }

    public function logs(array $f, int $limit = 100, int $offset = 0): array
    {
        [$w, $p] = $this->logWhere($f);
        $st = $this->db->prepare("SELECT id, created_at, username, command, object, cltrid, svtrid, result_code, result_msg, duration_ms, error
            FROM epp_log $w ORDER BY id DESC LIMIT " . (int)$limit . ' OFFSET ' . (int)$offset);
        $st->execute($p);
        return $st->fetchAll();
    }

    private function logWhere(array $f): array
    {
        $w = [];
        $p = [];
        if (!empty($f['q'])) {
            $w[] = '(object LIKE ? OR cltrid LIKE ? OR svtrid LIKE ?)';
            array_push($p, "%{$f['q']}%", "%{$f['q']}%", "%{$f['q']}%");
        }
        if (!empty($f['command'])) {
            $w[] = 'command = ?';
            $p[] = $f['command'];
        }
        if (!empty($f['errors'])) {
            $w[] = '(result_code >= 2000 OR error IS NOT NULL)';
        }
        return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $p];
    }

    /** Cancella i log più vecchi di $days giorni (a blocchi, per non bloccare la tabella). */
    public function purgeLogs(int $days): int
    {
        $limit = date('Y-m-d H:i:s', strtotime("-$days days"));
        $tot = 0;
        do {
            $ids = $this->db->prepare('SELECT id FROM epp_log WHERE created_at < ? ORDER BY id LIMIT 5000');
            $ids->execute([$limit]);
            $list = $ids->fetchAll(PDO::FETCH_COLUMN);
            if ($list) {
                $this->db->exec('DELETE FROM epp_log WHERE id IN (' . implode(',', array_map('intval', $list)) . ')');
                $tot += count($list);
            }
        } while (count($list) === 5000);
        return $tot;
    }

    public function logStats(): array
    {
        $r = $this->db->query('SELECT COUNT(*) AS n, MIN(created_at) AS oldest FROM epp_log')->fetch();
        return ['count' => (int)$r['n'], 'oldest' => $r['oldest']];
    }

    public function log(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM epp_log WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    // ---------------------------------------------------------------- cache domini/contatti

    public function saveDomain(array $i): void
    {
        $this->upsert('domains', 'name', [
            'name' => strtolower($i['name']),
            'roid' => $i['roid'] ?? null,
            'registrant' => $i['registrant'] ?? null,
            'status' => implode(',', $i['status'] ?? []),
            'cl_id' => $i['clID'] ?? null,
            'cr_date' => self::dt($i['crDate'] ?? null),
            'ex_date' => self::dt($i['exDate'] ?? null),
            'updated_at' => $this->now(),
            'info_json' => json_encode($i, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function domainUpdatedSince(string $name, string $since): bool
    {
        $st = $this->db->prepare('SELECT 1 FROM domains WHERE name = ? AND updated_at >= ?');
        $st->execute([strtolower($name), $since]);
        return (bool)$st->fetchColumn();
    }

    public function deleteDomain(string $name): void
    {
        $this->db->prepare('DELETE FROM domains WHERE name = ?')->execute([strtolower($name)]);
    }

    public const DOMAIN_SORT = ['name', 'registrant', 'status', 'cl_id', 'ex_date', 'updated_at'];
    public const CONTACT_SORT = ['id', 'name', 'org', 'email', 'is_registrant', 'updated_at'];

    /** ORDER BY da whitelist; i valori vuoti vanno sempre in fondo. */
    private static function orderBy(string $col, string $dir, array $allowed, string $tie): string
    {
        $col = in_array($col, $allowed, true) ? $col : $allowed[0];
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        $empty = in_array($col, ['ex_date', 'updated_at', 'is_registrant'], true) ? "$col IS NULL" : "($col IS NULL OR $col = '')";
        return "$empty ASC, $col $dir" . ($col !== $tie ? ", $tie ASC" : '');
    }

    public function domains(string $q = '', string $order = 'name', string $dir = 'asc', int $limit = 1000): array
    {
        $ord = self::orderBy($order, $dir, self::DOMAIN_SORT, 'name');
        $st = $this->db->prepare("SELECT name, registrant, status, cl_id, ex_date, updated_at FROM domains
            WHERE name LIKE ? OR registrant LIKE ? ORDER BY $ord LIMIT " . (int)$limit);
        $st->execute(["%$q%", "%$q%"]);
        return $st->fetchAll();
    }

    public function countDomains(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM domains')->fetchColumn();
    }

    public function countContacts(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM contacts')->fetchColumn();
    }

    public function domainsByContact(string $id): array
    {
        $st = $this->db->prepare('SELECT name FROM domains WHERE registrant = ? OR info_json LIKE ? ORDER BY name');
        $st->execute([$id, '%"id":"' . str_replace(['%', '_'], ['\%', '\_'], $id) . '"%']);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }

    public function saveContact(array $i): void
    {
        $this->upsert('contacts', 'id', [
            'id' => $i['id'],
            'name' => $i['name'] ?? null,
            'org' => $i['org'] ?? null,
            'email' => $i['email'] ?? null,
            'is_registrant' => empty($i['registrant']) ? 0 : 1,
            'updated_at' => $this->now(),
            'info_json' => json_encode($i, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function deleteContact(string $id): void
    {
        $this->db->prepare('DELETE FROM contacts WHERE id = ?')->execute([$id]);
    }

    public function contacts(string $q = '', string $order = 'id', string $dir = 'asc', int $limit = 1000): array
    {
        $ord = self::orderBy($order, $dir, self::CONTACT_SORT, 'id');
        $st = $this->db->prepare("SELECT id, name, org, email, is_registrant, updated_at FROM contacts
            WHERE id LIKE ? OR name LIKE ? OR org LIKE ? OR email LIKE ? ORDER BY $ord LIMIT " . (int)$limit);
        $st->execute(["%$q%", "%$q%", "%$q%", "%$q%"]);
        return $st->fetchAll();
    }

    /**
     * Ricerca per l'autocompletamento: ogni parola deve comparire in ID, nome, organizzazione o email
     * (es. "mario rossi" o "pensareweb srl"). Solo cache locale: EPP non ha una ricerca per nome.
     */
    public function searchContacts(string $q, bool $onlyRegistrants = false, int $limit = 15): array
    {
        $w = [];
        $p = [];
        foreach (array_slice(preg_split('/\s+/', trim($q)), 0, 5) as $word) {
            if ($word === '') {
                continue;
            }
            $like = '%' . addcslashes($word, '%_\\') . '%';
            $w[] = '(id LIKE ? OR name LIKE ? OR org LIKE ? OR email LIKE ?)';
            array_push($p, $like, $like, $like, $like);
        }
        if (!$w) {
            return [];
        }
        if ($onlyRegistrants) {
            $w[] = 'is_registrant = 1';
        }
        $st = $this->db->prepare('SELECT id, name, org, email, is_registrant, info_json FROM contacts WHERE ' . implode(' AND ', $w)
            . ' ORDER BY (id = ?) DESC, name LIMIT ' . (int)$limit);
        $st->execute([...$p, strtoupper(trim($q))]);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $i = json_decode((string)$r['info_json'], true) ?: [];
            $out[] = [
                'id' => $r['id'], 'name' => $r['name'], 'org' => $r['org'], 'email' => $r['email'],
                'city' => $i['city'] ?? '', 'registrant' => (bool)$r['is_registrant'],
                'entityType' => $i['registrant']['entityType'] ?? null,
            ];
        }
        return $out;
    }

    public function contact(string $id): ?array
    {
        $st = $this->db->prepare('SELECT info_json FROM contacts WHERE id = ?');
        $st->execute([$id]);
        $j = $st->fetchColumn();
        return $j ? json_decode($j, true) : null;
    }

    // ---------------------------------------------------------------- poll

    public function savePoll(string $msgId, string $qDate, string $msg, string $raw): void
    {
        $st = $this->db->prepare('SELECT 1 FROM poll_messages WHERE msg_id = ?');
        $st->execute([$msgId]);
        if (!$st->fetchColumn()) {
            $this->db->prepare('INSERT INTO poll_messages (msg_id, q_date, msg, raw_xml, created_at) VALUES (?,?,?,?,?)')
                ->execute([$msgId, $qDate, mb_substr($msg, 0, 1024), $raw, $this->now()]);
        }
    }

    public function ackPoll(string $msgId, string $by): void
    {
        $this->db->prepare('UPDATE poll_messages SET acked_at = ?, acked_by = ? WHERE msg_id = ?')
            ->execute([$this->now(), $by, $msgId]);
    }

    public function polls(int $limit = 200): array
    {
        return $this->db->query('SELECT id, msg_id, q_date, msg, created_at, acked_at, acked_by FROM poll_messages ORDER BY id DESC LIMIT ' . (int)$limit)->fetchAll();
    }

    public function poll(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM poll_messages WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }
}
