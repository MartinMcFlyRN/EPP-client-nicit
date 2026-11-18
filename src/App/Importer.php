<?php
declare(strict_types=1);

namespace NicEpp\App;

use NicEpp\Epp\Client;

/**
 * Import del portafoglio domini: per ogni nome esegue domain:info, salva nella cache locale
 * e (opzionale) importa anche registrante e contatti admin/tech con contact:info.
 * Lo stato del job è su file, così l'import web procede a blocchi e si può riprendere.
 */
final class Importer
{
    public function __construct(private Client $epp, private Repo $repo, private string $jobFile, private string $ourClid) {}

    /**
     * Estrae i nomi a dominio da testo libero o CSV (es. export dal portale RAIN):
     * per ogni riga prende il primo campo che è un nome .it valido.
     */
    public static function parseNames(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/', $text) as $line) {
            foreach (preg_split('/[;,\t|\s]+/', trim($line)) as $f) {
                $f = strtolower(trim($f, " \t\"'"));
                if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $f)) {
                    $f = (string)idn_to_ascii($f, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
                }
                if (preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+it$/', $f) && !str_contains($f, '@')) {
                    $out[$f] = true;
                    break;
                }
            }
        }
        return array_keys($out);
    }

    // ---------------------------------------------------------------- job

    public function job(): ?array
    {
        if (!is_file($this->jobFile)) {
            return null;
        }
        $j = json_decode((string)file_get_contents($this->jobFile), true);
        return is_array($j) ? $j : null;
    }

    private function saveJob(array $j): void
    {
        file_put_contents($this->jobFile, json_encode($j, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public function start(array $names, bool $contacts, bool $skipRecent, string $by): array
    {
        $j = [
            'names' => array_values($names), 'pos' => 0, 'contacts' => $contacts, 'skip_recent' => $skipRecent,
            'ok' => 0, 'skipped' => 0, 'foreign' => 0, 'contacts_ok' => 0, 'errors' => [], 'seen_contacts' => [],
            'started_at' => date('Y-m-d H:i:s'), 'finished_at' => null, 'by' => $by,
        ];
        $this->saveJob($j);
        return $j;
    }

    public function cancel(): void
    {
        @unlink($this->jobFile);
    }

    /** Elabora nomi finché non passa $seconds; restituisce lo stato aggiornato. */
    public function step(int $seconds = 20, int $sleepMs = 200, ?callable $progress = null): array
    {
        $j = $this->job();
        if (!$j || $j['finished_at']) {
            return $j ?? [];
        }
        $t0 = time();
        $j['last_error'] = null;
        while ($j['pos'] < count($j['names']) && (time() - $t0) < $seconds) {
            $name = $j['names'][$j['pos']];
            try {
                $this->one($name, $j);
            } catch (\NicEpp\Epp\EppException $e) {
                // errore di connessione o login: si ferma senza avanzare, si potrà riprendere
                $j['last_error'] = $e->response ? $e->response->errorText() : $e->getMessage();
                $this->saveJob($j);
                return $j;
            }
            $j['pos']++;
            if ($j['pos'] % 5 === 0) {
                $this->saveJob($j); // checkpoint
            }
            if ($progress) {
                $progress($j, $name);
            }
            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }
        if ($j['pos'] >= count($j['names'])) {
            $j['finished_at'] = date('Y-m-d H:i:s');
        }
        $this->saveJob($j);
        return $j;
    }

    private function one(string $name, array &$j): void
    {
        try {
            if ($j['skip_recent'] && $this->repo->domainUpdatedSince($name, date('Y-m-d H:i:s', strtotime('-1 day')))) {
                $j['skipped']++;
                return;
            }
            $r = $this->epp->domainInfo($name);
            if (!$r->ok()) {
                $this->err($j, $name, $r->errorText());
                return;
            }
            $d = $r->domainInfo();
            $this->repo->saveDomain($d);
            $j['ok']++;
            if ($d['clID'] !== $this->ourClid) {
                $j['foreign']++; // il dominio non è (più) sotto il nostro registrar
                return;
            }
            if ($j['contacts']) {
                $ids = array_unique(array_filter(array_merge([$d['registrant']], array_column($d['contacts'], 'id'))));
                foreach ($ids as $id) {
                    if (isset($j['seen_contacts'][$id])) {
                        continue;
                    }
                    $j['seen_contacts'][$id] = 1;
                    $c = $this->epp->contactInfo($id);
                    if ($c->ok()) {
                        $this->repo->saveContact($c->contactInfo());
                        $j['contacts_ok']++;
                    } else {
                        $this->err($j, \t('imp.err_contact', ['name' => $name, 'id' => $id]), $c->errorText());
                    }
                }
            }
        } catch (\NicEpp\Epp\EppException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->err($j, $name, $e->getMessage());
        }
    }

    private function err(array &$j, string $what, string $msg): void
    {
        if (count($j['errors']) < 500) {
            $j['errors'][] = ['what' => $what, 'msg' => $msg];
        }
    }
}
