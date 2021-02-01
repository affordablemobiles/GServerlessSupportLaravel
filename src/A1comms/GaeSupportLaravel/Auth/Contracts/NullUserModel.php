<?php

namespace A1comms\GaeSupportLaravel\Auth\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface NullUserModel extends Authenticatable
{
    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     * @return $this
     */
    public function fill(array $attributes);
}
