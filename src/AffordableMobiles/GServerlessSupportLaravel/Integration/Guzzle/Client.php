<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\Guzzle;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Integration\Guzzle\Middleware as TraceMiddleware;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Facades\Log;

/**
 * Extension to Guzzle Client to apply relevant middleware & config.
 */
class Client extends GuzzleClient
{
    /**
     * e.g.
     *      $client = new Client([
     *          ...standard guzzle config...,
     *          'gserverlesssupport' => [
     *              'trace' => false,
     *              'auth'  => [
     *                  ...auth config placeholder...,
     *              ],
     *          ],
     *      ]);.
     */
    public function __construct(array $config = [])
    {
        if ((!is_g_serverless()) || (\PHP_SAPI === 'cli')) {
            return parent::__construct($config);
        }

        if (!isset($config['handler'])) {
            $config['handler'] = HandlerStack::create();
            $config['handler']->setHandler(\GuzzleHttp\choose_handler());
        }

        if (method_exists($config['handler'], 'push')) {
            // We are able to modify the handler stack, continue...

            // Unless disabled, add TraceMiddleware for sub-request trace merging in StackDriver.
            if (false !== @$config['gserverlesssupport']['trace']) {
                $config['handler']->push(new TraceMiddleware());
            }
        } else {
            Log::warning('AffordableMobiles\\GServerlessSupportLaravel\\Integration\\Guzzle\\Client: Unable to modify handler stack, no push method defined');
        }

        return parent::__construct($config);
    }
}
