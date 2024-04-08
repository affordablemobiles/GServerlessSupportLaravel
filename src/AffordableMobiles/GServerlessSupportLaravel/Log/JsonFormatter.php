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

namespace AffordableMobiles\GServerlessSupportLaravel\Log;

use DateTimeInterface;
use Monolog\LogRecord;
use Monolog\Formatter\JsonFormatter as ParentJsonFormatter;

class JsonFormatter extends ParentJsonFormatter
{
    protected function normalizeRecord(LogRecord $record): array
    {
        $normalized = parent::normalizeRecord($record);

        // Re-key level for GCP logging
        $normalized['severity'] = $normalized['level_name'];
        $normalized['time'] = $record->datetime->format(DateTimeInterface::RFC3339_EXTENDED);
        $normalized['logging.googleapis.com/trace'] = g_serverless_trace_id();

        // Remove keys that are not used by GCP
        unset($normalized['level'], $normalized['level_name'], $normalized['datetime']);

        return $normalized;
    }
}