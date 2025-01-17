<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\InstrumentationInterface;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\ApplicationBuilder;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Common\Time\ClockInterface;

use function OpenTelemetry\Instrumentation\hook;

class LaravelBootInstrumentation implements InstrumentationInterface
{
    public const NAME = 'laravel-boot';

    public static function register(CachedInstrumentation $instrumentation): void
    {
        if (!\defined('LARAVEL_START')) {
            return;
        }

        $timestamp = LARAVEL_START;

        $parent = Context::getCurrent()->withContextValue(
            Span::wrap(
                g_serverless_trace_context(),
            ),
        );
        $parent->activate();

        $span   = $instrumentation->tracer()
            ->spanBuilder('laravel/bootstrap')
            ->setParent($parent)
            ->setStartTimestamp((int) ($timestamp * ClockInterface::NANOS_PER_SECOND))
            ->startSpan()
        ;

        hook(
            ApplicationBuilder::class,
            'create',
            post: static function (ApplicationBuilder $application, array $params, Application $returnValue, ?\Throwable $exception) use ($span): void {
                $returnValue->booted(static fn () => $span->end());
            },
        );
    }
}
