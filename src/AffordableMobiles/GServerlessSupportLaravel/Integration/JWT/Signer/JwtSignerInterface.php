<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\JWT\Signer;

interface JwtSignerInterface
{
    /**
     * Algorithm identifier (e.g. 'RS256').
     */
    public function getAlgorithm(): string;

    /**
     * Optional Key ID to include in JWT header ('kid').
     */
    public function getKid(): ?string;

    /**
     * Sign the raw header.payload signing input and return raw binary signature.
     */
    public function sign(string $signingInput): string;
}
