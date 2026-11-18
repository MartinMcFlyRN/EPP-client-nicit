<?php
declare(strict_types=1);

namespace NicEpp\Epp;

/**
 * Wrapper della risposta EPP con helper XPath e parsing degli errori extepp del Registro .it.
 */
final class Response
{
    private \DOMDocument $dom;
    private \DOMXPath $xp;

    public function __construct(private string $raw)
    {
        $this->dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $this->dom->loadXML($raw, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            throw new EppException(\t('err.epp_bad_xml', ['raw' => mb_substr($raw, 0, 300)]));
        }
        $this->xp = new \DOMXPath($this->dom);
        foreach ([
            'epp' => Commands::NS_EPP, 'domain' => Commands::NS_DOMAIN, 'contact' => Commands::NS_CONTACT,
            'extepp' => Commands::NS_EXTEPP, 'extcon' => Commands::NS_EXTCON, 'extdom' => Commands::NS_EXTDOM,
            'rgp' => Commands::NS_RGP, 'secDNS' => Commands::NS_SECDNS, 'extsecDNS' => Commands::NS_EXTSECDNS,
        ] as $p => $ns) {
            $this->xp->registerNamespace($p, $ns);
        }
    }

    public function raw(): string
    {
        return $this->raw;
    }

    public function pretty(): string
    {
        $d = new \DOMDocument();
        $d->preserveWhiteSpace = false;
        $d->formatOutput = true;
        $d->loadXML($this->raw);
        return $d->saveXML();
    }

    public function isGreeting(): bool
    {
        return $this->xp->query('/epp:epp/epp:greeting')->length > 0;
    }

    public function code(): int
    {
        $n = $this->xp->query('/epp:epp/epp:response/epp:result/@code')->item(0);
        return $n ? (int)$n->nodeValue : 0;
    }

    public function msg(): string
    {
        return $this->str('/epp:epp/epp:response/epp:result/epp:msg');
    }

    public function ok(): bool
    {
        $c = $this->code();
        return $c >= 1000 && $c < 2000;
    }

    public function svTRID(): string
    {
        return $this->str('/epp:epp/epp:response/epp:trID/epp:svTRID');
    }

    public function clTRID(): string
    {
        return $this->str('/epp:epp/epp:response/epp:trID/epp:clTRID');
    }

    /** Errori dettagliati: extValue (value + reason) e reasonCode extepp. */
    public function reasons(): array
    {
        $out = [];
        foreach ($this->xp->query('/epp:epp/epp:response/epp:result/epp:extValue') as $ev) {
            $code = $this->str('.//extepp:reasonCode', $ev);
            $reason = $this->str('epp:reason', $ev);
            $wrong = [];
            foreach ($this->xp->query('.//extepp:wrongValue', $ev) as $wv) {
                $wrong[] = trim($this->str('extepp:element', $wv) . ' = ' . $this->str('extepp:value', $wv));
            }
            if ($code === '' && $reason === '' && !$wrong) {
                $v = $this->xp->query('epp:value', $ev)->item(0);
                $wrong[] = $v ? trim($v->textContent) : '';
            }
            $out[] = ['code' => $code, 'reason' => $reason, 'wrong' => implode('; ', array_filter($wrong))];
        }
        // Alcuni messaggi riportano reasonCode in extension (responseExtInfo)
        foreach ($this->xp->query('/epp:epp/epp:response/epp:extension//extepp:reasonCode') as $rc) {
            $out[] = ['code' => trim($rc->textContent), 'reason' => $this->str('following-sibling::*[1]', $rc), 'wrong' => ''];
        }
        return $out;
    }

    public function errorText(): string
    {
        $t = $this->code() . ' ' . $this->msg();
        foreach ($this->reasons() as $r) {
            $t .= ' — ' . trim(($r['code'] ? '[' . $r['code'] . '] ' : '') . $r['reason'] . ($r['wrong'] ? ' (' . $r['wrong'] . ')' : ''));
        }
        return $t;
    }

    // ------------------------------------------------------------------ coda messaggi

