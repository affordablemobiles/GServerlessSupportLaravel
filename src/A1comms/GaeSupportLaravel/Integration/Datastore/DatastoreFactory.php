<?php

namespace A1comms\GaeSupportLaravel\Integration\Datastore;

use GDS;

class DatastoreFactory
{
    public static function make($namespace = null)
    {
        return new GDS\Gateway\GRPCv1(gae_project(), $namespace);
    }
}
