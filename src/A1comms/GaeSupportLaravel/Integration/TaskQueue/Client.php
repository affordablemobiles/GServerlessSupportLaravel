<?php

namespace A1comms\GaeSupportLaravel\Integration\TaskQueue;

use Exception;
use Google\Cloud\Core\Compute\Metadata;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Illuminate\Support\Str;

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

    public function getClient() {
        return $this->client;
    }

    public function getQueueName($queue) {
        return $this->client->queueName($this->project, $this->location, $queue);
    }

    public function getLocation() {
        return $this->location;
    }

    private function fetchLocation() {
        $metadata = new Metadata();
        $zone = explode(
            "/",
            $metadata->get('instance/zone')
        );
        $zone = array_pop($zone);

        // if the pattern is similar to europe-west1-a (i.e., GAE Flexible Environment)
        if (Str::is('*-*-*', $zone)) {
            return Str::beforeLast($zone, '-');
        }

        switch ($zone) {
            case "eu2":
            case "eu5":
            case "eu6":
                return "europe-west1";
            case "us6":
            case "us14":
                return "us-central1";
            default:
                throw new Exception("Unknown App Engine Region Code: " . $zone);
        }
    }
}
