<?php

namespace A1comms\GaeSupportLaravel\Trace\Integration\Guzzle;

use OpenCensus\Trace\Integrations\IntegrationInterface;
use GuzzleHttp\Client as GuzzleClient;

class TraceProvider implements IntegrationInterface
{
    public static function load()
    {
        if (!extension_loaded('opencensus')) {
            trigger_error('opencensus extension required to load Laravel integrations.', E_USER_WARNING);
            return;
        }

        opencensus_trace_method(GuzzleClient::class, '__call', [self::class, 'handleRequest']);
    }

    public static function handleRequest($scope, $method, $args)
    {
        return [
            'name' => 'GuzzleHttp::request',
            'attributes' => [
                'method' => $method,
                'uri' => (count($args) < 1) ? 'invalid' : $args[0],
            ],
        ];
    }
}