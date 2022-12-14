<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Integration\TaskQueue;

use Google\Cloud\Tasks\V2\Task;

class PushQueue
{
    private $name;
    private $full_name;

    public function __construct($name = 'default')
    {
        $this->name      = $name;
        $this->full_name = Client::instance()->getQueueName($name);
    }

    public function getName()
    {
        return $this->name;
    }

    public function addTasks($tasks)
    {
        if (!\is_array($tasks)) {
            throw new \InvalidArgumentException('$tasks must be an array. Actual type: '.\gettype($tasks));
        }

        if (empty($tasks)) {
            return [];
        }

        $result = [];

        foreach ($tasks as $task) {
            if ($task instanceof PushTask) {
                $task = $task->getTask();
            } elseif ($task instanceof Task) {
            } else {
                throw new \InvalidArgumentException('Each $task must be either PushTask or Task. Actual type: '.\gettype($task));
            }

            $tresult = Client::instance()->getClient()->createTask($this->full_name, $task);

            $tdetails = PushTask::parseTaskName($tresult);

            $result[] = $tdetails['task_id'];
        }

        return $result;
    }
}
