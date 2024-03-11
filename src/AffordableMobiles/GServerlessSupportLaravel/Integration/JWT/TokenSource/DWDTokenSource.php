<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\JWT\TokenSource;

use AffordableMobiles\GServerlessSupportLaravel\Integration\JWT\Signer\IAMSigner;
use DateTimeImmutable;
use Google\Auth\Credentials\GCECredentials;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\OAuth2;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;

class DWDTokenSource extends OAuth2
{
    private $subject;

    private $scopes;

    public function __construct($subject, $scopes = [])
    {
        $this->subject = $subject;

        if (!\is_array($scopes)) {
            throw new Exception('Invalid scopes: must be an array');
        }
        $this->scopes = $scopes;
    }

    public function getGrantType()
    {
        return static::JWT_URN;
    }

    public function getTokenCredentialUri()
    {
        return ServiceAccountCredentials::TOKEN_CREDENTIAL_URI;
    }

    public function toJwt(array $config = [])
    {
        $gce_creds = new GCECredentials();
        $client_email = $gce_creds->getClientName();

        $config = Configuration::forSymmetricSigner(
            new IAMSigner(),
            InMemory::plainText($client_email)
        );

        $now = new DateTimeImmutable();

        $token = $config->builder()
            ->issuedBy($client_email)
            ->permittedFor($this->getTokenCredentialUri())
            ->relatedTo($this->subject)
            ->issuedAt($now)
            ->expiresAt($now->modify('+1 hour'))
            ->withClaim('scope', implode(' ', $this->scopes))
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }
}
