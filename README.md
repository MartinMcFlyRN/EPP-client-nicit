# EPP .it Web Client

Client EPP web per il Registro .it (nic.it), PHP 8.1+ e MySQL/MariaDB, senza framework né dipendenze esterne.
Pensato per uso interno dello staff del registrar.

## Funzioni

- **Domini**: check (multiplo), info (anche con AuthInfo per domini di altri registrar), create (2–6 NS con glue, DS opzionali),
  update di NS, contatti admin/tech, stati client (`clientHold`, `clientTransferProhibited`, …), AuthInfo, delete, restore (rgp).
- **Modifica registrante**: update con `chg/registrant` e nuovo AuthInfo obbligatorio, con admin opzionale.
- **Trasferimenti**: request, query, cancel, approve, reject, oltre al **Transfer-Trade** (`extdom:trade`, cambio registrante durante il trasferimento).
- **DNSSEC**: `secDNS-1.1` in create e update, con calcolo automatico di add e rem rispetto ai DS attuali (accetta anche il formato `IN DS …`).
- **Contatti**: check, info, create, update e delete con estensione `extcon` (consentForPublishing, nationalityCode, entityType 1–7, regCode).
- **Poll**: lettura e ack manuali, più un cron che salva tutta la coda in DB e la conferma.
- **Audit log**: ogni comando con XML di richiesta e risposta, utente, clTRID/svTRID e durata. La password di login è sempre mascherata.
- **Console XML** (solo admin): invia XML arbitrario sulla sessione autenticata.
- **Utenti** con ruoli `readonly`, `operator`, `admin`. CSRF su tutti i POST.
- **Cambio password EPP** da UI (`login` con `newPW`): la nuova password viene salvata in `storage/epp-password`.

## Come gestisce la sessione EPP

Il Registro .it usa EPP su **HTTPS POST** con sessione via cookie e limita IP e sessioni concorrenti.
Il client tiene **una sola sessione condivisa** (cookie jar in `storage/`) serializzata con `flock`:

- se la sessione non c'è o è inattiva da più di `idle_timeout`, esegue il login prima del comando;
- se il server risponde con il greeting, `2002` o `2200`, rifà il login e ripete il comando una volta;
- `source_ip` permette di uscire dall'IP autorizzato presso il Registro.

## Installazione

```bash
cp config.sample.php config.php        # credenziali DB ed EPP
mysql -u root -p -e "CREATE DATABASE nicepp CHARACTER SET utf8mb4"
mysql -u root -p nicepp < sql/schema.sql
mkdir -p storage && chown www-data:www-data storage && chmod 700 storage
php bin/create-user.php manuel admin
```

Il document root deve puntare a **`public/`**: `config.php`, `storage/` e `src/` restano fuori dal web.

### nginx (esempio)

```nginx
server {
    listen 443 ssl http2;
    server_name epp.interno.example.it;
    root /var/www/nicepp/public;
    index index.php;

    # solo rete interna / VPN
    allow 10.0.0.0/8;
    deny all;

    location / { try_files $uri /index.php?$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_read_timeout 120;
    }
}
```

### Apache

Se puoi, punta il DocumentRoot a `public/`. Se invece il vhost punta alla radice del progetto (ad esempio su hosting condiviso
o Plesk), il `.htaccess` incluso inoltra tutte le richieste a `public/` e blocca le cartelle interne
(`src/`, `views/`, `sql/`, `bin/`, `tests/`, `storage/`) e i file nascosti. Servono `mod_rewrite` e `AllowOverride All`
(o almeno `FileInfo Options`). Senza `mod_rewrite` il `.htaccess` nega tutto, così non c'è rischio di esporre la configurazione.
Lascia `base_url` a `''`.

### Cron poll

```
*/10 * * * * www-data php /var/www/nicepp/bin/poll-cron.php >> /var/log/nicepp-poll.log 2>&1
```

Il cron deve girare con lo **stesso utente di PHP-FPM**, perché condivide cookie jar e lock in `storage/`.

## Struttura

```
public/index.php        router + bootstrap sessione web
src/Epp/Transport.php   HTTPS POST + cookie jar (curl)
src/Epp/Commands.php    costruttori XML (domain, contact, extcon, extdom, rgp, secDNS)
src/Epp/Response.php    parser risposte + errori extepp (reasonCode, wrongValue)
src/Epp/Client.php      sessione condivisa, login/relogin, lock, logging
src/routes.php          controller
src/App/Repo.php        DB (utenti, log, cache domini/contatti, poll)
views/                  template
bin/                    create-user, poll-cron
```

## Note e cose da verificare in produzione

- Il codice segue le Linee Guida Tecniche Sincrone v3.x. Prima dell'uso in produzione conviene una prova sul server di test
  (`https://pub-test.nic.it`, impostando `epp.url`) per gli scenari che usate di più.
- Il Registro accetta il cambio di stato solo da solo in un update: per questo l'interfaccia ha form separati.
- La cache locale dei domini si riempie con Info e Create. Per importare il portafoglio esistente basta lanciare un Info su ogni dominio,
  per esempio con uno script che usa `epp()->domainInfo()` e `repo()->saveDomain()`.
- Il credito non è esposto da un comando dedicato: le notifiche di credito arrivano nella coda poll.
