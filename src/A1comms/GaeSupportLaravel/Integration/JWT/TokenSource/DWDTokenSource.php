<?php

namespace A1comms\GaeSupportLaravel\Integration\JWT\TokenSource;

use Google\Auth\OAuth2;
use Google\Auth\Credentials\GCECredentials;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Builder;
use A1comms\GaeSupportLaravel\Integration\JWT\Signer\IAMSigner;

class DWDTokenSource extends OAuth2
{
    private $subject;

    private $scopes;

    public function __construct($subject, $scopes = [])
    {
        $this->subject = $subject;

        if (!is_array($scopes)){
            throw new Exception("Invalid scopes: must be an array");
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

        $time = time();

        $signer = new IAMSigner();

        $keyID = new Key($client_email);

        $token = (new Builder())
            ->issuedBy($client_email)                           // Configures the issuer (iss claim)
            ->permittedFor($this->getTokenCredentialUri())      // aud claim
            ->relatedTo($this->subject)                         // sub claim
            ->issuedAt($time)                                   // Configures the time that the token was issue (iat claim)
            ->expiresAt($time + 3600)                           // Configures the expiration time of the token (exp claim)
            ->withClaim('scope', implode(' ', $this->scopes))   // scopes claim
            ->getToken($signer, $keyID);

        return (string)$token;
    }
}