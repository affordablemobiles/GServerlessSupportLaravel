<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Guzzle;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\InstrumentationInterface;
use GuzzleHttp\ClientInterface as GuzzleClient;
use GuzzleHttp\Promise\PromiseInterface;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function OpenTelemetry\Instrumentation\hook;

class GuzzleInstrumentation implements InstrumentationInterface
{
    /** @psalm-suppress ArgumentTypeCoercion */
    public const NAME = 'guzzle';

    public static function register(CachedInstrumentation $instrumentation): void
    {
        hook(
            GuzzleClient::class,
            'transfer',
            pre: static function (GuzzleClient $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): array {
                $request = $params[0];
                \assert($request instanceof RequestInterface);

                $propagator    = Globals::propagator();
                $parentContext = Context::getCurrent();

                /** @psalm-suppress ArgumentTypeCoercion */
                $spanBuilder = $instrumentation
                    ->tracer()
                    ->spanBuilder('GuzzleHttp/request')
                    ->setParent($parentContext)
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setAttribute(TraceAttributes::URL_FULL, (string) $request->getUri())
                    ->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $request->getMethod())
                    ->setAttribute(TraceAttributes::NETWORK_PROTOCOL_VERSION, $request->getProtocolVersion())
                    ->setAttribute(TraceAttributes::USER_AGENT_ORIGINAL, $request->getHeaderLine('User-Agent'))
                    ->setAttribute(TraceAttributes::HTTP_REQUEST_BODY_SIZE, $request->getHeaderLine('Content-Length'))
                    ->setAttribute(TraceAttributes::SERVER_ADDRESS, $request->getUri()->getHost())
                    ->setAttribute(TraceAttributes::SERVER_PORT, $request->getUri()->getPort())
                    ->setAttribute(TraceAttributes::URL_PATH, $request->getUri()->getPath())
                    ->setAttribute('stackTrace', serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)))
                ;

                foreach ($propagator->fields() as $field) {
                    $request = $request->withoutHeader($field);
                }
                foreach (array_filter(explode('|', env('TRACE_GUZZLE_HTTP_REQUEST_HEADERS', ''))) as $header) {
                    if ($request->hasHeader($header)) {
                        $spanBuilder->setAttribute(
                            sprintf('http.request.header.%s', strtolower($header)),
                            $request->getHeader($header)
                        );
                    }
                }

                $span    = $spanBuilder->startSpan();
                $context = $span->storeInContext($parentContext);
                $propagator->inject($request, HeadersPropagator::instance(), $context);

                Context::storage()->attach($context);

                return [$request];
            },
            post: static function (GuzzleClient $client, array $params, PromiseInterface $promise, ?\Throwable $exception): void {
                $scope = Context::storage()->scope();
                $scope?->detach();

                if (!$scope || $scope->context() === Context::getCurrent()) {
                    return;
                }

                $span = Span::fromContext($scope->context());
                if ($exception) {
                    $span->recordException($exception, [TraceAttributes::EXCEPTION_ESCAPED => true]);
                    $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
                    $span->end();
                }

                $promise->then(
                    onFulfilled: static function (ResponseInterface $response) use ($span) {
                        $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());
                        $span->setAttribute(TraceAttributes::NETWORK_PROTOCOL_VERSION, $response->getProtocolVersion());
                        $span->setAttribute(TraceAttributes::HTTP_RESPONSE_BODY_SIZE, $response->getHeaderLine('Content-Length'));

                        foreach (array_filter(explode('|', env('TRACE_GUZZLE_HTTP_RESPONSE_HEADERS', ''))) as $header) {
                            if ($response->hasHeader($header)) {
                                // @psalm-suppress ArgumentTypeCoercion
                                $span->setAttribute(sprintf('http.response.header.%s', strtolower($header)), $response->getHeader($header));
                            }
                        }
                        if ($response->getStatusCode() >= 400 && $response->getStatusCode() < 600) {
                            $span->setStatus(StatusCode::STATUS_ERROR);
                        }
                        $span->end();

                        return $response;
                    },
                    onRejected: static function (\Throwable $t) use ($span): void {
                        $span->recordException($t, [TraceAttributes::EXCEPTION_ESCAPED => true]);
                        $span->setStatus(StatusCode::STATUS_ERROR, $t->getMessage());
                        $span->end();

                        throw $t;
                    }
                );
            }
        );
    }
}
