<?php
// Mock minimale del server EPP .it per test locali (HTTP POST + cookie di sessione).
session_name('JSESSIONID');
session_start();
$xml = file_get_contents('php://input');
file_put_contents(__DIR__ . '/mock-requests.log', "----\n" . $xml . "\n", FILE_APPEND);
header('Content-Type: text/xml');

$d = new DOMDocument();
if (!@$d->loadXML($xml)) { http_response_code(400); exit('bad xml'); }
$xp = new DOMXPath($d);
$xp->registerNamespace('e', 'urn:ietf:params:xml:ns:epp-1.0');
$tr = $xp->evaluate('string(//e:clTRID)');

function resp($code, $msg, $body = '', $extra = '') {
    global $tr;
    echo '<?xml version="1.0" encoding="UTF-8"?><epp xmlns="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xmlns:contact="urn:ietf:params:xml:ns:contact-1.0" xmlns:extepp="http://www.nic.it/ITNIC-EPP/extepp-2.0" xmlns:extcon="http://www.nic.it/ITNIC-EPP/extcon-1.0" xmlns:secDNS="urn:ietf:params:xml:ns:secDNS-1.1" xmlns:rgp="urn:ietf:params:xml:ns:rgp-1.0"><response>'
        . '<result code="' . $code . '"><msg>' . $msg . '</msg>' . $extra . '</result>' . $body
        . '<trID><clTRID>' . htmlspecialchars($tr) . '</clTRID><svTRID>SV-' . uniqid() . '</svTRID></trID></response></epp>';
    exit;
}

if ($xp->query('//e:hello')->length) {
    echo '<?xml version="1.0" encoding="UTF-8"?><epp xmlns="urn:ietf:params:xml:ns:epp-1.0"><greeting><svID>Mock .it EPP</svID><svDate>' . gmdate('c') . '</svDate></greeting></epp>';
    exit;
}
if ($xp->query('//e:login')->length) {
    $_SESSION['in'] = true;
    $_SESSION['polls'] = $_SESSION['polls'] ?? 2;
    resp(1000, 'Command completed successfully', '<msgQ count="2" id="1"/>');
}
if (empty($_SESSION['in'])) {
    resp(2002, 'Command use error');
}
if ($xp->query('//e:logout')->length) { session_destroy(); resp(1500, 'Command completed successfully; ending session'); }

$local = $xp->evaluate('local-name(//e:command/*[1]/*[1])'); // check/info/create...
$cmd = $xp->evaluate('local-name(//e:command/*[1])');
$ns = $xp->evaluate('namespace-uri(//e:command/*[1]/*[1])');
$isDomain = str_contains($ns, 'domain');

if ($cmd === 'poll') {
    $op = $xp->evaluate('string(//e:poll/@op)');
    $n = $_SESSION['polls'] ?? 0;
    if ($op === 'req') {
        if ($n <= 0) resp(1300, 'Command completed successfully; no messages');
        resp(1301, 'Command completed successfully; ack to dequeue', '<msgQ count="' . $n . '" id="M' . $n . '"><qDate>2026-11-17T10:00:00Z</qDate><msg>Transfer of esempio.it completed</msg></msgQ>');
    }
    $_SESSION['polls'] = $n - 1;
    resp(1000, 'Command completed successfully', '<msgQ count="' . ($n - 1) . '" id="M' . $n . '"/>');
}

