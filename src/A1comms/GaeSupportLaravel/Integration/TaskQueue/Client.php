<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Integration\TaskQueue;

use Google\Cloud\Core\Compute\Metadata;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Illuminate\Support\Str;

class Client
{
    private $client;
    private $project;
    private $location;

    private static $myInstance;

    public function __construct()
    {
        $this->client   = new CloudTasksClient();
        $this->project  = gae_project();
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
            config('gaesupport.cloud-tasks.region', config('gserverlesssupport.cloud-tasks.region')),
        );

        if (!empty($region)) {
            return $region;
        }

        try {
            $metadata = new Metadata();

            try {
                $region = explode(
                    '/',
                    $metadata->get('instance/region')
                );
                $region = array_pop($region);

                if (!empty($region)) {
                    return $region;
                }
            } catch (\Exception $e) {
                // Ignore and fall back to instance/zone
            }

            $zone = explode(
                '/',
                $metadata->get('instance/zone')
            );
            $zone = array_pop($zone);

            // if the pattern is similar to europe-west1-a (i.e., GAE Flexible Environment)
            if (Str::is('*-*-*', $zone)) {
                return Str::beforeLast($zone, '-');
            }

            switch ($zone) {
                case 'eu1':
                case 'eu2':
                case 'eu3':
                case 'eu4':
                case 'eu5':
                case 'eu6':
                    return 'europe-west1';

                case 'us6':
                case 'us14':
                    return 'us-central1';

                default:
                    throw new \Exception('Unknown App Engine Region Code: '.$zone);
            }
        } catch (\Exception $e) {
            if (Str::startsWith($e->getMessage(), 'Unknown App Engine Region Code')) {
                throw $e;
            }
        }

        throw new \Exception('Cloud Tasks Region Must Be Specified');
    }
}
