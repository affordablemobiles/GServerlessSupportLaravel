<?php

namespace A1comms\GaeSupportLaravel\Datastore;

use GDS;

class DatastoreFactory
{
    public static function make()
    {
        if ( is_gae_std() )
        {
            return new GDS\Gateway\ProtoBuf(null, null);
        }
        else if ( is_gae_flex() )
        {
            return new \GDS\Gateway\RESTv1(gae_project(), null);
        }

        return false;
    }
}
