<?php

namespace A1comms\GaeSupportLaravel\Integration\Datastore;

use GDS;

class DatastoreFactory
{
    public static function make($namespace = null, $transport = 'grpc')
    {
        switch ($transport) {
            case 'grpc':
                return new GDS\Gateway\GRPCv1(gae_project(), $namespace);
                break;
            case 'rest':
                return new GDS\Gateway\RESTv1(gae_project(), $namespace);
                break;
            default:
                throw new \Exception("Invalid Datastore Transport: " . $transport);
                break;
        }
    }
}
