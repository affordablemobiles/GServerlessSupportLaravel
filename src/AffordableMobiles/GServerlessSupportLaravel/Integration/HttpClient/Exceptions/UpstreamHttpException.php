<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\HttpClient\Exceptions;

use GuzzleHttp\Psr7\Message;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;

abstract class UpstreamHttpException extends HttpClientException
{
    public const DEFAULT_SUMMARY_LENGTH = 250;

    public function __construct(
        public readonly Request $request,
        public readonly Response $response,
        ?\Throwable $previous = null,
        ?int $summaryTruncateAt = self::DEFAULT_SUMMARY_LENGTH,
    ) {
        $message = $this->prepareMessage($request, $response, $summaryTruncateAt);

        parent::__construct($message, $response->status(), $previous);
    }

    /**
     * Named constructor for convenience.
     */
    public static function fromResponse(
        Request $request,
        Response $response,
        ?\Throwable $previous = null,
        ?int $summaryTruncateAt = self::DEFAULT_SUMMARY_LENGTH,
    ): static {
        return new static($request, $response, $previous, $summaryTruncateAt);
    }

    /**
     * Get the target host of the request.
     */
    public function getHost(): string
    {
        return parse_url($this->request->url(), PHP_URL_HOST) ?? 'unknown-host';
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatus(): int
    {
        return $this->response->status();
    }

    /**
     * Build the structured multi-line exception message.
     *
     * Line 1: Stable signature for Google Cloud Error Reporting grouping.
     * Line 2+: Truncated response body summary for diagnostic root-cause analysis.
     */
    protected function prepareMessage(Request $request, Response $response, ?int $truncateAt): string
    {
        $status = $response->status();
        $url    = $request->url();
        $host   = parse_url($url, PHP_URL_HOST) ?? 'unknown-host';
        $path   = parse_url($url, PHP_URL_PATH) ?? '/';
        $method = $request->method();
        $reason = $response->reason();

        $firstLine = \sprintf('[%s] %s %s -> %d %s', $host, $method, $path, $status, $reason);

        if (null === $truncateAt || $truncateAt <= 0) {
            return $firstLine;
        }

        $psrResponse = $response->toPsrResponse();
        $bodySummary = Message::bodySummary($psrResponse, $truncateAt);

        return null !== $bodySummary
            ? \sprintf("%s\nResponse summary:\n%s", $firstLine, $bodySummary)
            : $firstLine;
    }
}
