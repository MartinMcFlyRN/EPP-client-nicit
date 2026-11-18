<?php
declare(strict_types=1);

namespace NicEpp\Epp;

/**
 * Costruttori degli XML EPP secondo le Linee Guida Tecniche Sincrone del Registro .it.
 * Ogni metodo restituisce il documento XML completo.
 */
final class Commands
{
    public const NS_EPP       = 'urn:ietf:params:xml:ns:epp-1.0';
    public const NS_DOMAIN    = 'urn:ietf:params:xml:ns:domain-1.0';
    public const NS_CONTACT   = 'urn:ietf:params:xml:ns:contact-1.0';
    public const NS_EXTEPP    = 'http://www.nic.it/ITNIC-EPP/extepp-2.0';
    public const NS_EXTCON    = 'http://www.nic.it/ITNIC-EPP/extcon-1.0';
    public const NS_EXTDOM    = 'http://www.nic.it/ITNIC-EPP/extdom-2.0';
    public const NS_RGP       = 'urn:ietf:params:xml:ns:rgp-1.0';
    public const NS_SECDNS    = 'urn:ietf:params:xml:ns:secDNS-1.1';
    public const NS_EXTSECDNS = 'http://www.nic.it/ITNIC-EPP/extsecDNS-1.0';

    public const DOMAIN_CLIENT_STATUSES = [
        'clientDeleteProhibited', 'clientUpdateProhibited', 'clientTransferProhibited', 'clientHold',
    ];

    private string $lastTrid = '';

    public function __construct(private string $tridPrefix = 'WEB', private bool $dnssec = false) {}

    public function dnssecEnabled(): bool
    {
        return $this->dnssec;
    }

