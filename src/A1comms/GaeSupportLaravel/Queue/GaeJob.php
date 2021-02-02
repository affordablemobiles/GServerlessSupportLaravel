<?php

namespace A1comms\GaeSupportLaravel\Queue;

use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Contracts\Queue\Job as JobContract;

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
     * @param  \Illuminate\Container\Container  $container
     * @param  \A1comms\GaeSupportLaravel\Queue\GaeQueue  $gaeQueue
     * @param  object  $job
     * @param  bool    $pushed
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
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        if (isset($this->job->pushed)) {
            return;
        }
    }

    /**
     * Release the job back into the queue.
     *
     * @param  int   $delay
     * @return void
     */
    public function release($delay = 0)
    {
        if (! $this->pushed) {
            $this->delete();
        }

        $this->recreateJob($delay);
    }

    /**
     * Release a pushed job back onto the queue.
     *
     * @param  int  $delay
     * @return void
     */
    protected function recreateJob($delay)
    {
        $this->gaeQueue->recreate($this->getRawBody(), $this->getQueue(), $delay);
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
}
