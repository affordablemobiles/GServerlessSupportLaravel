<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Auth\Guard\Combined;

use A1comms\GaeSupportLaravel\Auth\Contracts\Guard\StatelessValidator;
use A1comms\GaeSupportLaravel\Auth\Guard\AppEngine_Guard;
use A1comms\GaeSupportLaravel\Auth\Guard\IAP_Guard as IAP_Guard_Base;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

class IAP_Guard implements StatelessValidator
{
    /**
     * Authenticate a user based on request information,
     * return a valid user object if successful, or null.
     *
     * @return null|\Illuminate\Contracts\Auth\Authenticatable
     */
    public static function validate(Request $request, UserProvider $provider = null)
    {
        $result = AppEngine_Guard::validate($request, $provider);
        if (!empty($result)) {
            return $result;
        }

        $result = IAP_Guard_Base::validate($request, $provider);
        if (!empty($result)) {
            return $result;
        }

        return null;
    }
}
