<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Propagator;

use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\SpanContextInterface;

/**
 * This format using a human readable string encoding to propagate SpanContext.
 * The current format of the header is `<trace-id>[/<span-id>][;o=<options>]`.
 * The options are a bitmask of options. Currently the only option is the
 * least significant bit which signals whether the request was traced or not
 * (1 = traced, 0 = not traced).
 */
final class CloudTraceFormatter
{
    public const CONTEXT_HEADER_FORMAT = '/([0-9a-fA-F]{32})(?:\/(\d+))?(?:;o=(\d+))?/';

    /**
     * Generate a SpanContext object from the Trace Context header.
     */
    public static function deserialize(string $header): SpanContextInterface
    {
        $matched = preg_match(self::CONTEXT_HEADER_FORMAT, $header, $matches);

        if (!$matched) {
            return SpanContext::getInvalid();
        }

        $spanId = $matches[2] ?? null;
        if (empty($spanId)) {
            $spanId = bin2hex(random_bytes(8));
        } else {
            $spanId = Utils::leftZeroPad(Utils::decToHex($spanId));
        }
        $traced =  $matches[3] ?? '0';

        return SpanContext::createFromRemoteParent(
            strtolower($matches[1]),
            $spanId,
            (int) ('1' === $traced)
        );
    }

    /**
     * Convert a SpanContextInterface to header string.
     */
    public static function serialize(SpanContextInterface $context): string
    {
        $ret = $context->getTraceId();
        if ($context->getSpanId()) {
            $ret .= '/'.Utils::hexToDec($context->getSpanId());
        }

        return $ret.(';o='.($context->isSampled() ? '1' : '0'));
    }
}