if ($cmd === 'check' && $isDomain) {
    $cd = '';
    foreach ($xp->query('//*[local-name()="name"]') as $nn) {
        $av = str_contains($nn->textContent, 'taken') ? 'false' : 'true';
        $cd .= '<domain:cd><domain:name avail="' . $av . '">' . $nn->textContent . '</domain:name>' . ($av === 'false' ? '<domain:reason>Domain already registered</domain:reason>' : '') . '</domain:cd>';
    }
    resp(1000, 'Command completed successfully', '<resData><domain:chkData>' . $cd . '</domain:chkData></resData>');
}
if ($cmd === 'info' && $isDomain) {
    $n = $xp->evaluate('string(//*[local-name()="name"])');
    if (str_contains($n, 'missing')) resp(2303, 'Object does not exist');
    resp(1000, 'Command completed successfully', '<resData><domain:infData><domain:name>' . $n . '</domain:name><domain:roid>D123-ITNIC</domain:roid>'
        . '<domain:status s="ok" lang="en"/><domain:registrant>PWEB-R1</domain:registrant><domain:contact type="admin">PWEB-R1</domain:contact><domain:contact type="tech">PWEB-T1</domain:contact>'
        . '<domain:ns><domain:hostAttr><domain:hostName>ns1.' . $n . '</domain:hostName><domain:hostAddr ip="v4">192.0.2.1</domain:hostAddr></domain:hostAttr><domain:hostAttr><domain:hostName>ns2.provider.net</domain:hostName></domain:hostAttr></domain:ns>'
        . '<domain:clID>TEST-REG</domain:clID><domain:crID>TEST-REG</domain:crID><domain:crDate>2024-01-10T10:00:00+01:00</domain:crDate><domain:exDate>2026-12-10T23:59:59+01:00</domain:exDate>'
        . '<domain:authInfo><domain:pw>Ab3-xyzw12</domain:pw></domain:authInfo></domain:infData></resData>'
        . '<extension><secDNS:infData><secDNS:dsData><secDNS:keyTag>12345</secDNS:keyTag><secDNS:alg>13</secDNS:alg><secDNS:digestType>2</secDNS:digestType><secDNS:digest>ABCDEF0123</secDNS:digest></secDNS:dsData></secDNS:infData></extension>');
}
if ($cmd === 'create' && $isDomain) {
    $n = $xp->evaluate('string(//*[local-name()="name"])');
    resp(1000, 'Command completed successfully', '<resData><domain:creData><domain:name>' . $n . '</domain:name><domain:crDate>2026-11-18T01:00:00+01:00</domain:crDate><domain:exDate>2027-11-30T23:59:59+01:00</domain:exDate></domain:creData></resData>');
}
if ($cmd === 'transfer') {
    resp(1001, 'Command completed successfully; action pending', '<resData><domain:trnData><domain:name>' . $xp->evaluate('string(//*[local-name()="name"])') . '</domain:name><domain:trStatus>pending</domain:trStatus><domain:reID>TEST-REG</domain:reID><domain:reDate>2026-11-18T01:00:00Z</domain:reDate><domain:acID>OTHER-REG</domain:acID><domain:acDate>2026-11-19T01:00:00Z</domain:acDate></domain:trnData></resData>');
}
if ($cmd === 'check') { // contact: i primi N id visti risultano occupati (N in taken.txt)
    $f = __DIR__ . '/taken.txt'; $left = is_file($f) ? (int)file_get_contents($f) : 0; $cd = '';
    foreach ($xp->query('//*[local-name()="id"]') as $n) {
        $taken = $left > 0 || str_contains($n->textContent, 'TAKEN'); if ($left > 0) $left--;
        $cd .= '<contact:cd><contact:id avail="' . ($taken ? 'false' : 'true') . '">' . $n->textContent . '</contact:id></contact:cd>';
    }
    file_put_contents($f, (string)$left);
    resp(1000, 'Command completed successfully', '<resData><contact:chkData>' . $cd . '</contact:chkData></resData>');
}
if ($cmd === 'info') { // contact
    $id = $xp->evaluate('string(//*[local-name()="id"])');
    resp(1000, 'Command completed successfully', '<resData><contact:infData><contact:id>' . $id . '</contact:id><contact:roid>C1-ITNIC</contact:roid><contact:status s="ok"/>'
        . '<contact:postalInfo type="loc"><contact:name>Mario Rossi</contact:name><contact:addr><contact:street>Via Roma 1</contact:street><contact:city>Genova</contact:city><contact:sp>GE</contact:sp><contact:pc>16100</contact:pc><contact:cc>IT</contact:cc></contact:addr></contact:postalInfo>'
        . '<contact:voice>+39.0101234567</contact:voice><contact:email>mario@example.it</contact:email><contact:clID>TEST-REG</contact:clID><contact:crDate>2024-01-10T10:00:00Z</contact:crDate></contact:infData></resData>'
        . '<extension><extcon:infData><extcon:consentForPublishing>true</extcon:consentForPublishing><extcon:registrant><extcon:nationalityCode>IT</extcon:nationalityCode><extcon:entityType>1</extcon:entityType><extcon:regCode>RSSMRA64C14G702Q</extcon:regCode></extcon:registrant></extcon:infData></extension>');
}
if ($cmd === 'create') { // contact con errore simulato se regCode = BAD
    if (str_contains($xml, '<extcon:regCode>BAD</extcon:regCode>')) {
        resp(2004, 'Parameter value range error', '', '<extValue><value><extepp:wrongValue><extepp:element>regCode</extepp:element><extepp:namespace>http://www.nic.it/ITNIC-EPP/extcon-1.0</extepp:namespace><extepp:value>BAD</extepp:value></extepp:wrongValue></value><reason lang="en">Registrant: invalid reg code</reason></extValue><extValue><value><extepp:reasonCode>8027</extepp:reasonCode></value><reason>Registrant: invalid reg code</reason></extValue>');
    }
    resp(1000, 'Command completed successfully');
}
resp(1000, 'Command completed successfully');
