<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Context;

class SimpleSpan
{
    public static function pre(CachedInstrumentation $instrumentation, string $name, array $attributes = [], ?ContextInterface $parentContext = null): void
    {
        $parentContext ??= Context::getCurrent();

        $builder = $instrumentation->tracer()
            ->spanBuilder($name)
            ->setParent($parentContext)
        ;

        foreach ($attributes as $k => $v) {
            $builder->setAttribute($k, $v);
        }

        $span = $builder->startSpan();

        $context = $span->storeInContext($parentContext);

        Context::storage()->attach($context);
    }

    public static function post(array $attributes = []): void
    {
        $scope = Context::storage()->scope();
        $scope?->detach();

        if (!$scope || $scope->context() === Context::getCurrent()) {
            return;
        }

        $span = Span::fromContext($scope->context());

        foreach ($attributes as $k => $v) {
            $span->setAttribute($k, $v);
        }

        $span->end();
    }
}
