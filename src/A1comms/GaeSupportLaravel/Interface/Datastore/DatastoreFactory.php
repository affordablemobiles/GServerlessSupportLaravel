<?php

namespace A1comms\GaeSupportLaravel\Interface\Datastore;

use GDS;

class DatastoreFactory
{
    public static function make()
    {
        if ( is_gae() ) {
            return new GDS\Gateway\RESTv1(gae_project(), null);
        }

        return false;
    }
}
