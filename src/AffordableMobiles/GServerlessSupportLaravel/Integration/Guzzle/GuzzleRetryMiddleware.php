<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\Guzzle;

use Closure;
use Google\Cloud\Core\ExponentialBackoff;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function GuzzleHttp\Promise\rejection_for; /** @phpstan-ignore-line */

/**
 * Guzzle middleware that retries requests when encountering
 * errors establishing a connection (connect_timeout).
 *
 * Adapted from: https://github.com/caseyamcl/guzzle_retry_middleware
 * Copyright (c) 2020 Casey McLaughlin caseyamcl@gmail.com
 */
class GuzzleRetryMiddleware
{
    /**
     * @var array<string,mixed>
     */
    private $defaultOptions = [
        // Retry enabled.  Toggle retry on or off per request
        'retry_enabled'                    => true,

        // Default connect_timeout
        'connect_timeout'                  => 2,

        // Set a maximum number of attempts per request
        'max_retry_attempts'               => 6,

        // Callback to trigger before delay occurs (accepts count, delay, request, response, options)
        'on_retry_callback'                => null,
    ];

    /**
     * @var callable
     */
    private $nextHandler;

    /**
     * GuzzleRetryMiddleware constructor.
     *
     * @param array<string,mixed> $defaultOptions
     */
    final public function __construct(callable $nextHandler, array $defaultOptions = [])
    {
        $this->nextHandler    = $nextHandler;
        $this->defaultOptions = array_replace($this->defaultOptions, $defaultOptions);
    }

    /**
     * @param array<string,mixed> $options
     */
    public function __invoke(RequestInterface $request, array $options): Promise
    {
        // Combine options with defaults specified by this middleware
        $options = array_replace($this->defaultOptions, $options);

        // Set the retry counter if not already set
        if (!isset($options['retry_count'])) {
            $options['retry_count'] = 0;
        }

        $timeout         = (int) $options['timeout'];
        $connect_timeout = (int) $options['connect_timeout'];
        if ($timeout === $connect_timeout) {
            if ($timeout > $this->defaultOptions['connect_timeout']) {
                $options['connect_timeout'] = $this->defaultOptions['connect_timeout'];
            } else {
                $options['connect_timeout'] = 0.5;
            }
        }

        $next = $this->nextHandler;

        return $next($request, $options)
            ->then(
                $this->onFulfilled($request, $options),
                $this->onRejected($request, $options)
            )
        ;
    }

    /**
     * Provides a closure that can be pushed onto the handler stack.
     *
     * Example:
     * <code>$handlerStack->push(GuzzleRetryMiddleware::factory());</code>
     *
     * @param array<string,mixed> $defaultOptions
     */
    public static function factory(array $defaultOptions = []): \Closure
    {
        return static fn (callable $handler): self => new static($handler, $defaultOptions);
    }

    /**
     * No exceptions were thrown during processing.
     *
     * Depending on where this middleware is in the stack, the response could still
     * be unsuccessful (e.g. 429 or 503), so check to see if it should be retried
     *
     * @param array<string,mixed> $options
     */
    protected function onFulfilled(RequestInterface $request, array $options): callable
    {
        return function (ResponseInterface $response) use ($request, $options) {
            return $this->shouldRetryHttpResponse($options, $request, $response)
                ? $this->doRetry($request, $options, $response)
                : $response;
        };
    }

    /**
     * An exception or error was thrown during processing.
     *
     * If the reason is a BadResponseException exception, check to see if
     * the request can be retried.  Otherwise, pass it on.
     *
     * @param array<string,mixed> $options
     */
    protected function onRejected(RequestInterface $request, array $options): callable
    {
        return function (\Throwable $reason) use ($request, $options): PromiseInterface {
            // If was bad response exception, test if we retry based on the response headers
            if ($reason instanceof BadResponseException) {
                if ($this->shouldRetryHttpResponse($options, $request, $reason->getResponse())) {
                    return $this->doRetry($request, $options, $reason->getResponse());
                }
            // If this was a connection exception, test to see if we should retry based on connect timeout rules
            } elseif ($reason instanceof ConnectException) {
                // If was another type of exception, test if we should retry based on timeout rules
                if ($this->shouldRetryConnectException($options, $request, $reason)) {
                    return $this->doRetry($request, $options);
                }
            }

            // If made it here, then we have decided not to retry the request
            // Future-proofing this; remove when bumping minimum Guzzle version to 7.0
            if (class_exists('\GuzzleHttp\Promise\Create')) {
                return Create::rejectionFor($reason);
            }

            return rejection_for($reason); // @phpstan-ignore-line
        };
    }

    /**
     * Decide whether to retry on connect exception.
     *
     * @param array<string,mixed> $options
     */
    protected function shouldRetryConnectException(array $options, RequestInterface $request, ConnectException $reason): bool
    {
        switch (true) {
            case false === $options['retry_enabled']:
            case 0     === $this->countRemainingRetries($options):
                return false;

            case Tools::isConnectionError($reason, (int) $options['connect_timeout']):
                return true;

                // Conditions met; see if status code matches one that can be retried
            default:
                return false;
        }
    }

    /**
     * Check whether to retry a request that received an HTTP response.
     *
     * Defaults to no at the moment.
     *
     * @param array<string,mixed> $options
     *
     * @return bool TRUE if the response should be retried, FALSE if not
     */
    protected function shouldRetryHttpResponse(
        array $options,
        RequestInterface $request,
        ?ResponseInterface $response = null
    ): bool {
        switch (true) {
            case false === $options['retry_enabled']:
            case 0     === $this->countRemainingRetries($options):
                return false;

                // Conditions met; see if status code matches one that can be retried
            default:
                return false;
        }
    }

    /**
     * Count the number of retries remaining.  Always returns 0 or greater.
     *
     * @param array<string,mixed> $options
     */
    protected function countRemainingRetries(array $options): int
    {
        $retryCount = isset($options['retry_count']) ? (int) $options['retry_count'] : 0;

        $numAllowed = isset($options['max_retry_attempts'])
            ? (int) $options['max_retry_attempts']
            : $this->defaultOptions['max_retry_attempts'];

        return (int) max([$numAllowed - $retryCount, 0]);
    }

    /**
     * Retry the request.
     *
     * Increments the retry count, determines the delay (timeout), executes callbacks, sleeps, and re-sends the request
     *
     * @param array<string,mixed> $options
     */
    protected function doRetry(RequestInterface $request, array $options, ?ResponseInterface $response = null): Promise
    {
        // Increment the retry count
        ++$options['retry_count'];

        // Determine the delay timeout
        $delayTimeout = ExponentialBackoff::calculateDelay(
            (int) $options['retry_count'],
        );

        // Callback
        \call_user_func_array(
            $options['on_retry_callback'] ?? [$this, 'onRetry'],
            [
                (int) $options['retry_count'],
                $delayTimeout,
                &$request,
                &$options,
                $response,
            ]
        );

        // Delay!
        usleep($delayTimeout);

        // Return
        return $this($request, $options);
    }

    /**
     * Retry notification.
     *
     * Can be overriden by specifying the `on_retry_callback` option.
     */
    protected function onRetry(int $retryCount, int $delayTimeoutMicros, RequestInterface $request, array $options, ?ResponseInterface $response = null): void
    {
        Log::info('ExponentialBackoff: retrying Guzzle request due to network timeout', [
            'retryCount' => $retryCount,
        ]);
    }
}
