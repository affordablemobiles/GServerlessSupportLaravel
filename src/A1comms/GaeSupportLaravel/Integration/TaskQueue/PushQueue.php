<?php

namespace A1comms\GaeSupportLaravel\Integration\TaskQueue;

if (GAE_LEGACY) {
    class PushQueue extends \google\appengine\api\taskqueue\PushQueue{}
} else {
    class PushQueue
    {
        private $name;
        private $full_name;

        public function __construct($name = 'default') {
            $this->name = $name;
            $this->full_name = Client::instance()->getQueueName($name);
        }

        public function getName() {
            return $this->name;
        }

        public function addTasks($tasks) {
            if (!is_array($tasks)) {
                throw new \InvalidArgumentException('$tasks must be an array. Actual type: ' . gettype($tasks));
            }

            if (empty($tasks)) {
                return [];
            }

            $result = [];

            foreach ($tasks as $task) {
                $result[] = Client::instance()->getClient()->createTask($this->full_name, $task->getTask());
            }

            return $result;
        }
    }
}