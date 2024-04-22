<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Trace\Sampler;

use A1comms\GaeSupportLaravel\Trace\Propagator\CloudTraceFormatter;
use OpenCensus\Trace\Sampler\SamplerInterface;

class HttpHeaderSampler implements SamplerInterface
{
    public const HEADER_NAME = 'HTTP_X_CLOUD_TRACE_CONTEXT';

    public function shouldSample()
    {
        $context = (new CloudTraceFormatter())->deserialize(
            $_SERVER[self::HEADER_NAME]
        );

        return $context->enabled() ?: false;
    }
}
