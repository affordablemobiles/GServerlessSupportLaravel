<?php

namespace A1comms\GaeSupportLaravel\Integration\Datastore;

use GDS;

class DatastoreFactory
{
    public static function make()
    {
        return new GDS\Gateway\RESTv1(gae_project(), null);
    }
}