    public function msgQCount(): ?int
    {
        $n = $this->xp->query('/epp:epp/epp:response/epp:msgQ/@count')->item(0);
        return $n ? (int)$n->nodeValue : null;
    }

    public function msgQ(): ?array
    {
        $q = $this->xp->query('/epp:epp/epp:response/epp:msgQ')->item(0);
        if (!$q instanceof \DOMElement) {
            return null;
        }
        return [
            'id'    => $q->getAttribute('id'),
            'count' => (int)$q->getAttribute('count'),
            'qDate' => $this->str('epp:qDate', $q),
            'msg'   => $this->str('epp:msg', $q),
        ];
    }

    // ------------------------------------------------------------------ accesso generico

    public function xpath(string $q, ?\DOMNode $ctx = null): \DOMNodeList
    {
        return $ctx ? $this->xp->query($q, $ctx) : $this->xp->query($q);
    }

    public function str(string $q, ?\DOMNode $ctx = null): string
    {
        $n = $ctx ? $this->xp->query($q, $ctx) : $this->xp->query($q);
        return ($n && $n->length) ? trim($n->item(0)->textContent) : '';
    }

    /** XML formattato del nodo extension (utile per mostrare dati extdom/rgp/secDNS). */
    public function extensionXml(): string
    {
        $n = $this->xp->query('/epp:epp/epp:response/epp:extension')->item(0);
        if (!$n) {
            return '';
        }
        $d = new \DOMDocument();
        $d->formatOutput = true;
        $d->appendChild($d->importNode($n, true));
        return trim(preg_replace('/^<\?xml[^>]*>\s*/', '', $d->saveXML()));
    }

    // ------------------------------------------------------------------ parser specifici

    public function domainCheck(): array
    {
        $out = [];
        foreach ($this->xp->query('//domain:chkData/domain:cd') as $cd) {
            $nameEl = $this->xp->query('domain:name', $cd)->item(0);
            $out[] = [
                'name'   => trim($nameEl->textContent),
                'avail'  => in_array($nameEl->getAttribute('avail'), ['1', 'true'], true),
                'reason' => $this->str('domain:reason', $cd),
            ];
        }
        return $out;
    }

    public function contactCheck(): array
    {
        $out = [];
        foreach ($this->xp->query('//contact:chkData/contact:cd') as $cd) {
            $idEl = $this->xp->query('contact:id', $cd)->item(0);
            $out[] = [
                'id'     => trim($idEl->textContent),
                'avail'  => in_array($idEl->getAttribute('avail'), ['1', 'true'], true),
                'reason' => $this->str('contact:reason', $cd),
            ];
        }
        return $out;
    }

    public function domainInfo(): array
    {
        $b = '//domain:infData';
        $ns = [];
        foreach ($this->xp->query($b . '/domain:ns/domain:hostAttr') as $h) {
            $ips = [];
            foreach ($this->xp->query('domain:hostAddr', $h) as $a) {
                $ips[] = trim($a->textContent);
            }
            $ns[] = ['host' => $this->str('domain:hostName', $h), 'ips' => $ips];
        }
        $contacts = [];
        foreach ($this->xp->query($b . '/domain:contact') as $c) {
            /** @var \DOMElement $c */
            $contacts[] = ['type' => $c->getAttribute('type'), 'id' => trim($c->textContent)];
        }
        $status = [];
        foreach ($this->xp->query($b . '/domain:status') as $s) {
            /** @var \DOMElement $s */
            $status[] = $s->getAttribute('s');
        }
        $rgp = [];
        foreach ($this->xp->query('//rgp:infData/rgp:rgpStatus') as $s) {
            /** @var \DOMElement $s */
            $rgp[] = $s->getAttribute('s');
        }
        $ds = [];
        foreach ($this->xp->query('//secDNS:infData/secDNS:dsData') as $d) {
            $ds[] = [
                'keyTag' => (int)$this->str('secDNS:keyTag', $d),
                'alg' => (int)$this->str('secDNS:alg', $d),
                'digestType' => (int)$this->str('secDNS:digestType', $d),
                'digest' => $this->str('secDNS:digest', $d),
            ];
        }
        return [
            'name'       => $this->str($b . '/domain:name'),
            'roid'       => $this->str($b . '/domain:roid'),
            'status'     => $status,
            'rgp'        => $rgp,
            'registrant' => $this->str($b . '/domain:registrant'),
            'contacts'   => $contacts,
            'ns'         => $ns,
            'clID'       => $this->str($b . '/domain:clID'),
            'crID'       => $this->str($b . '/domain:crID'),
            'crDate'     => $this->str($b . '/domain:crDate'),
            'upDate'     => $this->str($b . '/domain:upDate'),
            'exDate'     => $this->str($b . '/domain:exDate'),
            'trDate'     => $this->str($b . '/domain:trDate'),
            'authInfo'   => $this->str($b . '/domain:authInfo/domain:pw'),
            'ds'         => $ds,
            'extension'  => $this->extensionXml(),
        ];
    }

