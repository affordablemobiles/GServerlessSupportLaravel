<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Integration\Google;

use Google\Auth\OAuth2 as BaseOAuth2;

/**
 * OAuth2 supports authentication by OAuth2 2-legged flows.
 *
 * It primary supports
 * - service account authorization
 * - authorization where a user already has an access token
 */
class OAuth2 extends BaseOAuth2
{
    /**
     * Obtains the encoded jwt from the instance data.
     *
     * @param array<mixed> $config array optional configuration parameters
     *
     * @return string
     */
    public function toJwt(array $config = [])
    {
        if (null === $this->getSigningKey()) {
            throw new \DomainException('No signing key available');
        }
        if (null === $this->getSigningAlgorithm()) {
            throw new \DomainException('No signing algorithm specified');
        }
        $now = time();

        $opts = array_merge([
            'skew' => self::DEFAULT_SKEW_SECONDS,
        ], $config);

        $assertion = [
            'iss' => $this->getIssuer(),
            'exp' => ($now + $this->getExpiry()),
            'iat' => ($now - $opts['skew']),
        ];
        foreach ($assertion as $k => $v) {
            if (null === $v) {
                throw new \DomainException($k.' should not be null');
            }
        }
        if (null !== $this->getAudience()) {
            $assertion['aud'] = $this->getAudience();
        }

        if (null !== $this->getScope()) {
            $assertion['scope'] = $this->getScope();
        }

        if (empty($assertion['scope']) && empty($assertion['aud'])) {
            throw new \DomainException('one of scope or aud should not be null');
        }

        if (null !== $this->getSub()) {
            $assertion['sub'] = $this->getSub();
        }
        $assertion += $this->getAdditionalClaims();

        return JWT::encode(
            $assertion,
            $this->getSigningKey(),
            $this->getSigningAlgorithm(),
            $this->getSigningKeyId()
        );
    }
}
