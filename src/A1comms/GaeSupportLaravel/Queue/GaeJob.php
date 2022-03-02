<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Queue;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;

class GaeJob extends Job implements JobContract
{
    /**
     * The Gae queue instance.
     *
     * @var \A1comms\GaeSupportLaravel\Queue\GaeQueue
     */
    protected $gaeQueue;

    /**
     * The Gae message instance.
     *
     * @var array
     */
    protected $job;

    /**
     * Indicates if the message was a push message.
     *
     * @var bool
     */
    protected $pushed = false;

    /**
     * Create a new job instance.
     *
     * @param \A1comms\GaeSupportLaravel\Queue\GaeQueue $gaeQueue
     * @param object                                    $job
     * @param bool                                      $pushed
     */
    public function __construct(
        Container $container,
        GaeQueue $gaeQueue,
        $job,
        $pushed = false
    ) {
        $this->job = $job;
        $this->gaeQueue = $gaeQueue;
        $this->pushed = $pushed;
        $this->container = $container;
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->job->body;
    }

    /**
     * Delete the job from the queue.
     */
    public function delete(): void
    {
        parent::delete();

        if (isset($this->job->pushed)) {
            return;
        }
    }

    /**
     * Release the job back into the queue.
     *
     * @param int $delay
     */
    public function release($delay = 0): void
    {
        if (!$this->pushed) {
            $this->delete();
        }

        $this->recreateJob($delay);
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return 0;
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->job->id;
    }

    /**
     * Get the IoC container instance.
     *
     * @return \Illuminate\Container\Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Get the underlying Gae queue instance.
     *
     * @return \A1comms\GaeSupportLaravel\Queue\GaeQueue
     */
    public function getGaeQueue()
    {
        return $this->gaeQueue;
    }

    /**
     * Get the underlying Gae job.
     *
     * @return array
     */
    public function getGaeJob()
    {
        return $this->job;
    }

    /**
     * Release a pushed job back onto the queue.
     *
     * @param int $delay
     */
    protected function recreateJob($delay): void
    {
        $this->gaeQueue->recreate($this->getRawBody(), $this->getQueue(), $delay);
    }
}
