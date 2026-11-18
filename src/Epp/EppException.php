<?php
declare(strict_types=1);

namespace NicEpp\Epp;

class EppException extends \RuntimeException
{
    public ?Response $response = null;

    public static function fromResponse(string $msg, Response $r): self
    {
        $e = new self($msg . ' [' . $r->code() . ' ' . $r->msg() . ']');
        $e->response = $r;
        return $e;
    }
}
