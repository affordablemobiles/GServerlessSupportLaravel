<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Trace\Integration\Guzzle;

use GuzzleHttp\Client as GuzzleClient;
use OpenCensus\Trace\Integrations\IntegrationInterface;

class TraceProvider implements IntegrationInterface
{
    public static function load(): void
    {
        if (!\extension_loaded('opencensus')) {
            trigger_error('opencensus extension required to load Laravel integrations.', E_USER_WARNING);

            return;
        }

        opencensus_trace_method(GuzzleClient::class, '__call', [self::class, 'handleRequest']);
    }

    public static function handleRequest($scope, $method, $args)
    {
        $uri = '';

        if (\count($args) < 1) {
            $uri = 'invalid';
        } else {
            // Make sure we remove the query string,
            // as this can contain sensitive data!
            $uri = explode('?', $args[0])[0];
        }

        return [
            'name' => 'GuzzleHttp::request',
            'attributes' => [
                'method' => $method,
                'uri' => $uri,
            ],
        ];
    }
}
