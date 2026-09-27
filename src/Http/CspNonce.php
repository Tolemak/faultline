<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Contracts\Service\ResetInterface;

final class CspNonce implements ResetInterface
{
    private ?string $nonce = null;

    public function get(): string
    {
        return $this->nonce ??= base64_encode(random_bytes(18));
    }

    public function used(): bool
    {
        return null !== $this->nonce;
    }

    public function reset(): void
    {
        $this->nonce = null;
    }
}
