<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Integration\Google;

use Firebase\JWT\JWT as BaseJWT;

class JWT extends BaseJWT
{
    /**
     * Sign a string, supporting a callable signer callback.
     *
     * @param string                                                           $msg The message to sign
     * @param callable|OpenSSLAsymmetricKey|OpenSSLCertificate|resource|string $key the secret key
     * @param string                                                           $alg Supported algorithms are 'ES384','ES256', 'HS256', 'HS384',
     *                                                                              'HS512', 'RS256', 'RS384', and 'RS512'
     *
     * @return string An encrypted message
     *
     * @throws DomainException Unsupported algorithm or bad key was specified
     */
    public static function sign(
        string $msg,
        $key,
        string $alg
    ): string {
        if (\is_callable($key)) {
            return \call_user_func_array($key, [$alg, $msg]);
        }

        return parent::sign($msg, $key, $alg);
    }
}
