<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Auth;

use Illuminate\Support\Facades\Log;

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
     * @param string $model
     * @param mixed  $list
     */
    public function __construct($model, $list = [])
    {
        $this->model = $model;
        $this->list  = $list;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param mixed $identifier
     *
     * @return null|\A1comms\GaeSupportLaravel\Auth\Contracts\NullUserModel
     */
    public function retrieveById($identifier)
    {
        if (\in_array($identifier, $this->list, true)) {
            $user = $this->createModel();

            Log::info('Auth@ListUserProvider: Allowing access for user: '.$identifier);

            return $user->fill([
                $user->getAuthIdentifierName() => $identifier,
            ]);
        }
        Log::error('Auth@ListUserProvider: Denying access to user: '.$identifier);

        return null;
    }
}
