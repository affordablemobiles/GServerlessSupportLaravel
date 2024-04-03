<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\TaskQueue;

use Google\Cloud\Tasks\V2\AppEngineHttpRequest;
use Google\Cloud\Tasks\V2\AppEngineRouting;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\HttpRequest;
use Google\Cloud\Tasks\V2\OidcToken;
use Google\Cloud\Tasks\V2\Task;
use Google\Protobuf;
use Illuminate\Support\Facades\URL;

class PushTask
{
    private $task;
    private $pushTask;

    public function __construct($url_path, $query_data = [], $options = [])
    {
        if (is_cloud_run() && empty($options['target'])) {
            $this->pushTask = new HttpRequest();

            $this->pushTask->setUrl(URL::to($url_path));

            $token = new OidcToken();
            $token->setServiceAccountEmail(config('gserverlesssupport.cloud-tasks.service-account'));
            $token->setAudience(config('gserverlesssupport.cloud-tasks.audience'));

            $this->pushTask->setOidcToken($token);
        } else {
            $this->pushTask = new AppEngineHttpRequest();

            $this->pushTask->setRelativeUri($url_path);

            if (!empty($options['target'])) {
                $routing = new AppEngineRouting();

                if (!empty($options['target']['service'])) {
                    $routing->setService($options['target']['service']);
                }

                if (!empty($options['target']['version'])) {
                    $routing->setVersion($options['target']['version']);
                }

                $this->pushTask->setAppEngineRouting($routing);
            } elseif ('default' !== g_service()) {
                $routing = (new AppEngineRouting())
                    ->setService(g_service())
                ;

                $this->pushTask->setAppEngineRouting($routing);
            }
        }

        $this->pushTask->setBody(http_build_query($query_data));
        $this->pushTask->setHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);

        if (!empty($options['method'])) {
            $this->pushTask->setHttpMethod(
                HttpMethod::value($options['method'])
            );
        }

        $this->task = new Task();
        if (is_cloud_run() && empty($options['target'])) {
            $this->task->setHttpRequest($this->pushTask);
        } else {
            $this->task->setAppEngineHttpRequest($this->pushTask);
        }

        if (!empty($options['delay_seconds'])) {
            $secondsInterval = new \DateInterval('PT'.$options['delay_seconds'].'S');
            $futureTime      = (new \DateTime())->add($secondsInterval);
            $timestamp       = new Protobuf\Timestamp();
            $timestamp->fromDateTime($futureTime);
            $this->task->setScheduleTime($timestamp);
        }
    }

    public function getTask()
    {
        return $this->task;
    }

    public function add($queue_name = 'default')
    {
        $queue = new PushQueue($queue_name);

        return $queue->addTasks([$this])[0];
    }

    public static function parseTaskName(Task $task)
    {
        // In Format: `projects/PROJECT_ID/locations/LOCATION_ID/queues/QUEUE_ID/tasks/TASK_ID`
        $taskName = $task->getName();

        $taskDetails = explode('/', $taskName);

        return [
            'project_id'  => $taskDetails[1],
            'location_id' => $taskDetails[3],
            'queue_id'    => $taskDetails[5],
            'task_id'     => $taskDetails[7],
        ];
    }
}
