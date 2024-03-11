<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Propagator;

use OpenCensus\Trace\Propagator\CloudTraceFormatter as BaseCloudTraceFormatter;

class CloudTraceFormatter extends BaseCloudTraceFormatter
{
    /**
     * Generate a SpanContext object from the Trace Context header.
     *
     * @param string $header
     *
     * @return SpanContext
     */
    public function deserialize($header)
    {
        $return = parent::deserialize($header);

        if (is_gae() && env('GAE_DEVELOPMENT', false)) {
            // Force a trace of everything in development.

            $return->setEnabled(true);
        }

        return $return;
    }
}
