<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Integration\Guzzle;

use OpenCensus\Trace\Propagator\HttpHeaderPropagator;
use OpenCensus\Trace\Propagator\PropagatorInterface;
use OpenCensus\Trace\Tracer;
use Psr\Http\Message\RequestInterface;

class Middleware
{
    /**
     * @var PropagatorInterface
     */
    private $propagator;

    /**
     * Create a new Guzzle middleware that creates trace spans and propagates the current
     * trace context to the downstream request.
     *
     * @param PropagatorInterface $propagator Interface responsible for serializing trace context
     */
    public function __construct(PropagatorInterface $propagator = null)
    {
        $this->propagator = $propagator ?: new HttpHeaderPropagator();
    }

    /**
     * Magic method which makes this object callable. Guzzle middleware are expected to be
     * callables.
     *
     * @param callable $handler The next handler in the HandlerStack
     *
     * @return callable
     */
    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, $options) use ($handler) {
            $context = Tracer::spanContext();
            if ($context->enabled()) {
                $request = $request->withHeader(
                    $this->propagator->key(),
                    $this->propagator->formatter()->serialize($context)
                );
            }

            return $handler($request, $options);
        };
    }
}
