<?php

declare(strict_types=1);

/*
 * This file was part of the Monolog package (modified).
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace A1comms\GaeSupportLaravel\Log;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\Utils;
use OpenCensus\Trace\Tracer;

class JsonFormatter extends NormalizerFormatter
{
    public const BATCH_MODE_JSON     = 1;
    public const BATCH_MODE_NEWLINES = 2;

    protected $batchMode;
    protected $appendNewline;

    protected $message;

    protected $maxNormalizeDepth     = 9;
    protected $maxNormalizeItemCount = 1000;

    /**
     * @var bool
     */
    protected $includeStacktraces = true;

    public function __construct(int $batchMode = self::BATCH_MODE_NEWLINES, bool $appendNewline = true)
    {
        $this->batchMode     = $batchMode;
        $this->appendNewline = $appendNewline;
    }

    /**
     * The batch mode option configures the formatting style for
     * multiple records. By default, multiple records will be
     * formatted as a JSON-encoded array. However, for
     * compatibility with some API endpoints, alternative styles
     * are available.
     */
    public function getBatchMode(): int
    {
        return $this->batchMode;
    }

    /**
     * True if newlines are appended to every formatted record.
     */
    public function isAppendingNewlines(): bool
    {
        return $this->appendNewline;
    }

    /**
     * @suppress PhanTypeComparisonToArray
     */
    public function format(array $record): string
    {
        $this->message = $record['message'];

        $normalized = $this->normalize($record);
        if (isset($normalized['context']) && [] === $normalized['context']) {
            $normalized['context'] = new \stdClass();
        }
        if (isset($normalized['extra']) && [] === $normalized['extra']) {
            $normalized['extra'] = new \stdClass();
        }

        $normalized['message']                      = $this->normalize($this->message);
        $normalized['severity']                     = $normalized['level_name'];
        $normalized['logging.googleapis.com/trace'] = 'projects/'.gae_project().'/traces/'.Tracer::spanContext()->traceId();
        $normalized['time']                         = $normalized['datetime']->format(\DateTimeInterface::RFC3339_EXTENDED);

        unset($normalized['level'], $normalized['level_name'], $normalized['datetime']);

        $this->message = null;

        return $this->toJson($normalized, true).($this->appendNewline ? "\n" : '');
    }

    public function formatBatch(array $records): string
    {
        switch ($this->batchMode) {
            case static::BATCH_MODE_NEWLINES:
                return $this->formatBatchNewlines($records);

            case static::BATCH_MODE_JSON:
            default:
                return $this->formatBatchJson($records);
        }
    }

    public function includeStacktraces(bool $include = true): void
    {
        $this->includeStacktraces = $include;
    }

    /**
     * Return a JSON-encoded array of records.
     */
    protected function formatBatchJson(array $records): string
    {
        return $this->toJson($this->normalize($records), true);
    }

    /**
     * Use new lines to separate records instead of a
     * JSON-encoded array.
     */
    protected function formatBatchNewlines(array $records): string
    {
        $instance = $this;

        $oldNewline          = $this->appendNewline;
        $this->appendNewline = false;
        array_walk($records, static function (&$value, $key) use ($instance): void {
            $value = $instance->format($value);
        });
        $this->appendNewline = $oldNewline;

        return implode("\n", $records);
    }

    /**
     * Normalizes given $data.
     *
     * @param mixed $data
     * @param mixed $depth
     *
     * @return mixed
     */
    protected function normalize($data, $depth = 0)
    {
        if ($depth > $this->maxNormalizeDepth) {
            return 'Over '.$this->maxNormalizeDepth.' levels deep, aborting normalization';
        }

        if (\is_array($data) || $data instanceof \Traversable) {
            $normalized = [];

            $count = 1;
            foreach ($data as $key => $value) {
                if ($count++ > $this->maxNormalizeItemCount) {
                    $normalized['...'] = 'Over '.$this->maxNormalizeItemCount.' items ('.\count($data).' total), aborting normalization';

                    break;
                }

                $normalized[$key] = $this->normalize($value, $depth + 1);
            }

            return $normalized;
        }

        if ($data instanceof \Throwable) {
            return $this->normalizeException($data, 0);
        }

        return $data;
    }

    /**
     * Normalizes given exception with or without its own stack trace based on
     * `includeStacktraces` property.
     *
     * @param mixed $e
     * @param mixed $depth
     */
    protected function normalizeException($e, $depth = 0)
    {
        $data = [
            'class'   => Utils::getClass($e),
            'message' => $e->getMessage(),
            'code'    => $e->getCode(),
            'file'    => $e->getFile().':'.$e->getLine(),
        ];

        if ($this->includeStacktraces) {
            if ($depth > 0) {
                $trace = $e->getTrace();
                foreach ($trace as $frame) {
                    if (isset($frame['file'])) {
                        $data['trace'][] = $frame['file'].':'.$frame['line'];
                    } elseif (isset($frame['function']) && '{closure}' === $frame['function']) {
                        // We should again normalize the frames, because it might contain invalid items
                        $data['trace'][] = $frame['function'];
                    } else {
                        // We should again normalize the frames, because it might contain invalid items
                        $data['trace'][] = $this->normalize($frame);
                    }
                }
            } else {
                $this->message = 'EXCEPTION: ('.$data['code'].') '.$data['message']."\n\n".$e->getTraceAsString();
            }
        }

        if ($previous = $e->getPrevious()) {
            $data['previous'] = $this->normalizeException($previous, $depth + 1);
        }

        return $data;
    }
}
