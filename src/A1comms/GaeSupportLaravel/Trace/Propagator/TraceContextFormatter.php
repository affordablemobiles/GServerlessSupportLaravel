<?php

namespace A1comms\GaeSupportLaravel\Trace\Propagator;

use OpenCensus\Trace\Propagator\TraceContextFormatter as BaseTraceContextFormatter;

class TraceContextFormatter extends BaseTraceContextFormatter
{
    /**
     * Generate a SpanContext object from the Trace Context header
     *
     * @param string $header
     * @return SpanContext
     */
    public function deserialize($header)
    {
        $return = parent::deserialize($header);

        if (is_gae() && (env('APP_ENV', 'production') != 'production'))
        {
            // Force a trace of everything in development.

            $return->setEnabled(true);
        }

        return $return;
    }
}