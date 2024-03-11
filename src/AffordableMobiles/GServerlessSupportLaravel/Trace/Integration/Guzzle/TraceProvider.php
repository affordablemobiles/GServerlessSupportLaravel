<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Integration\Guzzle;

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

        opencensus_trace_method(GuzzleClient::class, 'transfer', [self::class, 'handleRequest']);
    }

    public static function handleRequest($scope, $request, array $options = [])
    {
        return [
            'name'       => 'GuzzleHttp::request',
            'attributes' => [
                'method' => $request->getMethod(),
                'uri'    => $request->getUri(),
            ],
        ];
    }
}
