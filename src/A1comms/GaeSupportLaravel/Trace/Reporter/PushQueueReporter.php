<?php

namespace A1comms\GaeSupportLaravel\Trace\Reporter;

use Google\Cloud\Core\Exception\ServiceException;
use Google\Cloud\Trace\TraceClient;
use Google\Cloud\Trace\TraceSpan;
use Google\Cloud\Trace\Tracer\TracerInterface;
use Google\Cloud\Trace\Reporter\ReporterInterface;

/**
 * This implementation of the ReporterInterface uses App Engine Push Queues
 * to report Traces via a secondary microservice to allow lower latency
 * for frontend requests.
 */
class PushQueueReporter implements ReporterInterface
{
    /**
     * Create a TraceReporter that uses the provided TraceClient to report.
     *
     */
    public function __construct()
    {

    }

    /**
     * Report the provided Trace to a backend.
     *
     * @param  TracerInterface $tracer
     * @return bool
     */
    public function report(TracerInterface $tracer)
    {
        $spans = $tracer->spans();
        if (empty($spans)) {
            return false;
        }

        $trace = [
            "projectId"     =>  gae_project(),
            "traceId"       =>  $tracer->context()->traceId(),
        ];

        $tasks = [];

        $i = 0;
        while ($i < count(self::$spans)){
            $s_spans = array_map(
                function (TraceSpan $span) {
                    return $span->info();
                },
                array_slice($spans, $i, 100)
            );

            $t_trace = $trace;
            $t_trace += [
                "spans"     => $s_spans,
            ]

            if (class_exists('google\appengine\api\taskqueue\PushTask')) {
                $tasks += [
                    new \google\appengine\api\taskqueue\PushTask('/system/traceSubmit', ['data' => json_encode($t_trace)], ['delay_seconds' => 0, 'method' => 'POST']),
                ];
            }

            $i += 100;
        }

        try {
            $queue = new \google\appengine\api\taskqueue\PushQueue('trace');
            $queue->addTasks($tasks);

            return true;
        } catch (ServiceException $e) {
            return false;
        }
    }
}
