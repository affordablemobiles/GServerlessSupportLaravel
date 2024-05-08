<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Model;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\NullUserModel;

class IAPUser implements NullUserModel
{
    /**
     * User's Email.
     *
     * @var string
     */
    protected $email;

    /**
     * Fill the model with an array of attributes.
     *
     * @return $this
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $k => $v) {
            $this->{$k} = $v;
        }

        return $this;
    }

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return 'email';
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->email;
    }

    /**
     * Get the name of the password attribute for the user.
     *
     * @return string
     */
    public function getAuthPasswordName()
    {
        return 'password';
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return '';
    }

    /**
     * Get the token value for the "remember me" session.
     *
     * @return string
     */
    public function getRememberToken()
    {
        return '';
    }

    /**
     * Set the token value for the "remember me" session.
     *
     * @param string $value
     */
    public function setRememberToken($value)
    {
        return '';
    }

    /**
     * Get the column name for the "remember me" token.
     *
     * @return string
     */
    public function getRememberTokenName()
    {
        return 'none';
    }
}
