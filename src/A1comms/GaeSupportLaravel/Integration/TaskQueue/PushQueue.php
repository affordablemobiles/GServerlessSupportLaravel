<?php

namespace A1comms\GaeSupportLaravel\Integration\TaskQueue;

class PushQueue
{
    private $name;
    private $full_name;

    public function __construct($name = 'default')
    {
        $this->name = $name;
        $this->full_name = Client::instance()->getQueueName($name);
    }

    public function getName()
    {
        return $this->name;
    }

    public function addTasks($tasks)
    {
        if (!is_array($tasks)) {
            throw new \InvalidArgumentException('$tasks must be an array. Actual type: ' . gettype($tasks));
        }

        if (empty($tasks)) {
            return [];
        }

        $result = [];

        foreach ($tasks as $task) {
            $tresult = Client::instance()->getClient()->createTask($this->full_name, $task->getTask());

            $tdetails = PushTask::parseTaskName($tresult);

            $result[] = $tdetails['task_id'];
        }

        return $result;
    }
}
