<?php

namespace App\Services\C2;

use RuntimeException;

final class C2Exception extends RuntimeException
{
    public function __construct(string $message, public readonly int $status, public readonly string $errorCode)
    {
        parent::__construct($message, $status);
    }

    /** Falhas passageiras: a próxima rodada tenta de novo. */
    public function isTemporary(): bool
    {
        return in_array($this->status, [0, 408, 429, 500, 502, 503, 504], true);
    }
}
