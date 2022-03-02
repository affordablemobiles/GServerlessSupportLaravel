<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Integration\Debugbar;

use DebugBar\DataCollector\Renderable;
use DebugBar\DataCollector\TimeDataCollector as BaseTimeDataCollector;
use DebugBar\DebugBarException;

/**
 * Collects info about the request duration as well as providing
 * a way to log duration of any operations.
 */
class TimeDataCollector extends BaseTimeDataCollector implements Renderable
{
    public function mapSpan($span)
    {
        $start = $span->startTime();
        $end = $span->endTime();
        if ($end <= 0) {
            $end = microtime(true);
        }

        return [
            'label' => $span->name(),
            'start' => $start,
            'relative_start' => $start - $this->requestStartTime,
            'end' => $end,
            'relative_end' => $end - $this->requestEndTime,
            'duration' => $end - $start,
            'duration_str' => $this->getDataFormatter()->formatDuration($end - $start),
            'params' => [],
            'collector' => null,
        ];
    }

    /**
     * @throws DebugBarException
     *
     * @return array
     */
    public function collect()
    {
        $measures = [];

        if (\function_exists('opencensus_trace_list')) {
            $measures = array_map(fn ($span) => $this->mapSpan($span), opencensus_trace_list());
        }

        usort($measures, function ($a, $b) {
            if ($a['start'] === $b['start']) {
                return 0;
            }

            return $a['start'] < $b['start'] ? -1 : 1;
        });

        return [
            'start' => $this->requestStartTime,
            'end' => $this->requestEndTime,
            'duration' => $this->getRequestDuration(),
            'duration_str' => $this->getDataFormatter()->formatDuration($this->getRequestDuration()),
            'measures' => $measures,
        ];
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'time';
    }

    /**
     * @return array
     */
    public function getWidgets()
    {
        return [
            'time' => [
                'icon' => 'clock-o',
                'tooltip' => 'Request Duration',
                'map' => 'time.duration_str',
                'default' => "'0ms'",
            ],
            'timeline' => [
                'icon' => 'tasks',
                'widget' => 'PhpDebugBar.Widgets.TimelineWidget',
                'map' => 'time',
                'default' => '{}',
            ],
        ];
    }
}
