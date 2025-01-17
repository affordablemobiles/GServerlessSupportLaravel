<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\TaskQueue;

use Google\Cloud\Tasks\V2\CloudTasksClient;

class Client
{
    private $client;
    private $project;
    private $location;

    private static $myInstance;

    public function __construct()
    {
        $this->client   = new CloudTasksClient();
        $this->project  = g_project();
        $this->location = $this->fetchLocation();
    }

    public static function instance()
    {
        if (null === self::$myInstance) {
            self::$myInstance = new self();
        }

        return self::$myInstance;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function getQueueName($queue)
    {
        return $this->client->queueName($this->project, $this->location, $queue);
    }

    public function getLocation()
    {
        return $this->location;
    }

    private function fetchLocation()
    {
        $region = value(
            config('gserverlesssupport.cloud-tasks.region'),
        );

        if (!empty($region)) {
            return $region;
        }

        throw new \Exception('Cloud Tasks Region Must Be Specified');
    }
}
