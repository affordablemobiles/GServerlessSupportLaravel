<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Token\Type;

use Jose\Component\KeyManagement\JWKFactory;
use SimpleJWT\Keys\KeyFactory;
use SimpleJWT\Keys\KeySet;

class JWT_x509 extends JWT
{
    /**
     * Fetches a KeySet instance for the public JWKs.
     *
     * @param string $jwk_url URL of the JWK public key file
     *
     * @return KeySet
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
                            JWKFactory::createFromCertificate($key)->all()
                        )
                    ),
                    'auto'
                )
            );
        }

        return $jwkset;
    }
}
