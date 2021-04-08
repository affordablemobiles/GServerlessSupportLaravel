<?php

namespace A1comms\GaeSupportLaravel\Trace\Sampler;

use OpenCensus\Trace\Sampler\SamplerInterface;
use A1comms\GaeSupportLaravel\Trace\Propagator\CloudTraceFormatter;

class HttpHeaderSampler implements SamplerInterface
{
    const HEADER_NAME = '';

    public function shouldSample()
    {
        $context = (new CloudTraceFormatter())->deserialize(
            $_SERVER[self::HEADER_NAME]
        );

        return ($context->enabled() ?: false);
    }
}
