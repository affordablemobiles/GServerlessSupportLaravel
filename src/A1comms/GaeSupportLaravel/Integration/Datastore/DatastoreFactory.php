<?php

namespace A1comms\GaeSupportLaravel\Integration\Datastore;

use GDS;

class DatastoreFactory
{
    public static function make()
    {
        if (GAE_LEGACY) {
            return new GDS\Gateway\ProtoBuf(null, null);
        }

        return new GDS\Gateway\RESTv1(gae_project(), null);
    }
}
