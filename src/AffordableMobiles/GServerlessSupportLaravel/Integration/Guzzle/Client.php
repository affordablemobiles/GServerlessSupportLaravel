<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\Guzzle;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Integration\Guzzle\Middleware as TraceMiddleware;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Facades\Log;

class Client extends GuzzleClient
{
}
