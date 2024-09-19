<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Auth;

use A1comms\GaeSupportLaravel\Auth\Contracts\NullUserModel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

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
     * @param string $model
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

        return new $class();
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param string $identifier
     *
     * @return null|NullUserModel
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
     * @param string $identifier
     * @param string $token
     *
     * @return null|NullUserModel
     */
    public function retrieveByToken($identifier, $token)
    {
        return null;
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param NullUserModel $user
     * @param string        $token
     */
    public function updateRememberToken(Authenticatable $user, $token): void {}

    /**
     * Retrieve a user by the given credentials.
     *
     * @return null|NullUserModel
     */
    public function retrieveByCredentials(array $credentials)
    {
        return null;
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param NullUserModel $user
     *
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        return false;
    }
}
