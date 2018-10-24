<?php

namespace A1comms\GaeSupportLaravel\Integration\TaskQueue;

use Google\Cloud\Core\Compute\Metadata;
use Google\Cloud\Tasks\V2beta2\CloudTasksClient;

class Client
{
    private $client;
    private $project;
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
        $this->project = gae_project();
        $this->location = $this->fetchLocation();
    }

    public function getQueueName($queue) {
        return $this->client->queueName($this->project, $this->location, $queue);
    }

    public function getLocation() {
        return $this->location;
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