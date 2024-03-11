<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Model;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\NullUserModel;

class FirebaseUser implements NullUserModel
{
    /**
     * User's ID.
     *
     * @var string
     */
    public $sub;

    /**
     * User's Name.
     *
     * @var string
     */
    public $name;

    /**
     * User's Picture (URL to thumbnail).
     *
     * @var string
     */
    public $picture;

    /**
     * User's Email.
     *
     * @var string
     */
    public $email;

    /**
     * Is User's Email Verified?
     *
     * @var bool
     */
    public $email_verified;

    /**
     * Firebase Attributes.
     *
     * Example:
     *
     *  {
     *      "identities": {
     *          "google.com": [
     *              "115628285719939502680"
     *          ],
     *          "email": [
     *              "sam.melrose@a1comms.com"
     *          ]
     *      },
     *      "sign_in_provider": "google.com"
     *  }
     *
     * @var array
     */
    public $firebase;

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
        return 'sub';
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->sub;
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
