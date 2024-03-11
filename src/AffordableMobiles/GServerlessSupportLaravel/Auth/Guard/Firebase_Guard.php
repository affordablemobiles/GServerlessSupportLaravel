<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Guard;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\Guard\StatelessValidator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\InvalidTokenException;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Model\FirebaseUser;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\Firebase;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Firebase_Guard implements StatelessValidator
{
    /**
     * Authenticate a user based on request information,
     * return a valid user object if successful, or null.
     *
     * @return null|\Illuminate\Contracts\Auth\Authenticatable
     */
    public static function validate(Request $request, UserProvider $provider = null)
    {
        $expected_audience = env('FIREBASE_PROJECT');
        if (empty($expected_audience)) {
            throw new \Exception('Firebase Authentication Guard: Audience (env FIREBASE_PROJECT) not defined');
        }

        $jwt = $request->cookie(config('gaesupport.auth.firebase.cookie_name'));
        if (empty($jwt)) {
            return null;
        }

        try {
            $return = Firebase::validateToken($jwt, $expected_audience);
        } catch (InvalidTokenException $e) {
            Log::warning('Firebase Authentication Guard: '.$e->getMessage());

            return null;
        }

        return static::returnUser($provider, $return);
    }

    protected static function returnUser(UserProvider $provider = null, array $data)
    {
        if (empty($provider)) {
            $user = new FirebaseUser();

            $user->fill($data);

            return $user;
        }

        return $provider->retrieveById($id);
    }
}
