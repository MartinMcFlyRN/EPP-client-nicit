<?php
// Scarica la coda poll del Registro, salva i messaggi in DB e (se configurato) fa l'ack.
// Crontab:  */10 * * * *  php /var/www/nicepp/bin/poll-cron.php >> /var/log/nicepp-poll.log 2>&1
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

$max = (int)cfg('poll.max_per_run', 100);
$ack = (bool)cfg('poll.auto_ack', true);
$n = 0;

try {
    while ($n < $max) {
        $r = epp()->pollReq();
        if ($r->code() === 1300) {
            break; // coda vuota
        }
        $q = $r->msgQ();
        if (!$r->ok() || !$q) {
            fwrite(STDERR, date('c') . ' poll error: ' . $r->errorText() . "\n");
            exit(1);
        }
        repo()->savePoll($q['id'], $q['qDate'], $q['msg'], $r->raw());
        echo date('c') . " msg {$q['id']}: {$q['msg']}\n";
        $n++;
        if (!$ack) {
            break; // senza ack il server restituirebbe sempre lo stesso messaggio
        }
        $a = epp()->pollAck($q['id']);
        if (!$a->ok()) {
            fwrite(STDERR, date('c') . ' ack error: ' . $a->errorText() . "\n");
            exit(1);
        }
        repo()->ackPoll($q['id'], 'cron');
    }
} catch (\Throwable $e) {
    fwrite(STDERR, date('c') . ' ' . $e->getMessage() . "\n");
    exit(1);
}
echo date('c') . " poll: $n messaggi\n";
