<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Auth;

use A1comms\GaeSupportLaravel\Integration\ErrorReporting\Report as ErrorBootstrap;
use A1comms\GaeSupportLaravel\Integration\Google\Credentials\GCEDWDCredentials;
use Google\Client;
use Google\Service\Directory;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class GroupUserProvider extends NullUserProvider
{
    /**
     * The user group.
     *
     * @var string
     */
    protected $group;

    /**
     * Create a new group user provider.
     *
     * @param string $model
     * @param array  $list
     */
    public function __construct($model, string $group)
    {
        $this->model = $model;

        if (empty($group)) {
            throw new InvalidArgumentException('group must be defined');
        }

        $this->group = $group;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param string $identifier
     *
     * @return null|\A1comms\GaeSupportLaravel\Auth\Contracts\NullUserModel
     */
    public function retrieveById($identifier)
    {
        if ($this->isGroupMember($identifier)) {
            $user = $this->createModel();

            Log::info('Auth@GroupUserProvider: Allowing access for user: '.$identifier);

            return $user->fill([
                $user->getAuthIdentifierName() => $identifier,
            ]);
        }
        Log::error('Auth@GroupUserProvider: Denying access to user: '.$identifier);

        return null;
    }

    /**
     * Validate group membership.
     *
     * @return bool
     */
    protected function isGroupMember(string $identifier)
    {
        try {
            $client = new Client([
                'credentials' => (new GCEDWDCredentials(
                    scope: Directory::ADMIN_DIRECTORY_GROUP_MEMBER_READONLY,
                ))->setSubject(env('GSUITE_ADMIN_IMPERSONATE')),
            ]);

            return (new Directory($client))->members->hasMember($this->group, $identifier)->getIsMember();
        } catch (\Throwable $ex) {
            if (!str_contains((string) $ex, 'Invalid Input')) {
                ErrorBootstrap::exceptionHandler($ex);
            }

            return false;
        }
    }
}
