<?php
// Copia in config.php e personalizza. NON mettere config.php sotto il document root.
return [
    'app' => [
        'name'        => 'EPP .it',
        'base_url'    => '',             // es. '' se il vhost punta a public/, oppure '/epp'
        'timezone'    => 'Europe/Rome',
        'session_name'=> 'NICEPPSESS',
        // Maschera nei log i <pw> di authInfo dei domini/contatti (la password di login è sempre mascherata)
        'mask_authinfo_in_log' => false,
    ],
    'db' => [
        'dsn'  => 'mysql:host=127.0.0.1;dbname=nicepp;charset=utf8mb4',
        'user' => 'nicepp',
        'pass' => 'CAMBIAMI',
    ],
    'epp' => [
        // Produzione: https://epp.nic.it  — Test: https://pub-test.nic.it
        'url'          => 'https://epp.nic.it',
        'clid'         => 'REGISTRAR-REG',
        'password'     => 'CAMBIAMI',       // sovrascritta da storage/epp-password se presente (cambio password da UI)
        'tridPrefix'   => 'PWEB',
        'source_ip'    => '',               // IP sorgente autorizzato dal Registro (CURLOPT_INTERFACE), vuoto = default
        'ca_file'      => '',               // bundle CA custom, vuoto = di sistema
        'client_cert'  => '',
        'client_key'   => '',
        'timeout'      => 60,
        'connect_timeout' => 10,
        // Dopo quanti secondi di inattività rifare il login preventivamente.
        // Tienilo sotto il timeout di sessione del Registro.
        'idle_timeout' => 600,
        'storage_dir'  => __DIR__ . '/storage',
    ],
    'poll' => [
        // bin/poll-cron.php: salva i messaggi in DB e fa l'ack automatico
        'auto_ack' => true,
        'max_per_run' => 100,
    ],
];
