<?php

namespace A1comms\GaeSupportLaravel\Auth\Guard;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\UserProvider;

class OAuth2_Guard extends BaseGuard
{
    /**
     * The URI of the token info endpoint.
     */
    const TOKENINFO_ENDPOINT_URI = 'https://oauth2.googleapis.com/tokeninfo';

    /**
     * Authenticate a user based on request information,
     * return a valid user object if successful, or null.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public static function validate(Request $request, UserProvider $provider = null)
    {
        $token = $request->bearerToken();
        if (empty($token)) {
            return null;
        }
        
        try {
            $return = self::validate_access_token($token);
        } catch (Exception $e) {
            Log::warning('OAuth2 Authentication Guard: ' . $e->getMessage());
            
            return null;
        }

        return static::returnUser($provider, $return['email']);
    }

    protected static function validate_access_token($token)
    {
        $response = null;
        
        try {
            $clientParams = [
                'allow_redirects'   => false,
                'timeout'           => 2
            ];

            // create the HTTP client
            $client = new Client($clientParams);

            // make the request
            $response = $client->get(
                self::TOKENINFO_ENDPOINT_URI . '?access_token=' . $token
            );

            $response = json_decode($response->getBody(), true);
        } catch (Exception $e) {
            throw new Exception("Access Token Validation Exception: Remote Token Check Failed");
        }

        return $response;
    }
}