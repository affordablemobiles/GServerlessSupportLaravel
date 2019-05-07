<?php

namespace A1comms\GaeSupportLaravel\Auth\Model;

use Illuminate\Contracts\Auth\Authenticatable;

class IAPUser implements Authenticatable
{
    /**
     * User's Email
     *
     * @var string
     */
    protected $email;

    /**
     * Create instance of IAPUser
     *
     * @param   string  $email
     * @return  void
     */
    public function __construct($email)
    {
        $this->email = $email;
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
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return "";
    }

    /**
     * Get the token value for the "remember me" session.
     *
     * @return string
     */
    public function getRememberToken()
    {
        return "";
    }

    /**
     * Set the token value for the "remember me" session.
     *
     * @param  string  $value
     * @return void
     */
    public function setRememberToken($value)
    {
        return "";
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