<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Sampler;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Propagator\CloudTraceFormatter;
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
