<?php

namespace A1comms\GaeSupportLaravel\Integration\Datastore;

use GDS;

class DatastoreFactory
{
    public static function make()
    {
        if (GAE_LEGACY) {
            return new GDS\Gateway\ProtoBuf(null, $namespace);
        }

        return new GDS\Gateway\GRPCv1(gae_project(), $namespace);
    }
}
