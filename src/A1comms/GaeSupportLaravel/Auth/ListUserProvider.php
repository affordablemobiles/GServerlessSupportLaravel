<?php

namespace A1comms\GaeSupportLaravel\Auth;

use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Auth\Authenticatable;

class ListUserProvider extends NullUserProvider
{
    /**
     * The user list.
     *
     * @var string
     */
    protected $list;

    /**
     * Create a new null user provider.
     *
     * @param  string  $model
     * @return void
     */
    public function __construct($model, $list = [])
    {
        $this->model = $model;
        $this->list = $list;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \A1comms\GaeSupportLaravel\Auth\Contracts\NullUserModel|null
     */
    public function retrieveById($identifier)
    {
        if (in_array($identifier, $this->list)) {
            $user = $this->createModel();

            Log::info('Auth@ListUserProvider: Allowing access for user: ' . $identifier);
            
            return $user->fill([
                $user->getAuthIdentifierName() => $identifier,
            ]);
        } else {
            Log::error('Auth@ListUserProvider: Denying access to user: ' . $identifier);
        }

        return null;
    }
}