    public static function e(?string $s): string
    {
        return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public function lastTrid(): string
    {
        return $this->lastTrid;
    }

    private function trid(): string
    {
        $this->lastTrid = substr($this->tridPrefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)), 0, 64);
        return $this->lastTrid;
    }

    private function head(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n"
            . '<epp xmlns="' . self::NS_EPP . '">';
    }

    private function command(string $inner, string $ext = ''): string
    {
        $x = $this->head() . '<command>' . $inner;
        if ($ext !== '') {
            $x .= '<extension>' . $ext . '</extension>';
        }
        return $x . '<clTRID>' . $this->trid() . '</clTRID></command></epp>';
    }

    // ------------------------------------------------------------------ sessione

    public function hello(): string
    {
        $this->lastTrid = '';
        return $this->head() . '<hello/></epp>';
    }

    public function login(string $clID, string $pw, ?string $newPw = null): string
    {
        $x = '<login><clID>' . self::e($clID) . '</clID><pw>' . self::e($pw) . '</pw>';
        if ($newPw !== null && $newPw !== '') {
            $x .= '<newPW>' . self::e($newPw) . '</newPW>';
        }
        $x .= '<options><version>1.0</version><lang>en</lang></options><svcs>'
            . '<objURI>' . self::NS_CONTACT . '</objURI>'
            . '<objURI>' . self::NS_DOMAIN . '</objURI>'
            . '<svcExtension>';
        $ext = [self::NS_EXTEPP, self::NS_EXTCON, self::NS_EXTDOM, self::NS_RGP];
        if ($this->dnssec) { // solo per registrar accreditati DNSSEC, altrimenti il Registro rifiuta il login (2306 / 10001)
            array_push($ext, self::NS_SECDNS, self::NS_EXTSECDNS);
        }
        foreach ($ext as $u) {
            $x .= '<extURI>' . $u . '</extURI>';
        }
        $x .= '</svcExtension></svcs></login>';
        return $this->command($x);
    }

    public function logout(): string
    {
        return $this->command('<logout/>');
    }

    public function pollReq(): string
    {
        return $this->command('<poll op="req"/>');
    }

    public function pollAck(string $msgId): string
    {
        return $this->command('<poll op="ack" msgID="' . self::e($msgId) . '"/>');
    }

    // ------------------------------------------------------------------ domini

    /** @param string[] $names */
    public function domainCheck(array $names): string
    {
        $x = '<check><domain:check xmlns:domain="' . self::NS_DOMAIN . '">';
        foreach ($names as $n) {
            $x .= '<domain:name>' . self::e($n) . '</domain:name>';
        }
        return $this->command($x . '</domain:check></check>');
    }

    public function domainInfo(string $name, ?string $authInfo = null): string
    {
        $x = '<info><domain:info xmlns:domain="' . self::NS_DOMAIN . '">'
            . '<domain:name hosts="all">' . self::e($name) . '</domain:name>';
        if ($authInfo !== null && $authInfo !== '') {
            $x .= '<domain:authInfo><domain:pw>' . self::e($authInfo) . '</domain:pw></domain:authInfo>';
        }
        return $this->command($x . '</domain:info></info>');
    }

    /**
     * @param array<int,array{host:string,ips:string[]}> $ns
     * @param string[] $admins
     * @param string[] $techs
     * @param array<int,array{keyTag:int,alg:int,digestType:int,digest:string}> $ds
     */
    public function domainCreate(string $name, array $ns, string $registrant, string $admin, array $techs, string $authInfo, array $ds = [], int $period = 1): string
    {
        $x = '<create><domain:create xmlns:domain="' . self::NS_DOMAIN . '">'
            . '<domain:name>' . self::e($name) . '</domain:name>'
            . '<domain:period unit="y">' . $period . '</domain:period>'
            . $this->nsXml($ns)
            . '<domain:registrant>' . self::e($registrant) . '</domain:registrant>'
            . '<domain:contact type="admin">' . self::e($admin) . '</domain:contact>';
        foreach ($techs as $t) {
            $x .= '<domain:contact type="tech">' . self::e($t) . '</domain:contact>';
        }
        $x .= '<domain:authInfo><domain:pw>' . self::e($authInfo) . '</domain:pw></domain:authInfo>'
            . '</domain:create></create>';

        $ext = '';
        if ($ds && $this->dnssec) {
            $ext = '<secDNS:create xmlns:secDNS="' . self::NS_SECDNS . '">' . $this->dsXml($ds) . '</secDNS:create>';
        }
        return $this->command($x, $ext);
    }

    /**
     * Update generico.
     * $add / $rem: ['ns'=>[...hostAttr], 'contacts'=>[['type'=>'tech','id'=>'X']], 'status'=>['clientHold']]
     * $chg: ['registrant'=>'ID', 'authInfo'=>'pw']
     * $secDns: ['remAll'=>bool, 'rem'=>[ds...], 'add'=>[ds...]]
     */
    public function domainUpdate(string $name, array $add = [], array $rem = [], array $chg = [], array $secDns = []): string
    {
        $x = '<update><domain:update xmlns:domain="' . self::NS_DOMAIN . '">'
            . '<domain:name>' . self::e($name) . '</domain:name>';
        foreach (['add' => $add, 'rem' => $rem] as $tag => $blk) {
            $inner = '';
            if (!empty($blk['ns'])) {
                $inner .= $this->nsXml($blk['ns']);
            }
            foreach ($blk['contacts'] ?? [] as $c) {
                $inner .= '<domain:contact type="' . self::e($c['type']) . '">' . self::e($c['id']) . '</domain:contact>';
            }
            foreach ($blk['status'] ?? [] as $s) {
                $inner .= '<domain:status s="' . self::e($s) . '"/>';
            }
            if ($inner !== '') {
                $x .= '<domain:' . $tag . '>' . $inner . '</domain:' . $tag . '>';
            }
        }
        $c = '';
        if (!empty($chg['registrant'])) {
            $c .= '<domain:registrant>' . self::e($chg['registrant']) . '</domain:registrant>';
        }
        if (!empty($chg['authInfo'])) {
            $c .= '<domain:authInfo><domain:pw>' . self::e($chg['authInfo']) . '</domain:pw></domain:authInfo>';
        }
        if ($c !== '') {
            $x .= '<domain:chg>' . $c . '</domain:chg>';
        }
        $x .= '</domain:update></update>';

        $ext = '';
        if ($secDns && $this->dnssec) {
            $s = '';
            if (!empty($secDns['remAll'])) {
                $s .= '<secDNS:rem><secDNS:all>true</secDNS:all></secDNS:rem>';
            } elseif (!empty($secDns['rem'])) {
                $s .= '<secDNS:rem>' . $this->dsXml($secDns['rem']) . '</secDNS:rem>';
            }
            if (!empty($secDns['add'])) {
                $s .= '<secDNS:add>' . $this->dsXml($secDns['add']) . '</secDNS:add>';
            }
            if ($s !== '') {
                $ext = '<secDNS:update xmlns:secDNS="' . self::NS_SECDNS . '">' . $s . '</secDNS:update>';
            }
        }
        return $this->command($x, $ext);
    }

    public function domainRestore(string $name): string
    {
        $x = '<update><domain:update xmlns:domain="' . self::NS_DOMAIN . '">'
            . '<domain:name>' . self::e($name) . '</domain:name><domain:chg/>'
            . '</domain:update></update>';
        $ext = '<rgp:update xmlns:rgp="' . self::NS_RGP . '"><rgp:restore op="request"/></rgp:update>';
        return $this->command($x, $ext);
    }

    public function domainDelete(string $name): string
    {
        return $this->command('<delete><domain:delete xmlns:domain="' . self::NS_DOMAIN . '">'
            . '<domain:name>' . self::e($name) . '</domain:name></domain:delete></delete>');
    }

    /**
     * op: request | query | approve | reject | cancel
     * Con $newRegistrant + $newAuthInfo su op=request esegue un Transfer-Trade (extdom:trade).
     */
    public function domainTransfer(string $op, string $name, string $authInfo, ?string $newRegistrant = null, ?string $newAuthInfo = null): string
    {
        $x = '<transfer op="' . self::e($op) . '"><domain:transfer xmlns:domain="' . self::NS_DOMAIN . '">'
            . '<domain:name>' . self::e($name) . '</domain:name>';
        if ($authInfo !== '') {
            $x .= '<domain:authInfo><domain:pw>' . self::e($authInfo) . '</domain:pw></domain:authInfo>';
        }
        $x .= '</domain:transfer></transfer>';
        $ext = '';
        if ($op === 'request' && $newRegistrant) {
            $ext = '<extdom:trade xmlns:extdom="' . self::NS_EXTDOM . '"><extdom:transferTrade>'
                . '<extdom:newRegistrant>' . self::e($newRegistrant) . '</extdom:newRegistrant>'
                . '<extdom:newAuthInfo><extdom:pw>' . self::e((string)$newAuthInfo) . '</extdom:pw></extdom:newAuthInfo>'
                . '</extdom:transferTrade></extdom:trade>';
        }
        return $this->command($x, $ext);
    }

    // ------------------------------------------------------------------ contatti

    /** @param string[] $ids */
    public function contactCheck(array $ids): string
    {
        $x = '<check><contact:check xmlns:contact="' . self::NS_CONTACT . '">';
        foreach ($ids as $i) {
            $x .= '<contact:id>' . self::e($i) . '</contact:id>';
        }
        return $this->command($x . '</contact:check></check>');
    }

    public function contactInfo(string $id): string
    {
        return $this->command('<info><contact:info xmlns:contact="' . self::NS_CONTACT . '">'
            . '<contact:id>' . self::e($id) . '</contact:id></contact:info></info>');
    }

    /**
     * $c: id, name, org, street[] (1-3), city, sp, pc, cc, voice, fax, email, authInfo,
     *     consent (bool), registrant (null | ['nationality'=>'IT','entityType'=>1,'regCode'=>'...'])
     */
    public function contactCreate(array $c): string
    {
        $x = '<create><contact:create xmlns:contact="' . self::NS_CONTACT . '">'
            . '<contact:id>' . self::e($c['id']) . '</contact:id>'
            . $this->postalInfoXml($c);
        if (!empty($c['voice'])) {
            $x .= '<contact:voice>' . self::e($c['voice']) . '</contact:voice>';
        }
        if (!empty($c['fax'])) {
            $x .= '<contact:fax>' . self::e($c['fax']) . '</contact:fax>';
        }
        $x .= '<contact:email>' . self::e($c['email']) . '</contact:email>'
            . '<contact:authInfo><contact:pw>' . self::e($c['authInfo'] ?? bin2hex(random_bytes(6))) . '</contact:pw></contact:authInfo>'
            . '</contact:create></create>';

        $ext = '<extcon:create xmlns:extcon="' . self::NS_EXTCON . '">' . $this->extconBody($c) . '</extcon:create>';
        return $this->command($x, $ext);
    }

    /** Aggiorna postalInfo/voice/fax/email e la sezione extcon (consenso, dati registrante). */
    public function contactUpdate(array $c): string
    {
        $x = '<update><contact:update xmlns:contact="' . self::NS_CONTACT . '">'
            . '<contact:id>' . self::e($c['id']) . '</contact:id><contact:chg>'
            . $this->postalInfoXml($c);
        if (!empty($c['voice'])) {
            $x .= '<contact:voice>' . self::e($c['voice']) . '</contact:voice>';
        }
        if (!empty($c['fax'])) {
            $x .= '<contact:fax>' . self::e($c['fax']) . '</contact:fax>';
        }
        $x .= '<contact:email>' . self::e($c['email']) . '</contact:email>';
        $x .= '</contact:chg></contact:update></update>';
        $ext = '<extcon:update xmlns:extcon="' . self::NS_EXTCON . '">' . $this->extconBody($c) . '</extcon:update>';
        return $this->command($x, $ext);
    }

    public function contactDelete(string $id): string
    {
        return $this->command('<delete><contact:delete xmlns:contact="' . self::NS_CONTACT . '">'
            . '<contact:id>' . self::e($id) . '</contact:id></contact:delete></delete>');
    }

    // ------------------------------------------------------------------ helper

    private function postalInfoXml(array $c): string
    {
        $x = '<contact:postalInfo type="loc"><contact:name>' . self::e($c['name']) . '</contact:name>';
        if (!empty($c['org'])) {
            $x .= '<contact:org>' . self::e($c['org']) . '</contact:org>';
        }
        $x .= '<contact:addr>';
        foreach (array_slice(array_values(array_filter($c['street'] ?? [], fn($s) => trim((string)$s) !== '')), 0, 3) as $s) {
            $x .= '<contact:street>' . self::e($s) . '</contact:street>';
        }
        $x .= '<contact:city>' . self::e($c['city']) . '</contact:city>';
        if (!empty($c['sp'])) {
            $x .= '<contact:sp>' . self::e($c['sp']) . '</contact:sp>';
        }
        if (!empty($c['pc'])) {
            $x .= '<contact:pc>' . self::e($c['pc']) . '</contact:pc>';
        }
        $x .= '<contact:cc>' . self::e(strtoupper($c['cc'])) . '</contact:cc></contact:addr></contact:postalInfo>';
        return $x;
    }

    private function extconBody(array $c): string
    {
        $x = '<extcon:consentForPublishing>' . (!empty($c['consent']) ? 'true' : 'false') . '</extcon:consentForPublishing>';
        if (!empty($c['registrant'])) {
            $r = $c['registrant'];
            $x .= '<extcon:registrant>'
                . '<extcon:nationalityCode>' . self::e(strtoupper($r['nationality'])) . '</extcon:nationalityCode>'
                . '<extcon:entityType>' . (int)$r['entityType'] . '</extcon:entityType>'
                . '<extcon:regCode>' . self::e($r['regCode']) . '</extcon:regCode>'
                . '</extcon:registrant>';
        }
        return $x;
    }

    /** @param array<int,array{host:string,ips:string[]}> $ns */
    private function nsXml(array $ns): string
    {
        $x = '<domain:ns>';
        foreach ($ns as $h) {
            $x .= '<domain:hostAttr><domain:hostName>' . self::e(strtolower($h['host'])) . '</domain:hostName>';
            foreach ($h['ips'] ?? [] as $ip) {
                $v = str_contains($ip, ':') ? 'v6' : 'v4';
                $x .= '<domain:hostAddr ip="' . $v . '">' . self::e($ip) . '</domain:hostAddr>';
            }
            $x .= '</domain:hostAttr>';
        }
        return $x . '</domain:ns>';
    }

    private function dsXml(array $ds): string
    {
        $x = '';
        foreach ($ds as $d) {
            $x .= '<secDNS:dsData>'
                . '<secDNS:keyTag>' . (int)$d['keyTag'] . '</secDNS:keyTag>'
                . '<secDNS:alg>' . (int)$d['alg'] . '</secDNS:alg>'
                . '<secDNS:digestType>' . (int)$d['digestType'] . '</secDNS:digestType>'
                . '<secDNS:digest>' . self::e(strtoupper(preg_replace('/\s+/', '', $d['digest']))) . '</secDNS:digest>'
                . '</secDNS:dsData>';
        }
        return $x;
    }
}
