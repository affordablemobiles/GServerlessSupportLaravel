<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\Debugbar;

use AffordableMobiles\OpenTelemetry\CloudTrace\Exporter as CloudTraceExporter;
use DebugBar\DataCollector\Renderable;
use DebugBar\DataCollector\TimeDataCollector as BaseTimeDataCollector;
use DebugBar\DebugBarException;
use OpenTelemetry\SDK\Common\Time\ClockInterface;
use OpenTelemetry\SDK\Trace\SpanDataInterface;

/**
 * Collects info about the request duration as well as providing
 * a way to log duration of any operations.
 */
class TimeDataCollector extends BaseTimeDataCollector implements Renderable
{
    public function mapSpan(SpanDataInterface $span): array
    {
        $start = $this->epochNanoToMicrotime(
            $span->getStartEpochNanos(),
        );
        $end   = $this->epochNanoToMicrotime(
            $span->getEndEpochNanos(),
        );
        if ($end <= 0) {
            $end = microtime(true);
        }

        return [
            'label'          => $span->getName(),
            'start'          => $start,
            'relative_start' => $start - $this->requestStartTime,
            'end'            => $end,
            'relative_end'   => $end - $this->requestEndTime,
            'duration'       => $end - $start,
            'duration_str'   => $this->getDataFormatter()->formatDuration($end - $start),
            'params'         => [],
            'collector'      => null,
        ];
    }

    /**
     * @return array
     *
     * @throws DebugBarException
     */
    public function collect()
    {
        $measures = array_map(fn ($span) => $this->mapSpan($span), CloudTraceExporter::getSpans());

        usort($measures, static function ($a, $b) {
            if ($a['start'] === $b['start']) {
                return 0;
            }

            return $a['start'] < $b['start'] ? -1 : 1;
        });

        return [
            'start'        => $this->requestStartTime,
            'end'          => $this->requestEndTime,
            'duration'     => $this->getRequestDuration(),
            'duration_str' => $this->getDataFormatter()->formatDuration($this->getRequestDuration()),
            'measures'     => $measures,
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
                'icon'    => 'clock-o',
                'tooltip' => 'Request Duration',
                'map'     => 'time.duration_str',
                'default' => "'0ms'",
            ],
            'timeline' => [
                'icon'    => 'tasks',
                'widget'  => 'PhpDebugBar.Widgets.TimelineWidget',
                'map'     => 'time',
                'default' => '{}',
            ],
        ];
    }

    private function epochNanoToMicrotime(int $nanos): float
    {
        $micro = (int) ($nanos / ClockInterface::NANOS_PER_MICROSECOND);

        return (float) $micro / ClockInterface::MICROS_PER_SECOND;
    }
}
