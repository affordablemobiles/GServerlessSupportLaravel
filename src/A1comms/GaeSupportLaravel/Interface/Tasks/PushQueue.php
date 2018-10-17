<?php

namespace A1comms\GaeSupportLaravel\Interface\Tasks;

class PushQueue {
    private $name;

    public function __construct($name = 'default') {
        $this->name = $name;
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

        // TODO: Add tasks here...
    }
}