<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Auth\Token\Type;

use SimpleJWT\Keys\KeyFactory;
use SimpleJWT\Keys\KeySet;

class JWT_x509 extends JWT
{
    /**
     * Fetches a KeySet instance for the public JWKs.
     *
     * Note: due to the way tokens are presented at the URL,
     *       this package requires "web-token/jwt-framework"
     *       to convert the x509 certificates to JWKs:
     *
     *       composer require web-token/jwt-framework
     *
     * @param string $jwk_url URL of the JWK public key file
     *
     * @return \SimpleJWT\Keys\KeySet
     */
    protected static function get_jwk_set($jwk_url)
    {
        // Create a JWK Key Set from the gstatic URL
        $jwkset = new KeySet();

        $keys = json_decode(self::get_jwk_set_raw($jwk_url), true);

        foreach ($keys as $id => $key) {
            $jwkset->add(
                KeyFactory::create(
                    json_encode(
                        array_merge(
                            ['kid' => $id],
                            \Jose\Component\KeyManagement\JWKFactory::createFromCertificate($key)->all()
                        )
                    ),
                    'auto'
                )
            );
        }

        return $jwkset;
    }
}
