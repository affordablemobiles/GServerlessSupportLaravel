<?php

namespace A1comms\GaeSupportLaravel\Interface\TaskQueue;

use Google\Cloud\Core\Compute\Metadata;
use Google\Cloud\Tasks\V2beta2\CloudTasksClient;

class Client
{
    private $client;
    private $location;

    private static $myInstance = null;

    public static function instance() {
        if (is_null(self::$myInstance)) {
            self::$myInstance = new Client();
        }

        return self::$myInstance;
    }

    public function __construct() {
        $this->client = new CloudTasksClient();
        $this->location = $this->fetchLocation();
    }

    private function fetchLocation() {
        $metadata = new Metadata();
        $zone = $metadata->get('instance/zone');
        $zone = explode("/", $zone);
        $zone = array_pop($zone);

        $region = explode("-", $zone);
        array_pop($region);
        $region = implode("-", $region);

        return $region;
    }
}