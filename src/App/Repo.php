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
        return $this->db->query('SELECT id, username, role, active, created_at, last_login FROM users ORDER BY username')->fetchAll();
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

    public function deleteDomain(string $name): void
    {
        $this->db->prepare('DELETE FROM domains WHERE name = ?')->execute([strtolower($name)]);
    }

    public function domains(string $q = '', string $order = 'name', int $limit = 500): array
    {
        $ord = $order === 'ex_date' ? 'ex_date IS NULL, ex_date ASC' : 'name ASC';
        $st = $this->db->prepare("SELECT name, registrant, status, cl_id, ex_date, updated_at FROM domains
            WHERE name LIKE ? OR registrant LIKE ? ORDER BY $ord LIMIT " . (int)$limit);
        $st->execute(["%$q%", "%$q%"]);
        return $st->fetchAll();
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

    public function contacts(string $q = '', int $limit = 500): array
    {
        $st = $this->db->prepare('SELECT id, name, org, email, is_registrant, updated_at FROM contacts
            WHERE id LIKE ? OR name LIKE ? OR org LIKE ? OR email LIKE ? ORDER BY id LIMIT ' . (int)$limit);
        $st->execute(["%$q%", "%$q%", "%$q%", "%$q%"]);
        return $st->fetchAll();
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
