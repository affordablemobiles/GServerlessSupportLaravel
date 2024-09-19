<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Queue;

use A1comms\GaeSupportLaravel\Integration\TaskQueue\PushTask;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Queue\Queue;
use Illuminate\Support\Facades\Log;

class GaeQueue extends Queue implements QueueContract
{
    public const PAYLOAD_REQ_PARAM_NAME = 'data';

    /**
     * The current request instance.
     *
     * @var Request
     */
    protected $request;

    /**
     * The name of the default tube.
     *
     * @var string
     */
    protected $default;

    /**
     * URL for push.
     *
     * @var string
     */
    protected $url;

    /**
     * Indicates if the messages should be encrypted.
     *
     * @var bool
     */
    protected $shouldEncrypt;

    /**
     * Indicates is the messages should be compressed (useful when using ::withChain and/or larger job sizes).
     *
     * @var bool
     */
    protected $shouldCompress;

    /**
     * The encrypter implementation.
     *
     * @var Encrypter
     */
    protected $encrypter;

    /**
     * GaeQueue constructor.
     *
     * @param bool  $shouldEncrypt
     * @param bool  $shouldCompress
     * @param mixed $default
     * @param mixed $url
     */
    public function __construct(
        Request $request,
        $default,
        $url,
        $shouldEncrypt = false,
        $shouldCompress = false
    ) {
        $this->request        = $request;
        $this->url            = $url;
        $this->default        = $default;
        $this->shouldEncrypt  = $shouldEncrypt;
        $this->shouldCompress = $shouldCompress;

        $this->encrypter = app('encrypter');
    }

    /**
     * Push a new job onto the queue.
     *
     * @param string $job
     * @param mixed  $data
     * @param string $queue
     *
     * @return mixed
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue);
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param string $payload
     * @param string $queue
     *
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        if ($this->shouldEncrypt) {
            $payload = $this->encrypter->encrypt($payload);
        }
        if ($this->shouldCompress) {
            $payload = base64_encode(gzencode($payload, 9));
        }

        $task = new PushTask(
            $this->url,
            [
                static::PAYLOAD_REQ_PARAM_NAME => $payload,
            ],
            $options
        );

        return $task->add($this->getQueue($queue));
    }

    /**
     * Push a raw payload onto the queue after encrypting the payload.
     *
     * @param string $payload
     * @param string $queue
     * @param int    $delay
     *
     * @return mixed
     */
    public function recreate($payload, $queue, $delay)
    {
        $options = ['delay_seconds' => $this->getSeconds($delay)];

        return $this->pushRaw($payload, $queue, $options);
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param \DateTime|int $delay
     * @param string        $job
     * @param mixed         $data
     * @param string        $queue
     *
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        $delay_seconds = $this->getSeconds($delay);

        $payload = $this->createPayload($job, $data);

        return $this->pushRaw($payload, $queue, compact('delay_seconds'));
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param string $queue
     *
     * @return null|Job
     */
    public function pop($queue = null)
    {
        throw new \RuntimeException('Pop is not supported by GaeQueue.');
    }

    /**
     * Get the size of the queue.
     *
     * @param string $queue
     *
     * @return int
     */
    public function size($queue = null)
    {
        return 0;
    }

    /**
     * Delete a message from the Gae queue.
     *
     * @param string $queue
     * @param string $id
     */
    public function deleteMessage($queue, $id): void
    {
        throw new \RuntimeException('Delete is not supported by GaeQueue.');
    }

    /**
     * Marshal a push queue request and fire the job.
     *
     * @return Response
     */
    public function marshal()
    {
        try {
            $job = $this->marshalPushedJob();
        } catch (\Exception $e) {
            // Ignore for security reasons!
            // So if we are being hacked
            // the hacker would think it went OK.
            Log::warning(
                'Marshalling Queue Request: Invalid job. '.$e->getMessage(),
                [
                    'exception' => $e,
                ]
            );

            return new Response('OK');
        }

        if (isset($job->id)) {
            $this->createPushedGaeJob($job)->fire();
        } else {
            Log::warning('Marshalling Queue Request: No GAE header supplied.');
        }

        return new Response('OK');
    }

    /**
     * Get the queue or return the default.
     *
     * @param null|string $queue
     *
     * @return string
     */
    public function getQueue($queue)
    {
        return $queue ?: $this->default;
    }

    /**
     * Get the request instance.
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Set the request instance.
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * Marshal out the pushed job and payload.
     *
     * @return object
     */
    protected function marshalPushedJob()
    {
        $r = $this->request;

        $body = $this->parseJobBody($r->input(static::PAYLOAD_REQ_PARAM_NAME));

        return (object) [
            'id'     => $r->header('X-AppEngine-TaskName'),
            'body'   => $body,
            'pushed' => true,
        ];
    }

    /**
     * Create a new GaeJob for a pushed job.
     *
     * @param object $job
     *
     * @return GaeJob
     */
    protected function createPushedGaeJob($job)
    {
        return new GaeJob($this->container, $this, $job, true);
    }

    /**
     * Parse the job body for firing.
     *
     * @param string $body
     *
     * @return string
     */
    protected function parseJobBody($body)
    {
        if ($this->shouldCompress) {
            $body = gzdecode(base64_decode($body, true));
        }

        if ($this->shouldEncrypt) {
            $body = $this->encrypter->decrypt($body);
        }

        return $body;
    }
}
