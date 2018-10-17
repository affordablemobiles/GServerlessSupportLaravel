<?php

namespace A1comms\GaeSupportLaravel\Interface\Tasks;

use Google\Cloud\Tasks\V2beta2\CloudTasksClient;

class Client {
    private $client;

    private static $myInstance = null;

    public static function instance() {
        if (is_null(self::$myInstance)) {
            self::$myInstance = new Client();
        }

        return self::$myInstance;
    }

    public function __construct() {
        $this->client = new CloudTasksClient();
    }
}