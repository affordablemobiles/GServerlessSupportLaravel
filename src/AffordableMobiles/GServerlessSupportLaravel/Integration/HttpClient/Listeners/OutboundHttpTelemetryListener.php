<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\HttpClient\Listeners;

use AffordableMobiles\GServerlessSupportLaravel\Integration\HttpClient\Exceptions\UpstreamClientException;
use AffordableMobiles\GServerlessSupportLaravel\Integration\HttpClient\Exceptions\UpstreamServerException;
use AffordableMobiles\GServerlessSupportLaravel\Support\UriMatcher;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Response;

class OutboundHttpTelemetryListener
{
    /**
     * Default critical 4xx status codes to report (authentication, authorization, rate limiting).
     *
     * @var array<int>
     */
    public const DEFAULT_REPORT_4XX = [
        401, // Unauthorized / Bad Credentials
        403, // Forbidden / IP Whitelist / Scope Issue
        429, // Too Many Requests / Rate Limited
    ];

    protected readonly ConfigRepository $config;

    public function __construct(?ConfigRepository $config = null)
    {
        $this->config = $config ?? app('config');
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(ConnectionFailed::class, [$this, 'handleConnectionFailed']);
        $events->listen(ResponseReceived::class, [$this, 'handleResponseReceived']);
    }

    /**
     * Handle outbound connection failures (transport errors like timeouts and DNS resolution failures).
     */
    public function handleConnectionFailed(ConnectionFailed $event): void
    {
        [$host, $path] = UriMatcher::parseUri($event->request->url());
        $hostConfig    = $this->resolveHostConfig($host, $path);

        if (isset($hostConfig['enabled']) && false === $hostConfig['enabled']) {
            return;
        }

        report($event->exception);
    }

    /**
     * Handle completed outbound HTTP responses.
     */
    public function handleResponseReceived(ResponseReceived $event): void
    {
        $response = $event->response;

        if ($response->successful()) {
            return;
        }

        [$host, $path] = UriMatcher::parseUri($event->request->url());

        if (!$this->shouldReportResponse($host, $path, $response)) {
            return;
        }

        $exception = $response->serverError()
            ? new UpstreamServerException($event->request, $response, $response->toException())
            : new UpstreamClientException($event->request, $response, $response->toException());

        report($exception);
    }

    /**
     * Determine whether an HTTP response error should be reported based on status and host configuration.
     */
    protected function shouldReportResponse(string $host, string $path, Response $response): bool
    {
        $hostConfig = $this->resolveHostConfig($host, $path);

        if (isset($hostConfig['enabled']) && false === $hostConfig['enabled']) {
            return false;
        }

        $status = $response->status();

        // 5xx Server Errors
        if ($response->serverError()) {
            return (bool) ($hostConfig['report_5xx'] ?? $this->config->get('gserverlesssupport.http-client.telemetry.report_5xx', true));
        }

        // 4xx Client Errors
        if ($response->clientError()) {
            $allowed4xx = $hostConfig['report_4xx'] ?? $this->config->get('gserverlesssupport.http-client.telemetry.report_4xx', self::DEFAULT_REPORT_4XX);

            if (false === $allowed4xx || [] === $allowed4xx) {
                return false;
            }

            return \in_array($status, (array) $allowed4xx, true);
        }

        return false;
    }

    /**
     * Resolve configuration options for a given host name and path.
     *
     * @return array<string,mixed>
     */
    protected function resolveHostConfig(string $host, string $path): array
    {
        $hosts = $this->config->get('gserverlesssupport.http-client.telemetry.hosts', []);

        if (empty($hosts) || !\is_array($hosts)) {
            return [];
        }

        $matched = UriMatcher::matchHostAndPath($hosts, $host, $path);

        return \is_array($matched) ? $matched : [];
    }
}
