<?php

declare(strict_types=1);

use AffordableMobiles\GServerlessSupportLaravel\Trace\Propagator\CloudTracePropagator;
use OpenTelemetry\SDK\Registry;

if (!class_exists(Registry::class)) {
    return;
}

Registry::registerTextMapPropagator(
    'cloudtrace',
    CloudTracePropagator::getInstance()
);

Registry::registerTextMapPropagator(
    'cloudtrace-oneway',
    CloudTracePropagator::getOneWayInstance()
);
