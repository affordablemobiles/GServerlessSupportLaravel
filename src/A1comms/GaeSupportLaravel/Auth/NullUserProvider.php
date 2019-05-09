<?php

namespace A1comms\GaeSupportLaravel\Auth;

use Illuminate\Contracts\Auth\UserProvider;
use A1comms\GaeSupportLaravel\Auth\Contracts\NullUserModel;

class NullUserProvider implements UserProvider
{
    /**
     * The user model.
     *
     * @var string
     */
    protected $model;

    /**
     * Create a new null user provider.
     *
     * @param  string  $model
     * @return void
     */
    public function __construct($model)
    {
        $this->model = $model;
    }

    /**
     * Create a new instance of the model.
     *
     * @return object
     */
    public function createModel()
    {
        $class = '\\'.ltrim($this->model, '\\');

        return new $class;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \A1comms\GaeSupportLaravel\Auth\Contracts\NullUserModel|null
     */
    public function retrieveById($identifier)
    {
        $user = $this->createModel();

        return $user->fill([
            $user->getAuthIdentifierName() => $identifier,
        ]);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed   $identifier
     * @param  string  $token
     * @return \A1comms\GaeSupportLaravel\Auth\Contracts\NullUserModel|null
     */
    public function retrieveByToken($identifier, $token)
    {
        return null;
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \A1comms\GaeSupportLaravel\Auth\Contracts\NullUserModel  $user
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(NullUserModel $user, $token)
    {

    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return \A1comms\GaeSupportLaravel\Auth\Contracts\NullUserModel|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        return null
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \A1comms\GaeSupportLaravel\Auth\Contracts\NullUserModel  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(NullUserModel $user, array $credentials)
    {
        return false;
    }
}