    public function contactInfo(): array
    {
        $b = '//contact:infData';
        $pi = $this->xp->query($b . '/contact:postalInfo')->item(0);
        $street = [];
        if ($pi) {
            foreach ($this->xp->query('contact:addr/contact:street', $pi) as $s) {
                $street[] = trim($s->textContent);
            }
        }
        $status = [];
        foreach ($this->xp->query($b . '/contact:status') as $s) {
            /** @var \DOMElement $s */
            $status[] = $s->getAttribute('s');
        }
        $reg = null;
        if ($this->xp->query('//extcon:infData/extcon:registrant')->length) {
            $reg = [
                'nationality' => $this->str('//extcon:infData/extcon:registrant/extcon:nationalityCode'),
                'entityType'  => (int)$this->str('//extcon:infData/extcon:registrant/extcon:entityType'),
                'regCode'     => $this->str('//extcon:infData/extcon:registrant/extcon:regCode'),
            ];
        }
        return [
            'id'       => $this->str($b . '/contact:id'),
            'roid'     => $this->str($b . '/contact:roid'),
            'status'   => $status,
            'name'     => $pi ? $this->str('contact:name', $pi) : '',
            'org'      => $pi ? $this->str('contact:org', $pi) : '',
            'street'   => $street,
            'city'     => $pi ? $this->str('contact:addr/contact:city', $pi) : '',
            'sp'       => $pi ? $this->str('contact:addr/contact:sp', $pi) : '',
            'pc'       => $pi ? $this->str('contact:addr/contact:pc', $pi) : '',
            'cc'       => $pi ? $this->str('contact:addr/contact:cc', $pi) : '',
            'voice'    => $this->str($b . '/contact:voice'),
            'fax'      => $this->str($b . '/contact:fax'),
            'email'    => $this->str($b . '/contact:email'),
            'clID'     => $this->str($b . '/contact:clID'),
            'crDate'   => $this->str($b . '/contact:crDate'),
            'upDate'   => $this->str($b . '/contact:upDate'),
            'consent'  => in_array($this->str('//extcon:infData/extcon:consentForPublishing'), ['true', '1'], true),
            'registrant' => $reg,
            'extension'  => $this->extensionXml(),
        ];
    }

    public function transferData(): array
    {
        $b = '//domain:trnData';
        return [
            'name'     => $this->str($b . '/domain:name'),
            'trStatus' => $this->str($b . '/domain:trStatus'),
            'reID'     => $this->str($b . '/domain:reID'),
            'reDate'   => $this->str($b . '/domain:reDate'),
            'acID'     => $this->str($b . '/domain:acID'),
            'acDate'   => $this->str($b . '/domain:acDate'),
            'exDate'   => $this->str($b . '/domain:exDate'),
        ];
    }

    public function createData(): array
    {
        return [
            'name'   => $this->str('//domain:creData/domain:name'),
            'crDate' => $this->str('//domain:creData/domain:crDate'),
            'exDate' => $this->str('//domain:creData/domain:exDate'),
        ];
    }
}
