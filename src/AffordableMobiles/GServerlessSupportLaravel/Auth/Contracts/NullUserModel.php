<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface NullUserModel extends Authenticatable
{
    /**
     * Fill the model with an array of attributes.
     *
     * @return $this
     */
    public function fill(array $attributes);
}
