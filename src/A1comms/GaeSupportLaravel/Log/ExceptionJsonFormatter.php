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

class ExceptionJsonFormatter extends JsonFormatter
{
    /**
     * @suppress PhanTypeComparisonToArray
     */
    public function format(array $record): string
    {
        $context = $record['context'];
        unset($record['context']);
        $record = array_merge_recursive($record, $context);

        return parent::format($record);
    }
}
